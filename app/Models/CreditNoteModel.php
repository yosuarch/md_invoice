<?php

declare(strict_types=1);

namespace App\Models;

/**
 * credit_note: correction of an issued invoice (App\Services\CreditNoteService). Never edited after creation.
 */
class CreditNoteModel extends BaseModel
{
    /**
     * Kinds of correction (credit_note.reason_type).
     *
     * @var array<string, string>
     */
    public const REASON_TYPES = [
        'RETURN'           => 'Goods returned',
        'PRICE_CORRECTION' => 'Price correction',
        'CANCEL_BY_CLIENT' => 'Cancelled by client',
        'CANCEL_BY_STORE'  => 'Cancelled by store',
        'OTHER'            => 'Other',
    ];

    protected $table         = 'credit_note';
    protected $allowedFields = ['credit_note_number', 'invoice_id', 'issue_date', 'reason_type', 'reason', 'subtotal', 'tax_total', 'shipping_amount', 'grand_total', 'created_by'];

    protected $validationRules = [
        'reason' => 'required|max_length[2000]',
    ];

    /**
     * Credit notes with invoice, customer and creator, newest first.
     *
     * @return $this
     */
    public function withDetails(): self
    {
        return $this->select(
            'credit_note.*, invoice.invoice_number, invoice.customer_id, customer_list.cl_organization_name AS customer_name,'
            . ' users.username, users.full_name',
        )
            ->join('invoice', 'invoice.id = credit_note.invoice_id')
            ->join('customer_list', 'customer_list.id = invoice.customer_id')
            ->join('users', 'users.id = credit_note.created_by', 'left')
            ->orderBy('credit_note.id', 'DESC');
    }

    /**
     * Credit note list with search (number, invoice number, customer).
     *
     * @param array{q?: string} $filters
     *
     * @return $this
     */
    public function search(array $filters): self
    {
        $this->withDetails();

        $q = trim((string) ($filters['q'] ?? ''));

        if ($q !== '') {
            $this->groupStart()
                ->like('credit_note.credit_note_number', $q)
                ->orLike('invoice.invoice_number', $q)
                ->orLike('customer_list.cl_organization_name', $q)
                ->groupEnd();
        }

        return $this;
    }
}
