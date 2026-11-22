<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Lookup (system, read-only in the UI): invoice statuses.
 */
class InvoiceStatusModel extends LookupModel
{
    protected $table         = 'invoice_status';
    protected $allowedFields = ['code', 'description', 'sort_order'];
    protected array $nullable = ['description'];
    protected array $referencedBy = ['invoice' => 'status_id'];

    protected $validationRules = [
        'code' => 'required|max_length[32]|regex_match[/^[A-Z][A-Z0-9_]*$/]|is_unique[invoice_status.code,id,{id}]',
        'description' => 'permit_empty|max_length[255]',
        'sort_order'  => 'required|is_natural',
    ];

    protected $validationMessages = [
        'code' => [
            'regex_match' => 'Use UPPER_SNAKE_CASE (A-Z, 0-9, _), starting with a letter.',
            'is_unique'   => 'This code already exists.',
        ],
    ];
}
