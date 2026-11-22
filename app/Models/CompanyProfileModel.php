<?php

declare(strict_types=1);

namespace App\Models;

/**
 * The company's own details (single row), printed on all documents.
 */
class CompanyProfileModel extends BaseModel
{
    protected $table         = 'company_profile';
    protected $allowedFields = ['company_name', 'tax_id', 'is_pkp', 'umkm_certificate_number', 'umkm_certificate_valid_until', 'address', 'phone', 'email', 'logo_path', 'default_bank_account_id', 'website', 'document_city', 'signatory_name', 'signatory_position', 'invoice_terms', 'agreement_terms', 'calc_tax_mode', 'calc_rounding', 'calc_amount_decimals', 'calc_unit_decimals'];
    protected array $nullable = ['website', 'document_city', 'signatory_name', 'signatory_position', 'invoice_terms', 'agreement_terms', 'tax_id', 'umkm_certificate_number', 'umkm_certificate_valid_until', 'address', 'phone', 'email', 'logo_path', 'default_bank_account_id'];
    protected array $fieldLabels = ['tax_id' => 'NPWP', 'is_pkp' => 'PKP', 'umkm_certificate_number' => 'SK PP 55 number', 'umkm_certificate_valid_until' => 'SK PP 55 valid until', 'default_bank_account_id' => 'Default bank account'];

    protected $validationRules = [
        'company_name'            => 'required|max_length[150]',
        'tax_id'                  => 'permit_empty|max_length[32]|npwp',
        'is_pkp'                  => 'permit_empty|in_list[0,1]',
        'umkm_certificate_number' => 'permit_empty|max_length[64]',
        'umkm_certificate_valid_until' => 'permit_empty|valid_date[Y-m-d]',
        'address'                 => 'permit_empty|max_length[1000]',
        'phone'                   => 'permit_empty|max_length[32]|phone',
        'email'                   => 'permit_empty|max_length[255]|valid_email',
        'default_bank_account_id' => 'permit_empty|is_natural_no_zero|is_not_unique[bank_account_list.id]',
        'website'                 => 'permit_empty|max_length[255]',
        'document_city'           => 'permit_empty|max_length[100]',
        'signatory_name'          => 'permit_empty|max_length[128]',
        'signatory_position'      => 'permit_empty|max_length[64]',
        'invoice_terms'           => 'permit_empty|max_length[5000]',
        'agreement_terms'         => 'permit_empty|max_length[10000]',
        'calc_tax_mode'           => 'permit_empty|in_list[LINE,TOTAL]',
        'calc_rounding'           => 'permit_empty|in_list[HALF_UP,DOWN]',
        'calc_amount_decimals'    => 'permit_empty|in_list[0,2]',
        'calc_unit_decimals'      => 'permit_empty|in_list[2,3,4,5,6]',
    ];

    /**
     * The single profile row, or null before it is first saved.
     *
     * @return array<string, mixed>|null
     */
    public function getProfile(): ?array
    {
        return $this->orderBy('id')->first();
    }

    /**
     * Create the profile row or update the existing one.
     *
     * @param array<string, mixed> $data Profile values.
     *
     * @return bool False on validation failure (see errors()).
     */
    public function saveProfile(array $data): bool
    {
        $current = $this->getProfile();

        if ($current === null) {
            return $this->insert($data, false);
        }

        return $this->update($current['id'], $data);
    }

    /**
     * Whether the company holds an SK PP 55/2022 (UMKM final 0.5%) valid on a date.
     *
     * @param array<string, mixed>|null $profile Profile row.
     * @param string                    $date    Y-m-d.
     *
     * @return bool
     */
    public static function hasValidUmkmCertificate(?array $profile, string $date): bool
    {
        if ($profile === null || empty($profile['umkm_certificate_number'])) {
            return false;
        }

        $until = $profile['umkm_certificate_valid_until'] ?? null;

        return $until === null || $until >= $date;
    }
}
