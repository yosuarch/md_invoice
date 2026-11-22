<?php

declare(strict_types=1);

namespace App\Models;

/**
 * client_proposal_list: proposals (quotations). Created and changed through App\Services\ProposalService
 * (number from document_sequence QUO, totals computed in PHP, status history).
 */
class ProposalModel extends BaseModel
{
    protected $table         = 'client_proposal_list';
    protected array $lowercase = ['cpl_proposal_title']; // stored lower case, shown with up()
    protected $allowedFields = [
        'cpl_id', 'customer_id', 'contact_person_id', 'cpl_proposal_title', 'status_id', 'funding_source_id', 'sales_rep_id',
        'payment_term_id', 'shipment_id', 'delivery_date',
        'status_updated_by', 'status_updated_at', 'valid_until', 'subtotal', 'discount_total', 'tax_total', 'grand_total',
        'cpl_note', 'created_by',
    ];
    protected array $nullable = ['contact_person_id', 'funding_source_id', 'sales_rep_id', 'payment_term_id', 'shipment_id', 'delivery_date', 'valid_until', 'cpl_note'];
    protected array $fieldLabels = [
        'cpl_id'             => 'Proposal number',
        'cpl_proposal_title' => 'Title',
        'contact_person_id'  => 'Contact person',
        'funding_source_id'  => 'Funding source',
        'sales_rep_id'       => 'Sales rep',
        'payment_term_id'    => 'Payment terms',
        'shipment_id'        => 'Shipment method',
        'delivery_date'      => 'Planned delivery date',
        'cpl_note'           => 'Note',
    ];

    protected array $referencedBy = ['invoice' => 'proposal_id'];

    protected $validationRules = [
        'cpl_id'             => 'required|max_length[32]|is_unique[client_proposal_list.cpl_id,id,{id}]',
        'customer_id'        => 'required|is_natural_no_zero|is_not_unique[customer_list.id]',
        'contact_person_id'  => 'permit_empty|is_natural_no_zero|is_not_unique[customer_contact_person.id]',
        'cpl_proposal_title' => 'required|max_length[200]',
        'status_id'          => 'required|is_natural_no_zero|is_not_unique[client_proposal_status.id]',
        'funding_source_id'  => 'permit_empty|is_natural_no_zero|is_not_unique[funding_source.id]',
        'sales_rep_id'       => 'permit_empty|is_natural_no_zero|is_not_unique[sales_representative.id]',
        'valid_until'        => 'permit_empty|valid_date[Y-m-d]',
        'payment_term_id'    => 'permit_empty|is_natural_no_zero|is_not_unique[payment_terms.id]',
        'shipment_id'        => 'permit_empty|is_natural_no_zero|is_not_unique[shipment.id]',
        'delivery_date'      => 'permit_empty|valid_date[Y-m-d]',
        'cpl_note'           => 'permit_empty|max_length[5000]',
    ];

    protected $validationMessages = [
        'customer_id' => ['required' => 'Select a customer.', 'is_not_unique' => 'Select a valid customer.'],
    ];

    /**
     * List/detail query joined with customer, status, sales rep and funding source.
     *
     * @return $this
     */
    public function withDetails(): self
    {
        return $this->select(
            'client_proposal_list.*, customer_list.cl_organization_name AS customer_name, customer_list.npsn, customer_list.school_status,'
            . ' client_proposal_status.code AS status_code, sales_representative.rep_name, funding_source.name AS funding_source_name,'
            . ' funding_source.code AS funding_source_code, payment_terms.code AS payment_term_code, payment_terms.due_days, shipment.name AS shipment_name',
        )
            ->join('customer_list', 'customer_list.id = client_proposal_list.customer_id')
            ->join('client_proposal_status', 'client_proposal_status.id = client_proposal_list.status_id')
            ->join('sales_representative', 'sales_representative.id = client_proposal_list.sales_rep_id', 'left')
            ->join('funding_source', 'funding_source.id = client_proposal_list.funding_source_id', 'left')
            ->join('payment_terms', 'payment_terms.id = client_proposal_list.payment_term_id', 'left')
            ->join('shipment', 'shipment.id = client_proposal_list.shipment_id', 'left');
    }

    /**
     * List query with search and filters, newest first.
     *
     * @param array{q?: string, status?: string, rep?: string, customer?: string} $filters
     *        status: a status code or 'open'; rep: a sales rep id or 'mine'.
     * @param int|null $myRepId Sales rep linked to the current user (for rep = 'mine').
     *
     * @return $this
     */
    public function search(array $filters, ?int $myRepId = null): self
    {
        $this->withDetails()->orderBy('client_proposal_list.id', 'DESC');

        $q = trim((string) ($filters['q'] ?? ''));

        if ($q !== '') {
            $this->groupStart()
                ->like('client_proposal_list.cpl_id', $q)
                ->orLike('client_proposal_list.cpl_proposal_title', $q)
                ->orLike('customer_list.cl_organization_name', $q)
                ->orLike('customer_list.npsn', $q)
                ->groupEnd();
        }

        $status = (string) ($filters['status'] ?? '');

        if ($status === 'to_invoice') {
            // Approved, but no invoice yet (void invoices do not count).
            $this->where('client_proposal_status.code', \App\Services\ProposalWorkflow::STORE_APPROVE)
                ->where(
                    'NOT EXISTS (SELECT 1 FROM invoice i JOIN invoice_status s ON s.id = i.status_id'
                    . " WHERE i.proposal_id = client_proposal_list.id AND i.deleted_at IS NULL AND s.code <> 'VOID')",
                    null,
                    false,
                );
        } elseif ($status === 'open') {
            $this->whereIn('client_proposal_status.code', \App\Services\ProposalWorkflow::OPEN);
        } elseif ($status !== '') {
            $this->where('client_proposal_status.code', $status);
        }

        $rep = (string) ($filters['rep'] ?? '');

        if ($rep !== '') {
            $this->where('client_proposal_list.sales_rep_id', $rep === 'mine' ? ($myRepId ?? 0) : (int) $rep);
        }

        if (($filters['customer'] ?? '') !== '') {
            $this->where('client_proposal_list.customer_id', (int) $filters['customer']);
        }

        return $this;
    }

    /**
     * Whether an invoice that is not void (or a draft) exists for this proposal.
     *
     * @param int $id Proposal id.
     *
     * @return bool
     */
    public function hasInvoice(int $id): bool
    {
        return (new InvoiceModel())->activeForProposal($id) !== [];
    }
}
