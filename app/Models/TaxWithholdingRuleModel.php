<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Data-driven tax withholding/collection rules applied by App\Services\WithholdingCalculator.
 * UNIQUE (code, valid_from): a regulation change is a new row (close the old one with valid_to).
 */
class TaxWithholdingRuleModel extends LookupModel
{
    protected $table         = 'tax_withholding_rule';
    protected $allowedFields = [
        'code', 'name', 'tax_kind', 'base', 'item_type', 'customer_status', 'rate', 'umkm_rate',
        'threshold', 'threshold_basis', 'skip_for_bos', 'requires_pkp', 'regulation', 'valid_from', 'valid_to', 'is_active',
    ];
    protected array $nullable    = ['umkm_rate', 'regulation', 'valid_to'];
    protected array $fieldLabels = [
        'rate'         => 'Rate (%)',
        'umkm_rate'    => 'UMKM final rate (%)',
        'skip_for_bos' => 'Skip for BOS funds',
        'requires_pkp' => 'Only if company is PKP',
    ];
    protected array $referencedBy = ['invoice_withholding' => 'rule_id'];

    protected $validationRules = [
        'code'            => 'required|max_length[32]|regex_match[/^[A-Z][A-Z0-9_]*$/]',
        'name'            => 'required|max_length[128]',
        'tax_kind'        => 'required|in_list[PPN,PPH22,PPH23]',
        'base'            => 'required|in_list[DPP,PPN]',
        'item_type'       => 'required|in_list[ALL,GOODS,SERVICE]',
        'customer_status' => 'required|in_list[ALL,PUBLIC,PRIVATE]',
        'rate'            => 'required|decimal|greater_than_equal_to[0]|less_than_equal_to[100]',
        'umkm_rate'       => 'permit_empty|decimal|greater_than_equal_to[0]|less_than_equal_to[100]',
        'threshold'       => 'permit_empty|decimal|greater_than_equal_to[0]',
        'threshold_basis' => 'required|in_list[DPP,GROSS]',
        'skip_for_bos'    => 'permit_empty|in_list[0,1]',
        'requires_pkp'    => 'permit_empty|in_list[0,1]',
        'regulation'      => 'permit_empty|max_length[255]',
        'valid_from'      => 'required|valid_date[Y-m-d]',
        'valid_to'        => 'permit_empty|valid_date[Y-m-d]',
        'is_active'       => 'permit_empty|in_list[0,1]',
    ];

    protected $validationMessages = [
        'code' => ['regex_match' => 'Use UPPER_SNAKE_CASE (A-Z, 0-9, _), starting with a letter.'],
    ];

    /**
     * Composite uniqueness (code, valid_from) and valid_to >= valid_from.
     *
     * @param array<string, mixed> $data Submitted values.
     * @param int|null             $id   Row being edited.
     *
     * @return array<string, string>
     */
    public function extraErrors(array $data, ?int $id): array
    {
        return $this->periodErrors($data, $id);
    }

    /**
     * Active rules valid on a date.
     *
     * @param string $date Y-m-d.
     *
     * @return list<array<string, mixed>>
     */
    public function validOn(string $date): array
    {
        return $this->where('is_active', 1)
            ->where('valid_from <=', $date)
            ->groupStart()->where('valid_to', null)->orWhere('valid_to >=', $date)->groupEnd()
            ->orderBy('tax_kind')
            ->findAll();
    }
}
