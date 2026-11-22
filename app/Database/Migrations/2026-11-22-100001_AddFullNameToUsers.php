<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Extends Shield's users table with staff details.
 * (Email/password live in Shield's auth_identities; roles are Shield groups;
 * "inactive" = Shield ban; last login = users.last_active / auth_logins.)
 */
class AddFullNameToUsers extends Migration
{
    /**
     * @return void
     */
    public function up(): void
    {
        $this->forge->addColumn('users', [
            'full_name' => ['type' => 'VARCHAR', 'constraint' => 128, 'null' => true, 'after' => 'username'],
        ]);
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $this->forge->dropColumn('users', 'full_name');
    }
}
