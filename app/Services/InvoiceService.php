<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ClientProposalStatusModel;
use App\Models\CompanyProfileModel;
use App\Models\CustomerAddressModel;
use App\Models\CustomerModel;
use App\Models\FundingSourceModel;
use App\Models\InvoiceItemModel;
use App\Models\InvoiceModel;
use App\Models\InvoiceStatusModel;
use App\Models\InvoiceWithholdingModel;
use App\Models\PaymentMethodModel;
use App\Models\PaymentTermModel;
use App\Models\ProposalItemModel;
use App\Models\ProposalModel;
use App\Models\TaxWithholdingRuleModel;
use CodeIgniter\Database\BaseConnection;
use RuntimeException;
use Throwable;

/**
 * Invoice operations (database.md §8, §11). Every write runs in one transaction with the invoice row locked.
 *
 * - createFromProposal(): DRAFT invoice from an approved proposal, items copied (one active invoice per proposal).
 * - update() / deleteDraft(): only while DRAFT.
 * - issue(): INV number from document_sequence, issue/due date, expected withholding (WithholdingCalculator), lock.
 * - void(): admin only, and only while nothing was paid, withheld or credited.
 * - refreshStatus(): payment status from the amounts, used by payments and credit notes.
 */
final class InvoiceService
{
    /**
     * Header fields accepted from the draft form.
     *
     * @var list<string>
     */
    public const HEADER_FIELDS = [
        'billing_address', 'customer_tax_id', 'payment_term_id', 'payment_method_id', 'shipment_id', 'bank_account_id',
        'funding_source_id', 'sales_rep_id', 'shipping_cost', 'notes',
    ];

    public function __construct(
        private readonly InvoiceModel $invoices = new InvoiceModel(),
        private readonly InvoiceItemModel $items = new InvoiceItemModel(),
        private readonly InvoiceStatusModel $statuses = new InvoiceStatusModel(),
    ) {
    }

    /**
     * Create a DRAFT invoice from a STORE_APPROVE proposal, copying customer data and all item lines.
     *
     * @param int $proposalId Proposal id.
     * @param int $userId     Creating user.
     *
     * @return int New invoice id.
     *
     * @throws RuntimeException When the proposal is not approved or already has an active invoice.
     */
    public function createFromProposal(int $proposalId, int $userId): int
    {
        return Transaction::run(function (BaseConnection $db) use ($proposalId, $userId): int {
            $proposal = $db->query(
                'SELECT p.*, s.code AS status_code FROM client_proposal_list p JOIN client_proposal_status s ON s.id = p.status_id'
                . ' WHERE p.id = ? AND p.deleted_at IS NULL FOR UPDATE',
                [$proposalId],
            )->getRowArray() ?? throw new RuntimeException(lang('Messages.notFoundProposal'));

            if ($proposal['status_code'] !== ProposalWorkflow::STORE_APPROVE) {
                throw new RuntimeException(lang('Messages.invoiceOnlyApproved'));
            }

            $existing = $this->invoices->activeForProposal($proposalId);

            if ($existing !== []) {
                throw new RuntimeException(lang('Messages.proposalHasInvoice', [InvoiceModel::displayNumber($existing[0])]));
            }

            $customer = (new CustomerModel())->find((int) $proposal['customer_id']) ?? throw new RuntimeException(lang('Messages.notFoundCustomer'));
            $termId   = $proposal['payment_term_id'] !== null ? (int) $proposal['payment_term_id'] : $this->defaultPaymentTermId($customer);
            $today    = date('Y-m-d');
            $profile  = (new CompanyProfileModel())->getProfile();

            $lines = array_map(static fn (array $item): array => [
                'id'              => null,
                'product_id'      => (int) $item['product_id'],
                'variant_id'      => $item['variant_id'] !== null ? (int) $item['variant_id'] : null,
                'item_title'      => $item['item_title'],
                'item_description' => $item['item_description'],
                'unit_code'       => $item['unit_code'],
                'item_type'       => $item['item_type'],
                'quantity'        => $item['cpi_quantity'],
                'unit_price'      => $item['unit_price'],
                'discount_amount' => $item['discount_amount'],
                'tax_rate'        => $item['tax_rate'],
                'tax_code'        => $item['tax_code'],
                'price_base'      => $item['price_base'],
                'unit_price_incl' => $item['unit_price_incl'],
                'discount_incl'   => $item['discount_incl'],
            ], (new ProposalItemModel())->where('proposal_id', $proposalId)->orderBy('id')->findAll());

            if ($lines === []) {
                throw new RuntimeException(lang('Messages.proposalNoItems'));
            }

            $totals = DocumentTotals::calculate($lines);

            $id = $this->invoices->insert([
                'invoice_number'    => InvoiceModel::DRAFT_PREFIX . bin2hex(random_bytes(8)),
                'customer_id'       => (int) $customer['id'],
                'proposal_id'       => $proposalId,
                'billing_address'   => $this->defaultBillingAddress((int) $customer['id']),
                'customer_tax_id'   => $customer['cl_tax_id'],
                'issue_date'        => $today,
                'due_date'          => $this->dueDate($today, $termId),
                'payment_term_id'   => $termId,
                'payment_method_id' => $this->optionalLookupId(new PaymentMethodModel(), 'TRANSFER'),
                'shipment_id'       => $proposal['shipment_id'],
                'bank_account_id'   => $profile['default_bank_account_id'] ?? null,
                'status_id'         => $this->statuses->idForCode(InvoiceWorkflow::DRAFT),
                'funding_source_id' => $proposal['funding_source_id'],
                'sales_rep_id'      => $proposal['sales_rep_id'],
                'created_by'        => $userId,
            ] + $this->headerTotals($totals, '0'));

            if ($id === false) {
                throw new ValidationFailed($this->invoices->errors());
            }

            DocumentLines::sync($this->items, 'invoice_id', 'quantity', (int) $id, $lines, $totals, []);

            return (int) $id;
        });
    }

