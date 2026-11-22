<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Lookup: funding sources for school purchases (BOSP, BOS Madrasah, APBD, Komite, ...).
 * is_bos = 1 marks BOS funds, exempt from PPh 22 (see tax_withholding_rule.skip_for_bos).
 */
class FundingSourceModel extends LookupModel
{
    protected $table              = 'funding_source';
    protected $allowedFields      = ['code', 'name', 'is_bos', 'description', 'sort_order'];
    protected array $nullable     = ['description'];
    protected array $fieldLabels  = ['is_bos' => 'BOS funds'];
    protected array $referencedBy = ['client_proposal_list' => 'funding_source_id', 'invoice' => 'funding_source_id'];

    protected $validationRules = [
        'code'        => 'required|max_length[32]|regex_match[/^[A-Z][A-Z0-9_]*$/]|is_unique[funding_source.code,id,{id}]',
        'name'        => 'required|max_length[128]',
        'is_bos'      => 'permit_empty|in_list[0,1]',
        'description' => 'permit_empty|max_length[255]',
        'sort_order'  => 'permit_empty|is_natural|less_than_equal_to[255]',
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
