<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Support\BaseMigration;

/**
 * customer_address.district: kecamatan (level 3 of the Kepmendagri regions, chosen from the cascading dropdown
 * province → regency/city → district). Stored as the name in lower case, like city and province.
 * The district list itself is loaded by RegionSeeder (php spark db:seed RegionSeeder).
 */
class AddAddressDistrict extends BaseMigration
{
    /**
     * @return void
     */
    public function up(): void
    {
        $this->forge->addColumn('customer_address', [
            'district' => $this->varchar(100, true) + ['after' => 'address_line'],
        ]);
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $this->forge->dropColumn('customer_address', 'district');
    }
}