    /**
     * Update header and items of a DRAFT invoice.
     *
     * @param int                        $id     Invoice id.
     * @param array<string, mixed>       $header Header fields (HEADER_FIELDS).
     * @param list<array<string, mixed>> $items  Posted item rows.
     *
     * @return void
     *
     * @throws ValidationFailed
     * @throws RuntimeException When the invoice is not a draft.
     */
    public function update(int $id, array $header, array $items): void
    {
        $header = $this->cleanHeader($header);
        $errors = $this->invoices->validate($header) ? [] : $this->invoices->errors();

        $existing = [];

        foreach ($this->items->where('invoice_id', $id)->findAll() as $row) {
            $existing[(int) $row['id']] = $row;
        }

        $lines = [];

        try {
            $lines = DocumentLines::prepare($items, $existing, date('Y-m-d'));
        } catch (ValidationFailed $e) {
            $errors += $e->errors;
        }

        if ($errors !== []) {
            throw new ValidationFailed($errors);
        }

        Transaction::run(function (BaseConnection $db) use ($id, $header, $lines, $existing): void {
            $invoice = $this->lockInvoice($db, $id);
            $this->assertDraft($invoice);

            $shipping = $header['shipping_cost'] === '' ? '0' : (string) $header['shipping_cost'];
            $totals   = DocumentTotals::calculate($lines, $shipping);
            $data     = $header + $this->headerTotals($totals, $shipping) + [
                'id'       => $id,
                'due_date' => $this->dueDate($invoice['issue_date'], (int) $header['payment_term_id']),
            ];

            if (! $this->invoices->update($id, $data)) {
                throw new ValidationFailed($this->invoices->errors());
            }

            DocumentLines::sync($this->items, 'invoice_id', 'quantity', $id, $lines, $totals, $existing);
            (new CustomerService())->assignRep((int) $invoice['customer_id'], (int) ($header['sales_rep_id'] ?? 0) ?: null);
        });
    }

    /**
     * Delete a DRAFT invoice (soft delete; drafts have no number, so no gap is left).
     *
     * @param int $id Invoice id.
     *
     * @return void
     *
     * @throws RuntimeException When the invoice is not a draft.
     */
    public function deleteDraft(int $id): void
    {
        Transaction::run(function (BaseConnection $db) use ($id): void {
            $this->assertDraft($this->lockInvoice($db, $id));

            foreach ($this->items->where('invoice_id', $id)->findColumn('id') ?? [] as $itemId) {
                $this->items->delete((int) $itemId);
            }

            $this->invoices->delete($id);
        });
    }

