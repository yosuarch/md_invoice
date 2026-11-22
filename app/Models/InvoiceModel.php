<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\InvoiceWorkflow;

/**
 * invoice: created from an approved proposal and changed only through App\Services\InvoiceService
 * (draft edits, issue with number + expected withholding, void) and the payment / credit note services.
 *
 * Drafts carry a placeholder number ("DRAFT-..."); the real number (INV/Y/m/nnnn) is assigned when issued,
 * so deleted drafts never leave gaps.
 */
class InvoiceModel extends BaseModel
{
    /**
     * Prefix of the placeholder number stored on drafts.
     */
    public const DRAFT_PREFIX = 'DRAFT-';

    protected $table         = 'invoice';
    protected array $lowercase = ['billing_address']; // stored lower case, shown with up()
    protected $allowedFields = [
        'invoice_number', 'customer_id', 'proposal_id', 'billing_address', 'customer_tax_id', 'issue_date', 'due_date',
        'payment_term_id', 'payment_method_id', 'shipment_id', 'bank_account_id', 'status_id', 'funding_source_id', 'sales_rep_id',
        'subtotal', 'discount_total', 'tax_total', 'shipping_cost', 'grand_total', 'withholding_total', 'amount_paid',
        'withheld_total', 'credit_total', 'refunded_total', 'notes', 'issued_at', 'void_reason', 'voided_at', 'voided_by', 'created_by',
    ];
    protected array $nullable = [
        'proposal_id', 'customer_tax_id', 'payment_method_id', 'shipment_id', 'bank_account_id', 'funding_source_id',
        'sales_rep_id', 'notes', 'void_reason',
    ];
    protected array $fieldLabels = [
        'billing_address'   => 'Billing address',
        'customer_tax_id'   => 'Customer NPWP',
        'payment_term_id'   => 'Payment terms',
        'payment_method_id' => 'Payment method',
        'shipment_id'       => 'Shipment',
        'bank_account_id'   => 'Bank account',
        'funding_source_id' => 'Funding source',
        'sales_rep_id'      => 'Sales rep',
        'shipping_cost'     => 'Shipping cost',
    ];

    protected array $referencedBy = ['payment' => 'invoice_id', 'credit_note' => 'invoice_id', 'delivery_note' => 'invoice_id'];

    protected $validationRules = [
        'billing_address'   => 'permit_empty|max_length[1000]',
        'customer_tax_id'   => 'permit_empty|max_length[32]|npwp',
        'payment_term_id'   => 'required|is_natural_no_zero|is_not_unique[payment_terms.id]',
        'payment_method_id' => 'permit_empty|is_natural_no_zero|is_not_unique[payment_method.id]',
        'shipment_id'       => 'permit_empty|is_natural_no_zero|is_not_unique[shipment.id]',
        'bank_account_id'   => 'permit_empty|is_natural_no_zero|is_not_unique[bank_account_list.id]',
        'funding_source_id' => 'permit_empty|is_natural_no_zero|is_not_unique[funding_source.id]',
        'sales_rep_id'      => 'permit_empty|is_natural_no_zero|is_not_unique[sales_representative.id]',
        'shipping_cost'     => 'permit_empty|decimal|greater_than_equal_to[0]|less_than_equal_to[9999999999999.99]',
        'notes'             => 'permit_empty|max_length[5000]',
    ];

    /**
     * Number to display: the invoice number, or "Draft #id" before it is issued.
     *
     * @param array<string, mixed> $invoice Invoice row.
     *
     * @return string
     */
    public static function displayNumber(array $invoice): string
    {
        return str_starts_with((string) $invoice['invoice_number'], self::DRAFT_PREFIX)
            ? lang('Invoices.draftNo', [$invoice['id']])
            : (string) $invoice['invoice_number'];
    }

    /**
     * Open balance: grand_total − credit_total − amount_paid − withheld_total + refunded_total (database.md §11.6).
     * Negative = credit balance: the customer paid more than it owes (overpayment, or credit notes after payment).
     *
     * @param array<string, mixed> $invoice Invoice row.
     *
     * @return string Decimal string.
     */
    public static function balance(array $invoice): string
    {
        $balance = bcsub((string) $invoice['grand_total'], (string) $invoice['credit_total'], 2);
        $balance = bcsub($balance, (string) $invoice['amount_paid'], 2);
        $balance = bcsub($balance, (string) $invoice['withheld_total'], 2);

        return bcadd($balance, (string) ($invoice['refunded_total'] ?? '0'), 2);
    }

