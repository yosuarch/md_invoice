<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Lookup: payment terms; invoice due_date = issue_date + due_days.
 */
class PaymentTermModel extends LookupModel
{
    protected $table         = 'payment_terms';
    protected $allowedFields = ['code', 'due_days', 'description'];
    protected array $nullable = ['description'];
    protected array $referencedBy = ['customer_list' => 'default_payment_term_id', 'invoice' => 'payment_term_id'];

    protected $validationRules = [
        'code' => 'required|max_length[32]|regex_match[/^[A-Z][A-Z0-9_]*$/]|is_unique[payment_terms.code,id,{id}]',
        'due_days'    => 'required|is_natural|less_than_equal_to[365]',
        'description' => 'permit_empty|max_length[255]',
    ];

    protected $validationMessages = [
        'code' => [
            'regex_match' => 'Use UPPER_SNAKE_CASE (A-Z, 0-9, _), starting with a letter.',
            'is_unique'   => 'This code already exists.',
        ],
    ];
}
