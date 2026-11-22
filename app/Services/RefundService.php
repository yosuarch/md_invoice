<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\InvoiceModel;
use App\Models\RefundModel;
use CodeIgniter\Database\BaseConnection;
use RuntimeException;

/**
 * Refunds (database.md §11.6b): paying money back from an invoice's credit balance, e.g. an overpayment or a
 * cancellation after payment (credit note with reason CANCEL_BY_CLIENT / CANCEL_BY_STORE, then refund).
 *
 * - Only cash we received can be refunded: amount <= InvoiceModel::refundable() (credit balance capped by
 *   amount_paid − refunded_total). Taxes withheld by the treasurer are reclaimed by the treasurer from the tax
 *   office with a nota retur / nota pembatalan (PMK 81/2024), not refunded by us. There is no tax API integration:
 *   the treasurer's e-billing and corrections are done manually at the Dinas Pendidikan / tax office.
 * - Never edited or deleted; a failed refund transfer is reversed (permission payments.reverse).
 */
final class RefundService
{
    /**
     * Refund fields accepted from the form.
     *
     * @var list<string>
     */
    public const FIELDS = ['refund_date', 'amount', 'payment_method_id', 'bank_account_id', 'reference_number', 'reason'];

    public function __construct(
        private readonly RefundModel $refunds = new RefundModel(),
        private readonly InvoiceService $invoiceService = new InvoiceService(),
    ) {
    }

    /**
     * Record a refund for an invoice with a credit balance.
     *
     * @param int                  $invoiceId Invoice id.
     * @param array<string, mixed> $data      Refund fields (FIELDS).
     * @param int                  $userId    Recording user.
     *
     * @return int New refund id.
     *
     * @throws ValidationFailed  Field errors.
     * @throws RuntimeException When nothing can be refunded.
     */
    public function record(int $invoiceId, array $data, int $userId): int
    {
        $clean = [];

        foreach (self::FIELDS as $field) {
            $clean[$field] = trim((string) ($data[$field] ?? ''));
        }

        return Transaction::run(function (BaseConnection $db) use ($invoiceId, $clean, $userId): int {
            $invoice    = $this->invoiceService->lockInvoice($db, $invoiceId);
            $refundable = InvoiceModel::refundable($invoice);

            if (in_array($invoice['status_code'], [InvoiceWorkflow::DRAFT, InvoiceWorkflow::VOID], true) || bccomp($refundable, '0', 2) <= 0) {
                throw new RuntimeException(lang('Messages.nothingToRefund'));
            }

            $errors = $this->refunds->validate($clean) ? [] : $this->refunds->errors();

            if (! DocumentLines::isDecimal($clean['amount'], 2) || bccomp($clean['amount'], '0', 2) <= 0) {
                $errors['amount'] = lang('Messages.enterRefund');
            } elseif (bccomp($clean['amount'], $refundable, 2) > 0) {
                $errors['amount'] = lang('Messages.refundMax', [format_idr($refundable)]);
            }

            if (! isset($errors['refund_date'])) {
                if ($clean['refund_date'] > date('Y-m-d')) {
                    $errors['refund_date'] = lang('Messages.refundDateFuture');
                } elseif ($clean['refund_date'] < $invoice['issue_date']) {
                    $errors['refund_date'] = lang('Messages.refundDateBefore');
                }
            }

            if ($errors !== []) {
                throw new ValidationFailed($errors);
            }

            $id = $this->refunds->insert($clean + ['invoice_id' => $invoiceId, 'recorded_by' => $userId]);

            if ($id === false) {
                throw new ValidationFailed($this->refunds->errors());
            }

            (new InvoiceModel())->update($invoiceId, ['refunded_total' => bcadd((string) $invoice['refunded_total'], $clean['amount'], 2)]);
            $this->invoiceService->refreshStatus($invoiceId);

            return (int) $id;
        });
    }

    /**
     * Reverse a refund whose transfer failed or that was entered by mistake (permission payments.reverse).
     * The refund stays on record, marked reversed; the invoice's refunded_total is reduced again.
     *
     * @param int                   $refundId Refund id.
     * @param string                $reason   Why (required).
     * @param callable(string):bool $can      Permission checker.
     * @param int                   $userId   Current user.
     *
     * @return int Invoice id.
     *
     * @throws ValidationFailed When the reason is missing.
     * @throws RuntimeException When not allowed.
     */
    public function reverse(int $refundId, string $reason, callable $can, int $userId): int
    {
        $reason = trim($reason);

        if (! $can('payments.reverse')) {
            throw new RuntimeException(lang('Messages.reverseRefundsDenied'));
        }

        if ($reason === '' || mb_strlen($reason) > 2000) {
            throw new ValidationFailed(['reversal_reason' => lang('Messages.reasonRequired2000')]);
        }

        $refund = $this->refunds->find($refundId) ?? throw new RuntimeException(lang('Messages.notFoundRefund'));

        return Transaction::run(function (BaseConnection $db) use ($refund, $reason, $userId): int {
            $invoice = $this->invoiceService->lockInvoice($db, (int) $refund['invoice_id']);
            $current = $db->query('SELECT reversed_at FROM refund WHERE id = ? FOR UPDATE', [$refund['id']])->getRowArray();

            if ($current['reversed_at'] !== null) {
                throw new RuntimeException(lang('Messages.refundAlreadyReversed'));
            }

            $this->refunds->update((int) $refund['id'], [
                'reversed_at'     => date('Y-m-d H:i:s'),
                'reversed_by'     => $userId,
                'reversal_reason' => $reason,
            ]);

            (new InvoiceModel())->update((int) $invoice['id'], [
                'refunded_total' => bcsub((string) $invoice['refunded_total'], (string) $refund['amount'], 2),
            ]);

            $this->invoiceService->refreshStatus((int) $invoice['id']);

            return (int) $invoice['id'];
        });
    }
}
