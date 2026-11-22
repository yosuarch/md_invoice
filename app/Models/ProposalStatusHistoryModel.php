<?php

declare(strict_types=1);

namespace App\Models;

/**
 * proposal_status_history: append-only log of proposal status changes (no updated_at / deleted_at).
 * It is itself a log, so no audit_log rows are written for it.
 */
class ProposalStatusHistoryModel extends BaseModel
{
    protected $table          = 'proposal_status_history';
    protected $allowedFields  = ['proposal_id', 'from_status_id', 'to_status_id', 'changed_by', 'note'];
    protected $useSoftDeletes = false;
    protected $updatedField   = '';
    protected bool $audited   = false;
    protected array $nullable = ['from_status_id', 'note'];

    /**
     * History of a proposal, newest first, with status codes and user names.
     *
     * @param int $proposalId Proposal id.
     *
     * @return list<array<string, mixed>>
     */
    public function forProposal(int $proposalId): array
    {
        return $this->select('proposal_status_history.*, f.code AS from_code, t.code AS to_code, users.username, users.full_name')
            ->join('client_proposal_status f', 'f.id = proposal_status_history.from_status_id', 'left')
            ->join('client_proposal_status t', 't.id = proposal_status_history.to_status_id')
            ->join('users', 'users.id = proposal_status_history.changed_by', 'left')
            ->where('proposal_id', $proposalId)
            ->orderBy('proposal_status_history.id', 'DESC')
            ->findAll();
    }
}
