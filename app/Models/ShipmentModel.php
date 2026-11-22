<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Lookup: shipment types (PICK_AT_STORE, PRIVATE, THIRD_PARTY).
 */
class ShipmentModel extends LookupModel
{
    protected $table         = 'shipment';
    protected $allowedFields = ['code', 'name'];
    protected array $nullable = [];
    protected array $referencedBy = ['invoice' => 'shipment_id', 'delivery_note' => 'shipment_id'];

    protected $validationRules = [
        'code' => 'required|max_length[32]|regex_match[/^[A-Z][A-Z0-9_]*$/]|is_unique[shipment.code,id,{id}]',
        'name' => 'required|max_length[64]',
    ];

    protected $validationMessages = [
        'code' => [
            'regex_match' => 'Use UPPER_SNAKE_CASE (A-Z, 0-9, _), starting with a letter.',
            'is_unique'   => 'This code already exists.',
        ],
    ];
}
