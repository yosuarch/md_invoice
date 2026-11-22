<?php

declare(strict_types=1);

namespace App\Models;

/**
 * customer_address. One default address per customer and type (enforced by App\Services\CustomerService).
 */
class CustomerAddressModel extends BaseModel
{
    public const TYPES = ['BILLING' => 'Billing', 'SHIPPING' => 'Shipping'];

    protected $table         = 'customer_address';
    protected array $lowercase = ['address_line', 'district', 'city', 'province']; // stored lower case, shown with up()
    protected $allowedFields = [
        'customer_id', 'address_type', 'address_line', 'district', 'city', 'province', 'postal_code', 'is_default',
    ];
    protected array $nullable = ['district', 'city', 'province', 'postal_code'];

    protected $validationRules = [
        'customer_id'  => 'required|is_natural_no_zero|is_not_unique[customer_list.id]',
        'address_type' => 'required|in_list[BILLING,SHIPPING]',
        'address_line' => 'required|max_length[1000]',
        'district'     => 'permit_empty|max_length[100]',
        'city'         => 'permit_empty|max_length[100]',
        'province'     => 'permit_empty|max_length[100]',
        'postal_code'  => 'permit_empty|regex_match[/^[0-9]{5}$/]',
        'is_default'   => 'permit_empty|in_list[0,1]',
    ];

    protected $validationMessages = [
        'postal_code' => ['regex_match' => 'Postal code must be 5 digits.'],
    ];

    /**
     * Addresses of a customer, grouped by type with the default first.
     *
     * @param int $customerId Customer id.
     *
     * @return list<array<string, mixed>>
     */
    public function forCustomer(int $customerId): array
    {
        return $this->where('customer_id', $customerId)
            ->orderBy('address_type')
            ->orderBy('is_default', 'DESC')
            ->orderBy('id')
            ->findAll();
    }

    /**
     * Single-line address for documents, e.g. "jl. merdeka 1, kec. sumur bandung, kota bandung, jawa barat 40111" (shown with up()).
     *
     * @param array<string, mixed> $address Address row.
     *
     * @return string
     */
    public static function format(array $address): string
    {
        $cityLine = trim(implode(' ', array_filter([$address['province'] ?? null, $address['postal_code'] ?? null])));

        return implode(', ', array_filter([
            trim((string) $address['address_line']),
            ! empty($address['district']) ? 'kec. ' . $address['district'] : null,
            $address['city'] ?? null,
            $cityLine !== '' ? $cityLine : null,
        ]));
    }
}
