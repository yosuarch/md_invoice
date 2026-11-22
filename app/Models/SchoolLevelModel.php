<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Lookup: school levels: PAUD, TK, SD, MI, SMP, MTs, SMA, MA, SMK, SLB, ...
 */
class SchoolLevelModel extends LookupModel
{
    protected $table              = 'school_level';
    protected $allowedFields      = ['code', 'name', 'sort_order'];
    protected array $referencedBy = ['customer_list' => 'school_level_id'];

    protected $validationRules = [
        'code'       => 'required|max_length[16]|regex_match[/^[A-Z][A-Z0-9_]*$/]|is_unique[school_level.code,id,{id}]',
        'name'       => 'required|max_length[64]',
        'sort_order' => 'permit_empty|is_natural|less_than_equal_to[255]',
    ];

    protected $validationMessages = [
        'code' => [
            'regex_match' => 'Use UPPER_SNAKE_CASE (A-Z, 0-9, _), starting with a letter.',
            'is_unique'   => 'This code already exists.',
        ],
    ];

    /**
     * Select options ordered by sort_order: id => name.
     *
     * @param string $labelColumn Ignored.
     *
     * @return array<int, string>
     */
    public function options(string $labelColumn = 'name'): array
    {
        return array_column($this->orderBy('sort_order')->findAll(), 'name', 'id');
    }
}