    /**
     * Issue a DRAFT invoice: number, issue date (today), due date, expected withholding; the invoice is locked afterwards.
     *
     * @param int $id     Invoice id.
     * @param int $userId Issuing user (audit only).
     *
     * @return string The assigned invoice number.
     *
     * @throws RuntimeException When the invoice cannot be issued.
     */
    public function issue(int $id, int $userId): string
    {
        return Transaction::run(function (BaseConnection $db) use ($id): string {
            $invoice = $this->lockInvoice($db, $id);
            $this->assertDraft($invoice);

            $items = $this->items->where('invoice_id', $id)->findAll();

            $problem = match (true) {
                $items === []                                          => lang('Messages.issueNeedsItems'),
                bccomp((string) $invoice['grand_total'], '0', 2) <= 0  => lang('Messages.issueNeedsTotal'),
                trim((string) $invoice['billing_address']) === ''      => lang('Messages.issueNeedsAddress'),
                default                                                => null,
            };

            if ($problem !== null) {
                throw new RuntimeException($problem);
            }

            $today       = date('Y-m-d');
            $number      = (new DocumentSequence())->next('INV');
            $withholding = $this->calculateWithholding($invoice, $items, $today);
            $model       = new InvoiceWithholdingModel();

            foreach ($withholding as $w) {
                $model->insert([
                    'invoice_id'  => $id,
                    'rule_id'     => $w['rule_id'],
                    'tax_kind'    => $w['tax_kind'],
                    'description' => $w['description'],
                    'base_amount' => $w['base_amount'],
                    'rate'        => $w['rate'],
                    'amount'      => $w['amount'],
                    'regulation'  => $w['regulation'],
                ]);
            }

            $this->invoices->update($id, [
                'invoice_number'    => $number,
                'issue_date'        => $today,
                'due_date'          => $this->dueDate($today, (int) $invoice['payment_term_id']),
                'withholding_total' => WithholdingCalculator::total($withholding),
                'status_id'         => $this->statuses->idForCode(InvoiceWorkflow::ISSUED),
                'issued_at'         => date('Y-m-d H:i:s'),
            ]);

            return $number;
        });
    }

    /**
     * Void an issued invoice (permission invoices.void). Not allowed once money was received, withheld or credited.
     *
     * @param int                   $id     Invoice id.
     * @param string                $reason Why it is voided (required).
     * @param callable(string):bool $can    Permission checker.
     * @param int                   $userId Current user.
     *
     * @return void
     *
     * @throws ValidationFailed When the reason is missing.
     * @throws RuntimeException When voiding is not allowed.
     */
    public function void(int $id, string $reason, callable $can, int $userId): void
    {
        $reason = trim($reason);

        if (! $can('invoices.void')) {
            throw new RuntimeException(lang('Messages.voidAdminOnly'));
        }

        if ($reason === '') {
            throw new ValidationFailed(['void_reason' => lang('Messages.enterReason')]);
        }

        if (mb_strlen($reason) > 2000) {
            throw new ValidationFailed(['void_reason' => lang('Messages.reasonTooLong')]);
        }

        Transaction::run(function (BaseConnection $db) use ($id, $reason, $userId): void {
            $invoice = $this->lockInvoice($db, $id);

            if (! in_array($invoice['status_code'], InvoiceWorkflow::PAYABLE, true)) {
                throw new RuntimeException(match ($invoice['status_code']) {
                    InvoiceWorkflow::DRAFT => lang('Messages.voidDraft'),
                    InvoiceWorkflow::VOID  => lang('Messages.voidAlready'),
                    default                => lang('Messages.voidPaid'),
                });
            }

            $settled = bcadd(bcadd((string) $invoice['amount_paid'], (string) $invoice['withheld_total'], 2), (string) $invoice['credit_total'], 2);

            if (bccomp($settled, '0', 2) > 0) {
                throw new RuntimeException(lang('Messages.voidSettled'));
            }

            $this->invoices->update($id, [
                'status_id'   => $this->statuses->idForCode(InvoiceWorkflow::VOID),
                'void_reason' => $reason,
                'voided_at'   => date('Y-m-d H:i:s'),
                'voided_by'   => $userId,
            ]);
        });
    }