    /**
     * Cash we can refund: the credit balance, but never more than the cash actually received and not yet refunded.
     * Taxes withheld by the treasurer were never paid to us (the treasurer pays them via e-billing); they are corrected
     * manually at the Dinas Pendidikan / tax office, outside this app.
     *
     * @param array<string, mixed> $invoice Invoice row.
     *
     * @return string Decimal string >= 0.
     */
    public static function refundable(array $invoice): string
    {
        $credit = bcmul(self::balance($invoice), '-1', 2);
        $cash   = bcsub((string) $invoice['amount_paid'], (string) ($invoice['refunded_total'] ?? '0'), 2);
        $amount = bccomp($credit, $cash, 2) < 0 ? $credit : $cash;

        return bccomp($amount, '0', 2) > 0 ? $amount : '0.00';
    }

    /**
     * Part of a credit balance that consists of withheld taxes (not refundable by us; reclaimed by the treasurer
     * from the tax office with a nota retur / nota pembatalan, PMK 81/2024).
     *
     * @param array<string, mixed> $invoice Invoice row.
     *
     * @return string Decimal string >= 0.
     */
    public static function taxToReclaim(array $invoice): string
    {
        $credit = bcmul(self::balance($invoice), '-1', 2);
        $rest   = bcsub($credit, self::refundable($invoice), 2);

        return bccomp($rest, '0', 2) > 0 ? $rest : '0.00';
    }

    /**
     * Whether the invoice is unpaid and past its due date (also before the overdue command has run).
     *
     * @param array<string, mixed> $invoice Invoice row with status_code.
     *
     * @return bool
     */
    public static function isPastDue(array $invoice): bool
    {
        return in_array($invoice['status_code'], InvoiceWorkflow::PAYABLE, true) && $invoice['due_date'] < date('Y-m-d');
    }

    /**
     * List/detail query joined with customer, status, payment terms, sales rep, funding source and proposal.
     *
     * @return $this
     */
    public function withDetails(): self
    {
        return $this->select(
            'invoice.*, customer_list.cl_organization_name AS customer_name, customer_list.npsn, customer_list.school_status,'
            . ' invoice_status.code AS status_code, payment_terms.code AS payment_term_code, payment_terms.due_days,'
            . ' sales_representative.rep_name, funding_source.name AS funding_source_name, funding_source.is_bos,'
            . ' client_proposal_list.cpl_id AS proposal_number',
        )
            ->join('customer_list', 'customer_list.id = invoice.customer_id')
            ->join('invoice_status', 'invoice_status.id = invoice.status_id')
            ->join('payment_terms', 'payment_terms.id = invoice.payment_term_id')
            ->join('sales_representative', 'sales_representative.id = invoice.sales_rep_id', 'left')
            ->join('funding_source', 'funding_source.id = invoice.funding_source_id', 'left')
            ->join('client_proposal_list', 'client_proposal_list.id = invoice.proposal_id', 'left');
    }

