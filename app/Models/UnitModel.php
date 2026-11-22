<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Lookup: units of measurement (PCS, BOX, KG, SET).
 */
class UnitModel extends LookupModel
{
    protected $table         = 'unit_of_measurement';
    protected $allowedFields = ['code', 'name'];
    protected array $nullable = [];
    protected array $referencedBy = ['product_list' => 'unit_id'];

    protected $validationRules = [
        'code' => 'required|max_length[16]|regex_match[/^[A-Z][A-Z0-9_]*$/]|is_unique[unit_of_measurement.code,id,{id}]',
        'name' => 'required|max_length[64]',
    ];

    protected $validationMessages = [
        'code' => [
            'regex_match' => 'Use UPPER_SNAKE_CASE (A-Z, 0-9, _), starting with a letter.',
            'is_unique'   => 'This code already exists.',
        ],
    ];
}
