<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\InvoiceModel;
use App\Models\InvoiceWithholdingModel;
use App\Models\PaymentModel;
use App\Models\PaymentWithholdingModel;
use CodeIgniter\Database\BaseConnection;
use RuntimeException;

/**
 * Records payments (database.md §11.6): cash received plus the taxes the treasurer withheld, in one transaction
 * with the invoice row locked; then amount_paid / withheld_total and the invoice status are updated.
 * More than the open balance is only accepted when confirmed: the excess becomes a credit balance to refund.
 * Payments are never edited or deleted: a failed or wrong payment is reversed (reason, user and time kept);
 * only the proof (bukti potong / NTPN) of a withholding can be completed later.
 */
final class PaymentService
{
    /**
     * Payment fields accepted from the form.
     *
     * @var list<string>
     */
    public const FIELDS = ['payment_date', 'amount', 'payment_method_id', 'bank_account_id', 'reference_number', 'note'];

    /**
     * Withholding row fields accepted from the form ("withholdings[n][field]").
     * invoice_withholding_id empty = a tax that was not expected (tax_kind required).
     *
     * @var list<string>
     */
    public const WITHHOLDING_FIELDS = ['invoice_withholding_id', 'tax_kind', 'amount', 'document_number', 'document_date'];

    public function __construct(
        private readonly PaymentModel $payments = new PaymentModel(),
        private readonly PaymentWithholdingModel $withholdings = new PaymentWithholdingModel(),
        private readonly InvoiceService $invoiceService = new InvoiceService(),
    ) {
    }

    /**
     * Record a payment for an issued invoice.
     *
     * @param int                        $invoiceId    Invoice id.
     * @param array<string, mixed>       $data         Payment fields (FIELDS).
     * @param list<array<string, mixed>> $withholdings Withheld taxes (WITHHOLDING_FIELDS); rows with amount 0 are ignored.
     * @param int                        $userId       Recording user.
     * @param bool                       $acceptOverpayment The user confirmed that more than the open balance was received.
     *
     * @return int New payment id.
     *
     * @throws ValidationFailed  Field errors (keys: FIELDS, "withholdings.N").
     * @throws RuntimeException When the invoice does not accept payments.
     */
    public function record(int $invoiceId, array $data, array $withholdings, int $userId, bool $acceptOverpayment = false): int
    {
        $data = $this->clean($data);

        return Transaction::run(function (BaseConnection $db) use ($invoiceId, $data, $withholdings, $userId, $acceptOverpayment): int {
            $invoice = $this->invoiceService->lockInvoice($db, $invoiceId);

            if (! in_array($invoice['status_code'], InvoiceWorkflow::PAYABLE, true)) {
                throw new RuntimeException(lang('Messages.paymentsOnlyPayable', [InvoiceWorkflow::label($invoice['status_code'])]));
            }

            $expected = [];

            foreach ((new InvoiceWithholdingModel())->where('invoice_id', $invoiceId)->findAll() as $row) {
                $expected[(int) $row['id']] = $row;
            }

            [$rows, $errors] = $this->prepareWithholdings($withholdings, $expected);
            $errors += $this->validatePayment($data, $invoice);

            if ($errors !== []) {
                throw new ValidationFailed($errors);
            }

            $withheld = '0.00';

            foreach ($rows as $row) {
                $withheld = bcadd($withheld, $row['amount'], 2);
            }

            $balance = InvoiceModel::balance($invoice);

            if (! $acceptOverpayment && bccomp(bcadd($data['amount'], $withheld, 2), $balance, 2) > 0) {
                throw new ValidationFailed(['amount' => lang('Messages.overpayment', [format_idr(bcadd($data['amount'], $withheld, 2)), format_idr($balance)])]);
            }

            // Kwitansi (receipt) number, gap-free in the same transaction.
            $paymentId = $this->payments->insert($data + [
                'invoice_id'     => $invoiceId,
                'recorded_by'    => $userId,
                'receipt_number' => (new DocumentSequence())->next('KW'),
            ]);

            if ($paymentId === false) {
                throw new ValidationFailed($this->payments->errors());
            }

            foreach ($rows as $row) {
                $this->withholdings->insert($row + ['payment_id' => (int) $paymentId]);
            }

            (new InvoiceModel())->update($invoiceId, [
                'amount_paid'    => bcadd((string) $invoice['amount_paid'], $data['amount'], 2),
                'withheld_total' => bcadd((string) $invoice['withheld_total'], $withheld, 2),
            ]);

            $this->invoiceService->refreshStatus($invoiceId);

            return (int) $paymentId;
        });
    }