    /**
     * Expected withholding for an invoice (draft preview or at issue), from the rules valid on $date.
     *
     * @param array<string, mixed>       $invoice Invoice row (customer_id, funding_source_id).
     * @param list<array<string, mixed>> $items   Invoice lines (item_type, line_total, tax_amount).
     * @param string                     $date    Y-m-d.
     *
     * @return list<array{rule_id: int, rule_code: string, tax_kind: string, description: string, base_amount: string, rate: string, amount: string, regulation: ?string}>
     */
    public function calculateWithholding(array $invoice, array $items, string $date): array
    {
        $customer = (new CustomerModel())->withDeleted()->find((int) $invoice['customer_id']);
        $funding  = $invoice['funding_source_id'] ? (new FundingSourceModel())->find((int) $invoice['funding_source_id']) : null;
        $profile  = (new CompanyProfileModel())->getProfile();

        return (new WithholdingCalculator())->calculate(
            (new TaxWithholdingRuleModel())->validOn($date),
            array_map(static fn (array $i): array => [
                'item_type'  => $i['item_type'],
                'line_total' => $i['line_total'],
                'tax_amount' => $i['tax_amount'],
            ], $items),
            ['school_status' => $customer['school_status'] ?? null],
            ['is_bos' => (bool) ($funding['is_bos'] ?? false)],
            [
                'is_pkp'               => $profile === null || (int) $profile['is_pkp'] === 1,
                'has_umkm_certificate' => CompanyProfileModel::hasValidUmkmCertificate($profile, $date),
            ],
        );
    }

    /**
     * Recompute the payment status of a locked invoice from its amounts and store it if it changed.
     * Call inside the transaction that changed amount_paid / withheld_total / credit_total / refunded_total.
     *
     * @param int $id Invoice id.
     *
     * @return string The (new) status code.
     */
    public function refreshStatus(int $id): string
    {
        $invoice = $this->invoices->select('invoice.*, invoice_status.code AS status_code')
            ->join('invoice_status', 'invoice_status.id = invoice.status_id')
            ->find($id) ?? throw new RuntimeException(lang('Messages.notFoundInvoice'));

        if (in_array($invoice['status_code'], [InvoiceWorkflow::DRAFT, InvoiceWorkflow::VOID], true)) {
            return $invoice['status_code'];
        }

        $received      = bcadd((string) $invoice['amount_paid'], (string) $invoice['withheld_total'], 2);
        $fullyCredited = bccomp((string) $invoice['credit_total'], (string) $invoice['grand_total'], 2) >= 0;
        $code          = InvoiceWorkflow::statusFor(InvoiceModel::balance($invoice), $received, $invoice['due_date'], date('Y-m-d'), $fullyCredited);

        if ($code !== $invoice['status_code']) {
            $this->invoices->update($id, ['status_id' => $this->statuses->idForCode($code)]);
        }

        return $code;
    }

    /**
     * Set OVERDUE on issued / partially paid invoices whose due date has passed (database.md §11.8).
     *
     * @param string|null $today Y-m-d (defaults to today in the app timezone).
     *
     * @return list<string> Numbers of the invoices that became overdue.
     */
    public function markOverdue(?string $today = null): array
    {
        $today   = $today ?? date('Y-m-d');
        $changed = [];
        $ids     = array_column($this->invoices->select('invoice.id')
            ->join('invoice_status', 'invoice_status.id = invoice.status_id')
            ->whereIn('invoice_status.code', [InvoiceWorkflow::ISSUED, InvoiceWorkflow::PARTIALLY_PAID])
            ->where('invoice.due_date <', $today)
            ->findAll(), 'id');

        foreach ($ids as $id) {
            try {
                $number = Transaction::run(function (BaseConnection $db) use ($id, $today): ?string {
                    $invoice = $this->lockInvoice($db, (int) $id);

                    if (! in_array($invoice['status_code'], [InvoiceWorkflow::ISSUED, InvoiceWorkflow::PARTIALLY_PAID], true) || $invoice['due_date'] >= $today) {
                        return null; // changed meanwhile
                    }

                    $this->invoices->update((int) $id, ['status_id' => $this->statuses->idForCode(InvoiceWorkflow::OVERDUE)]);

                    return $invoice['invoice_number'];
                });
            } catch (Throwable $e) {
                log_message('error', 'Marking invoice {id} overdue failed: {message}', ['id' => $id, 'message' => $e->getMessage()]);
                $number = null;
            }

            if ($number !== null) {
                $changed[] = $number;
            }
        }

        return $changed;
    }

