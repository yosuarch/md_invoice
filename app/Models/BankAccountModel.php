<?php

declare(strict_types=1);

namespace App\Models;

/**
 * The company's receiving bank accounts (printed on invoices).
 */
class BankAccountModel extends BaseModel
{
    protected $table         = 'bank_account_list';
    protected array $fieldLabels = ['bank_account_name' => 'Account holder name'];
    protected array $lowercase = ['bank_name', 'bank_account_name']; // stored lower case, shown with up()
    protected $allowedFields = ['bank_name', 'bank_account_number', 'bank_account_name', 'is_active'];

    protected array $referencedBy = [
        'company_profile' => 'default_bank_account_id',
        'invoice'         => 'bank_account_id',
        'payment'         => 'bank_account_id',
    ];

    protected $validationRules = [
        'bank_name'           => 'required|max_length[64]',
        'bank_account_number' => 'required|max_length[32]|regex_match[/^[0-9][0-9 .-]*$/]',
        'bank_account_name'   => 'required|max_length[128]',
        'is_active'           => 'permit_empty|in_list[0,1]',
    ];

    protected $validationMessages = [
        'bank_account_number' => ['regex_match' => 'Use digits only (spaces, dots and dashes allowed).'],
    ];

    /**
     * Active accounts as select options, e.g. "Mandiri - 123-456 (PT Example)".
     *
     * @param string $labelColumn Ignored; labels are composed.
     *
     * @return array<int, string>
     */
    public function options(string $labelColumn = 'bank_name'): array
    {
        $options = [];

        foreach ($this->where('is_active', 1)->orderBy('bank_name')->findAll() as $row) {
            $options[(int) $row['id']] = sprintf('%s - %s (%s)', $row['bank_name'], $row['bank_account_number'], $row['bank_account_name']);
        }

        return $options;
    }
}