    /**
     * Reverse a payment that failed (bounced transfer) or was entered by mistake (permission payments.reverse).
     * The payment and its withheld taxes stay on record, marked reversed with reason, user and time;
     * the invoice totals and status are recalculated. Not allowed while refunds exist on the invoice.
     *
     * @param int                   $paymentId Payment id.
     * @param string                $reason    Why (required).
     * @param callable(string):bool $can       Permission checker.
     * @param int                   $userId    Current user.
     *
     * @return int Invoice id.
     *
     * @throws ValidationFailed When the reason is missing.
     * @throws RuntimeException When reversing is not allowed.
     */
    public function reverse(int $paymentId, string $reason, callable $can, int $userId): int
    {
        $reason = trim($reason);

        if (! $can('payments.reverse')) {
            throw new RuntimeException(lang('Messages.reversePaymentsDenied'));
        }

        if ($reason === '' || mb_strlen($reason) > 2000) {
            throw new ValidationFailed(['reversal_reason' => lang('Messages.reasonRequired2000')]);
        }

        $payment = $this->payments->find($paymentId) ?? throw new RuntimeException(lang('Messages.notFoundPayment'));

        return Transaction::run(function (BaseConnection $db) use ($payment, $reason, $userId): int {
            $invoice = $this->invoiceService->lockInvoice($db, (int) $payment['invoice_id']);
            $current = $db->query('SELECT reversed_at FROM payment WHERE id = ? FOR UPDATE', [$payment['id']])->getRowArray();

            if ($current['reversed_at'] !== null) {
                throw new RuntimeException(lang('Messages.paymentAlreadyReversed'));
            }

            if (bccomp((string) $invoice['refunded_total'], '0', 2) > 0) {
                throw new RuntimeException(lang('Messages.reverseRefundsFirst'));
            }

            $withheld = (string) ($db->query(
                'SELECT COALESCE(SUM(amount), 0) AS total FROM payment_withholding WHERE payment_id = ? AND deleted_at IS NULL',
                [$payment['id']],
            )->getRowArray()['total'] ?? '0');

            $this->payments->update((int) $payment['id'], [
                'reversed_at'     => date('Y-m-d H:i:s'),
                'reversed_by'     => $userId,
                'reversal_reason' => $reason,
            ]);

            (new InvoiceModel())->update((int) $invoice['id'], [
                'amount_paid'    => bcsub((string) $invoice['amount_paid'], (string) $payment['amount'], 2),
                'withheld_total' => bcsub((string) $invoice['withheld_total'], $withheld, 2),
            ]);

            $this->invoiceService->refreshStatus((int) $invoice['id']);

            return (int) $invoice['id'];
        });
    }

    /**
     * Complete the proof of a withheld tax (bukti potong / NTPN number and date). Amounts never change.
     *
     * @param int    $id     payment_withholding id.
     * @param string $number Proof number.
     * @param string $date   Proof date (Y-m-d or empty).
     *
     * @return void
     *
     * @throws ValidationFailed
     * @throws RuntimeException When the row does not exist.
     */
    public function updateProof(int $id, string $number, string $date): void
    {
        if ($this->withholdings->find($id) === null) {
            throw new RuntimeException(lang('Messages.notFoundWithholding'));
        }

        $data = ['document_number' => trim($number), 'document_date' => trim($date)];

        if (! $this->withholdings->update($id, $data)) {
            throw new ValidationFailed($this->withholdings->errors());
        }
    }

    /**
     * Default values for the payment form: cash = balance minus the withholding still expected.
     *
     * @param array<string, mixed>       $invoice  Invoice row.
     * @param list<array<string, mixed>> $expected Expected withholdings with 'withheld' (InvoiceWithholdingModel::forInvoice()).
     *
     * @return array{amount: string, withholdings: list<array<string, mixed>>}
     */
    public static function defaults(array $invoice, array $expected): array
    {
        $balance = InvoiceModel::balance($invoice);
        $rows    = [];
        $open    = '0.00';

        foreach ($expected as $w) {
            $remaining = bcsub((string) $w['amount'], (string) $w['withheld'], 2);
            $remaining = bccomp($remaining, '0', 2) > 0 ? $remaining : '0.00';
            $open      = bcadd($open, $remaining, 2);

            $rows[] = [
                'invoice_withholding_id' => (int) $w['id'],
                'tax_kind'               => $w['tax_kind'],
                'description'            => $w['description'],
                'expected'               => $w['amount'],
                'withheld'               => $w['withheld'],
                'amount'                 => $remaining,
                'document_number'        => '',
                'document_date'          => '',
            ];
        }

        $cash = bcsub($balance, $open, 2);

        return ['amount' => bccomp($cash, '0', 2) > 0 ? $cash : $balance, 'withholdings' => $rows];
    }

