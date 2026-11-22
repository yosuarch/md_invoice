<?php

declare(strict_types=1);

namespace App\Models;

/**
 * agreement: Surat Perintah Kerja (SPK) / agreement between the store and the customer, made from an approved
 * proposal (App\Services\AgreementService). One per proposal; number from document_sequence (SPK).
 * Signatories and clauses are copied when it is created, so the printed agreement does not change later.
 */
class AgreementModel extends BaseModel
{
    protected $table         = 'agreement';
    protected array $lowercase = ['client_signatory_name', 'client_signatory_position', 'store_signatory_name', 'store_signatory_position']; // stored lower case, shown with up()
    protected $allowedFields = [
        'agreement_number', 'proposal_id', 'agreement_date', 'completion_date', 'client_signatory_name', 'client_signatory_position',
        'client_signatory_nip', 'store_signatory_name', 'store_signatory_position', 'terms', 'created_by',
    ];
    protected array $nullable    = ['completion_date', 'client_signatory_position', 'client_signatory_nip', 'store_signatory_position', 'terms'];
    protected array $fieldLabels = [
        'agreement_date'            => 'Agreement date',
        'completion_date'           => 'Completion date',
        'client_signatory_name'     => 'Customer signatory',
        'client_signatory_position' => 'Customer signatory position',
        'client_signatory_nip'      => 'NIP',
        'store_signatory_name'      => 'Store signatory',
        'store_signatory_position'  => 'Store signatory position',
        'terms'                     => 'Clauses',
    ];

    protected $validationRules = [
        'agreement_date'            => 'required|valid_date[Y-m-d]',
        'completion_date'           => 'permit_empty|valid_date[Y-m-d]',
        'client_signatory_name'     => 'required|max_length[128]',
        'client_signatory_position' => 'permit_empty|max_length[64]',
        'client_signatory_nip'      => 'permit_empty|max_length[32]',
        'store_signatory_name'      => 'required|max_length[128]',
        'store_signatory_position'  => 'permit_empty|max_length[64]',
        'terms'                     => 'permit_empty|max_length[10000]',
    ];

    /**
     * Agreement of a proposal, or null.
     *
     * @param int $proposalId Proposal id.
     *
     * @return array<string, mixed>|null
     */
    public function forProposal(int $proposalId): ?array
    {
        return $this->where('proposal_id', $proposalId)->first();
    }
}