    /**
     * Lock the invoice row (SELECT ... FOR UPDATE) and return it with its status code.
     *
     * @param BaseConnection $db Connection inside the running transaction.
     * @param int            $id Invoice id.
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException When the invoice does not exist.
     */
    public function lockInvoice(BaseConnection $db, int $id): array
    {
        return $db->query(
            'SELECT i.*, s.code AS status_code FROM invoice i JOIN invoice_status s ON s.id = i.status_id'
            . ' WHERE i.id = ? AND i.deleted_at IS NULL FOR UPDATE',
            [$id],
        )->getRowArray() ?? throw new RuntimeException(lang('Messages.notFoundInvoice'));
    }

    /**
     * @param array<string, mixed> $invoice Locked invoice row.
     *
     * @return void
     *
     * @throws RuntimeException When the invoice is not a draft.
     */
    private function assertDraft(array $invoice): void
    {
        if ($invoice['status_code'] !== InvoiceWorkflow::DRAFT) {
            throw new RuntimeException(lang('Messages.invoiceLocked', [mb_strtolower(InvoiceWorkflow::label($invoice['status_code']))]));
        }
    }

    /**
     * Normalize header values; only HEADER_FIELDS are kept.
     *
     * @param array<string, mixed> $header Raw values.
     *
     * @return array<string, mixed>
     */
    private function cleanHeader(array $header): array
    {
        $clean = [];

        foreach (self::HEADER_FIELDS as $field) {
            $value         = $header[$field] ?? '';
            $clean[$field] = is_string($value) ? trim($value) : $value;
        }

        return $clean;
    }

    /**
     * Header total columns from a DocumentTotals result.
     *
     * @param array{subtotal: string, discount_total: string, tax_total: string, grand_total: string} $totals
     * @param string $shipping Shipping cost.
     *
     * @return array<string, string>
     */
    private function headerTotals(array $totals, string $shipping): array
    {
        return [
            'subtotal'       => $totals['subtotal'],
            'discount_total' => $totals['discount_total'],
            'tax_total'      => $totals['tax_total'],
            'shipping_cost'  => CalcSettings::current()->round(DocumentTotals::num($shipping)),
            'grand_total'    => $totals['grand_total'],
        ];
    }

    /**
     * Due date = issue date + payment term days.
     *
     * @param string $issueDate Y-m-d.
     * @param int    $termId    payment_terms id.
     *
     * @return string Y-m-d.
     */
    private function dueDate(string $issueDate, int $termId): string
    {
        $term = (new PaymentTermModel())->find($termId);
        $days = (int) ($term['due_days'] ?? 0);

        return date('Y-m-d', strtotime("{$issueDate} +{$days} days"));
    }

    /**
     * Customer's default payment terms, else NET_30, else the first terms row.
     *
     * @param array<string, mixed> $customer Customer row.
     *
     * @return int
     */
    private function defaultPaymentTermId(array $customer): int
    {
        if (! empty($customer['default_payment_term_id'])) {
            return (int) $customer['default_payment_term_id'];
        }

        $terms = new PaymentTermModel();

        return $this->optionalLookupId($terms, 'NET_30')
            ?? (int) ($terms->orderBy('due_days')->first()['id'] ?? throw new RuntimeException(lang('Messages.noPaymentTerms')));
    }

    /**
     * Customer's default billing address (else any address) as one text block.
     *
     * @param int $customerId Customer id.
     *
     * @return string Empty when the customer has no address.
     */
    private function defaultBillingAddress(int $customerId): string
    {
        $addresses = (new CustomerAddressModel())->forCustomer($customerId);
        $billing   = array_values(array_filter($addresses, static fn (array $a): bool => $a['address_type'] === 'BILLING'));
        $address   = $billing[0] ?? $addresses[0] ?? null;

        return $address !== null ? CustomerAddressModel::format($address) : '';
    }

    /**
     * Id of a lookup code, or null when it is not set up.
     *
     * @param \App\Models\LookupModel $model Lookup model.
     * @param string                  $code  Code.
     *
     * @return int|null
     */
    private function optionalLookupId(\App\Models\LookupModel $model, string $code): ?int
    {
        try {
            return $model->idForCode($code);
        } catch (RuntimeException) {
            return null;
        }
    }
}