    /**
     * Trim payment fields; only FIELDS are kept.
     *
     * @param array<string, mixed> $data Raw values.
     *
     * @return array<string, string>
     */
    private function clean(array $data): array
    {
        $clean = [];

        foreach (self::FIELDS as $field) {
            $clean[$field] = trim((string) ($data[$field] ?? ''));
        }

        return $clean;
    }

    /**
     * Field checks for the payment itself.
     *
     * @param array<string, string> $data    Clean payment fields.
     * @param array<string, mixed>  $invoice Locked invoice row.
     *
     * @return array<string, string>
     */
    private function validatePayment(array $data, array $invoice): array
    {
        $errors = $this->payments->validate($data) ? [] : $this->payments->errors();

        if (! DocumentLines::isDecimal($data['amount'], 2) || bccomp($data['amount'], '0', 2) <= 0) {
            $errors['amount'] = lang('Messages.enterCash');
        }

        if (! isset($errors['payment_date'])) {
            if ($data['payment_date'] > date('Y-m-d')) {
                $errors['payment_date'] = lang('Messages.paymentDateFuture');
            } elseif ($data['payment_date'] < $invoice['issue_date']) {
                $errors['payment_date'] = lang('Messages.paymentDateBefore', [format_date($invoice['issue_date'])]);
            }
        }

        return $errors;
    }

    /**
     * Validate posted withholding rows.
     *
     * @param list<array<string, mixed>>       $posted   Posted rows.
     * @param array<int, array<string, mixed>> $expected Expected withholdings of the invoice by id.
     *
     * @return array{0: list<array<string, mixed>>, 1: array<string, string>} [rows to insert, errors]
     */
    private function prepareWithholdings(array $posted, array $expected): array
    {
        $rows   = [];
        $errors = [];

        foreach (array_values($posted) as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $n      = $index + 1;
            $row    = array_map(static fn ($v) => trim((string) $v), array_intersect_key($row, array_flip(self::WITHHOLDING_FIELDS)));
            $amount = $row['amount'] ?? '';

            if ($amount === '' || (DocumentLines::isDecimal($amount, 2) && bccomp($amount, '0', 2) === 0)) {
                continue; // not withheld with this payment
            }

            $linkId = (int) ($row['invoice_withholding_id'] ?? 0);
            $kind   = $linkId > 0 ? ($expected[$linkId]['tax_kind'] ?? null) : ($row['tax_kind'] ?? '');
            $number = $row['document_number'] ?? '';
            $date   = $row['document_date'] ?? '';

            $error = match (true) {
                $linkId > 0 && $kind === null                                     => lang('Messages.whUnknown'),
                ! array_key_exists((string) $kind, InvoiceWithholdingModel::KINDS) => lang('Messages.whSelectTax'),
                ! DocumentLines::isDecimal($amount, 2)                             => lang('Messages.whAmount'),
                mb_strlen($number) > 64                                            => lang('Messages.whNumberLong'),
                $date !== '' && ! self::isDate($date)                              => lang('Messages.whDateInvalid'),
                default                                                            => null,
            };

            if ($error !== null) {
                $errors["withholdings.{$n}"] = lang('Messages.withheldLine', [$n, $error]);

                continue;
            }

            $rows[] = [
                'invoice_withholding_id' => $linkId > 0 ? $linkId : null,
                'tax_kind'               => $kind,
                'amount'                 => bcadd($amount, '0', 2),
                'document_number'        => $number === '' ? null : $number,
                'document_date'          => $date === '' ? null : $date,
            ];
        }

        return [$rows, $errors];
    }

    /**
     * Whether $value is a valid Y-m-d date.
     *
     * @param string $value Input.
     *
     * @return bool
     */
    private static function isDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
