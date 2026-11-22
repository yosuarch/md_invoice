<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Entry point: php spark db:seed DatabaseSeeder
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Run all seeders in dependency order.
     *
     * @return void
     */
    public function run(): void
    {
        $this->call(LookupSeeder::class);
        $this->call(RegionSeeder::class);
        $this->call(NumberingSeeder::class);
    }
}
