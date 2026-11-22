<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Lookup: tax rates with validity periods. UNIQUE (code, valid_from): a rate change is a new row.
 */
class TaxRateModel extends LookupModel
{
    protected $table              = 'tax_rate';
    protected $allowedFields      = ['code', 'rate', 'valid_from', 'valid_to'];
    protected array $nullable     = ['valid_to'];
    protected array $fieldLabels = ['rate' => 'Rate (%)'];
    protected array $referencedBy = ['product_list' => 'tax_rate_id'];

    protected $validationRules = [
        'code'       => 'required|max_length[16]|regex_match[/^[A-Z][A-Z0-9_]*$/]',
        'rate'       => 'required|decimal|greater_than_equal_to[0]|less_than_equal_to[100]',
        'valid_from' => 'required|valid_date[Y-m-d]',
        'valid_to'   => 'permit_empty|valid_date[Y-m-d]',
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
     * Select options with readable labels, e.g. "PPN 11.00% (from 01/04/2022)".
     *
     * @param string $labelColumn Ignored; labels are composed.
     *
     * @return array<int, string>
     */
    public function options(string $labelColumn = 'code'): array
    {
        $options = [];

        foreach ($this->orderBy('code')->orderBy('valid_from', 'DESC')->findAll() as $row) {
            $label = sprintf('%s %s%% (from %s', $row['code'], $row['rate'], date('d/m/Y', strtotime($row['valid_from'])));
            $label .= $row['valid_to'] !== null ? ' to ' . date('d/m/Y', strtotime($row['valid_to'])) . ')' : ')';
            $options[(int) $row['id']] = $label;
        }

        return $options;
    }

    /**
     * Tax codes valid on a date with their rate (one row per code, the latest valid row), for the per-line tax
     * select on document forms.
     *
     * @param string|null $date Y-m-d (default today).
     *
     * @return array<string, string> code => rate ("11.00")
     */
    public function validOn(?string $date = null): array
    {
        $date ??= date('Y-m-d');
        $rows = $this->where('valid_from <=', $date)
            ->groupStart()->where('valid_to', null)->orWhere('valid_to >=', $date)->groupEnd()
            ->orderBy('code')->orderBy('valid_from', 'DESC')->findAll();
        $result = [];

        foreach ($rows as $row) {
            $result[$row['code']] ??= (string) $row['rate'];
        }

        return $result;
    }

    /**
     * The row for $code valid on $date (default today), or null.
     *
     * @param string      $code Tax code, e.g. 'PPN'.
     * @param string|null $date Y-m-d.
     *
     * @return array<string, mixed>|null
     */
    public function currentFor(string $code, ?string $date = null): ?array
    {
        $date ??= date('Y-m-d');

        return $this->where('code', $code)
            ->where('valid_from <=', $date)
            ->groupStart()->where('valid_to', null)->orWhere('valid_to >=', $date)->groupEnd()
            ->orderBy('valid_from', 'DESC')
            ->first();
    }
}
