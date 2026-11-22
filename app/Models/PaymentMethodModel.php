<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Lookup: payment methods (TRANSFER, CASH).
 */
class PaymentMethodModel extends LookupModel
{
    protected $table         = 'payment_method';
    protected $allowedFields = ['code', 'name'];
    protected array $nullable = [];
    protected array $referencedBy = ['invoice' => 'payment_method_id', 'payment' => 'payment_method_id'];

    protected $validationRules = [
        'code' => 'required|max_length[32]|regex_match[/^[A-Z][A-Z0-9_]*$/]|is_unique[payment_method.code,id,{id}]',
        'name' => 'required|max_length[64]',
    ];

    protected $validationMessages = [
        'code' => [
            'regex_match' => 'Use UPPER_SNAKE_CASE (A-Z, 0-9, _), starting with a letter.',
            'is_unique'   => 'This code already exists.',
        ],
    ];
}
