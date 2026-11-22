<?php

declare(strict_types=1);

use App\Models\CustomerAddressModel;
use App\Services\CustomerService;
use App\Services\ValidationFailed;
use Tests\Support\AppTestCase;

/**
 * Address regions: province → regency/city → district (kecamatan), matched case-insensitively (the dropdowns post
 * upper-case names, the region table has mixed case, addresses store lower case).
 *
 * @internal
 */
final class AddressRegionsTest extends AppTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\App\Database\Seeds\RegionSeeder::class);
    }

    public function testUpperCaseNamesFromTheDropdownsAreAccepted(): void
    {
        $customer = $this->createCustomer();
        $id       = (new CustomerService())->saveAddress($customer, null, [
            'address_type' => 'SHIPPING', 'address_line' => 'Desa Wangurer', 'province' => 'SULAWESI UTARA',
            'city' => 'KABUPATEN MINAHASA UTARA', 'district' => 'LIKUPANG SELATAN', 'postal_code' => '95375', 'is_default' => '1',
        ]);
        $address = (new CustomerAddressModel())->find($id);

        $this->assertSame('likupang selatan', $address['district']);
        $this->assertSame('desa wangurer, kec. likupang selatan, kabupaten minahasa utara, sulawesi utara 95375', CustomerAddressModel::format($address));
    }

    public function testDistrictMustBelongToTheRegency(): void
    {
        try {
            (new CustomerService())->saveAddress($this->createCustomer(), null, [
                'address_type' => 'BILLING', 'address_line' => 'X', 'province' => 'Sulawesi Utara',
                'city' => 'Kabupaten Minahasa Utara', 'district' => 'Wenang', 'is_default' => '1',
            ]);
            $this->fail('District of another regency accepted');
        } catch (ValidationFailed $e) {
            $this->assertArrayHasKey('district', $e->errors);
        }
    }
}
