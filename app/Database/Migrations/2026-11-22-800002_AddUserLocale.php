<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Support\BaseMigration;

/**
 * users.locale: screen language chosen by the user ('id' / 'en'); NULL = Config\App::$defaultLocale.
 * Printed documents are always Indonesian.
 */
class AddUserLocale extends BaseMigration
{
    /**
     * @return void
     */
    public function up(): void
    {
        $this->forge->addColumn('users', [
            'locale' => $this->varchar(5, true) + ['after' => 'full_name'],
        ]);
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $this->forge->dropColumn('users', 'locale');
    }
}