    /**
     * List query with search and filters, newest first.
     *
     * @param array{q?: string, status?: string, customer?: string, rep?: string} $filters
     *        status: a status code, 'unpaid' (issued, partially paid, overdue), 'past_due' (unpaid and due date passed)
     *        'refund_due' (credit balance with cash to pay back) or 'to_ship' (issued, goods not yet on a delivery note).
     * @param int|null $myRepId Sales rep linked to the current user (for rep = 'mine').
     *
     * @return $this
     */
    public function search(array $filters, ?int $myRepId = null): self
    {
        $this->withDetails()->orderBy('invoice.id', 'DESC');

        $q = trim((string) ($filters['q'] ?? ''));

        if ($q !== '') {
            $this->groupStart()
                ->like('invoice.invoice_number', $q)
                ->orLike('customer_list.cl_organization_name', $q)
                ->orLike('customer_list.npsn', $q)
                ->orLike('client_proposal_list.cpl_id', $q)
                ->groupEnd();
        }

        $status = (string) ($filters['status'] ?? '');

        if ($status === 'unpaid') {
            $this->whereIn('invoice_status.code', InvoiceWorkflow::PAYABLE);
        } elseif ($status === 'to_ship') {
            // Issued invoices with goods not yet on a (non-cancelled) delivery note.
            $this->whereIn('invoice_status.code', \App\Services\DeliveryService::DELIVERABLE)
                ->where(
                    "EXISTS (SELECT 1 FROM invoice_item ii WHERE ii.invoice_id = invoice.id AND ii.deleted_at IS NULL AND ii.item_type = 'GOODS'"
                    . ' AND ii.quantity > COALESCE((SELECT SUM(dni.quantity) FROM delivery_note_item dni'
                    . ' JOIN delivery_note dn ON dn.id = dni.delivery_note_id'
                    . ' WHERE dni.invoice_item_id = ii.id AND dni.deleted_at IS NULL AND dn.deleted_at IS NULL AND dn.cancelled_at IS NULL), 0))',
                    null,
                    false,
                );
        } elseif ($status === 'refund_due') {
            $this->where('(invoice.grand_total - invoice.credit_total - invoice.amount_paid - invoice.withheld_total + invoice.refunded_total) < 0', null, false)
                ->where('invoice.amount_paid > invoice.refunded_total', null, false);
        } elseif ($status === 'past_due') {
            $this->whereIn('invoice_status.code', InvoiceWorkflow::PAYABLE)->where('invoice.due_date <', date('Y-m-d'));
        } elseif ($status !== '') {
            $this->where('invoice_status.code', $status);
        }

        if (($filters['customer'] ?? '') !== '') {
            $this->where('invoice.customer_id', (int) $filters['customer']);
        }

        $rep = (string) ($filters['rep'] ?? '');

        if ($rep !== '') {
            $this->where('invoice.sales_rep_id', $rep === 'mine' ? ($myRepId ?? 0) : (int) $rep);
        }

        return $this;
    }

    /**
     * Invoices of a proposal that are not void (normally at most one).
     *
     * @param int $proposalId Proposal id.
     *
     * @return list<array<string, mixed>>
     */
    public function activeForProposal(int $proposalId): array
    {
        return $this->select('invoice.*, invoice_status.code AS status_code')
            ->join('invoice_status', 'invoice_status.id = invoice.status_id')
            ->where('invoice.proposal_id', $proposalId)
            ->where('invoice_status.code !=', InvoiceWorkflow::VOID)
            ->orderBy('invoice.id')
            ->findAll();
    }

    /**
     * Unpaid invoices ordered by due date (dashboard "due soon").
     *
     * @param int         $limit Max rows.
     * @param string|null $scope Extra SQL condition (App\Services\AccessScope::invoiceSql()), or null for all.
     *
     * @return list<array<string, mixed>>
     */
    public function dueSoon(int $limit = 8, ?string $scope = null): array
    {
        if ($scope !== null) {
            $this->where($scope, null, false);
        }

        return $this->withDetails()
            ->whereIn('invoice_status.code', InvoiceWorkflow::PAYABLE)
            ->orderBy('invoice.due_date')
            ->orderBy('invoice.id')
            ->findAll($limit);
    }

    /**
     * Count and open balance of unpaid invoices, optionally only those past due.
     *
     * @param bool        $pastDueOnly Only invoices whose due date has passed.
     * @param string|null $scope       Extra SQL condition (App\Services\AccessScope::invoiceSql()), or null for all.
     *
     * @return array{count: int, balance: string}
     */
    public function unpaidSummary(bool $pastDueOnly = false, ?string $scope = null): array
    {
        $builder = $this->db->table('invoice')
            ->select('COUNT(*) AS cnt, COALESCE(SUM(invoice.grand_total - invoice.credit_total - invoice.amount_paid - invoice.withheld_total + invoice.refunded_total), 0) AS balance')
            ->join('invoice_status', 'invoice_status.id = invoice.status_id')
            ->where('invoice.deleted_at', null)
            ->whereIn('invoice_status.code', InvoiceWorkflow::PAYABLE);

        if ($pastDueOnly) {
            $builder->where('invoice.due_date <', date('Y-m-d'));
        }

        if ($scope !== null) {
            $builder->where($scope, null, false);
        }

        $row = $builder->get()->getRowArray();

        return ['count' => (int) ($row['cnt'] ?? 0), 'balance' => (string) ($row['balance'] ?? '0')];
    }
}
