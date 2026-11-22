<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Lookup (system, read-only in the UI): proposal statuses.
 */
class ClientProposalStatusModel extends LookupModel
{
    protected $table         = 'client_proposal_status';
    protected $allowedFields = ['code', 'cps_description', 'sort_order'];
    protected array $nullable = ['cps_description'];
    protected array $referencedBy = ['client_proposal_list' => 'status_id'];

    protected $validationRules = [
        'code' => 'required|max_length[32]|regex_match[/^[A-Z][A-Z0-9_]*$/]|is_unique[client_proposal_status.code,id,{id}]',
        'cps_description' => 'permit_empty|max_length[255]',
        'sort_order'      => 'required|is_natural',
    ];

    protected $validationMessages = [
        'code' => [
            'regex_match' => 'Use UPPER_SNAKE_CASE (A-Z, 0-9, _), starting with a letter.',
            'is_unique'   => 'This code already exists.',
        ],
    ];
}
