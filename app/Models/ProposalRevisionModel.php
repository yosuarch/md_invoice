<?php

declare(strict_types=1);

namespace App\Models;

/**
 * proposal_revision: append-only snapshots of the versions of a proposal sent to the customer (negotiation history,
 * App\Services\ProposalRevisions). Items are stored as JSON with the values that were offered. It is itself a log,
 * so no audit_log rows are written for it.
 */
class ProposalRevisionModel extends BaseModel
{
    protected $table          = 'proposal_revision';
    protected $allowedFields  = [
        'proposal_id', 'revision_no', 'status_code', 'items', 'subtotal', 'discount_total', 'tax_total', 'grand_total',
        'valid_until', 'note', 'created_by',
    ];
    protected $useSoftDeletes = false;
    protected $updatedField   = '';
    protected bool $audited   = false;
    protected array $nullable = ['valid_until', 'note', 'created_by'];

    /**
     * Revisions of a proposal, oldest first, with the user's name and decoded items.
     *
     * @param int $proposalId Proposal id.
     *
     * @return list<array<string, mixed>>
     */
    public function forProposal(int $proposalId): array
    {
        $rows = $this->select('proposal_revision.*, users.username, users.full_name')
            ->join('users', 'users.id = proposal_revision.created_by', 'left')
            ->where('proposal_id', $proposalId)
            ->orderBy('revision_no')
            ->findAll();

        foreach ($rows as &$row) {
            $row['items'] = json_decode((string) $row['items'], true) ?: [];
        }

        return $rows;
    }
}
