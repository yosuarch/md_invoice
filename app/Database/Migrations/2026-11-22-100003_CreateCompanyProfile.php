<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Support\BaseMigration;

/**
 * company_profile: the business's own details printed on documents (single row).
 */
class CreateCompanyProfile extends BaseMigration
{
    /**
     * @return void
     */
    public function up(): void
    {
        $this->forge->addField($this->idField() + [
            'company_name'            => $this->varchar(150),
            'tax_id'                  => $this->varchar(32, true),
            'address'                 => ['type' => 'TEXT', 'null' => true],
            'phone'                   => $this->varchar(32, true),
            'email'                   => $this->varchar(255, true),
            'logo_path'               => $this->varchar(255, true),
            'default_bank_account_id' => $this->fk(true),
        ] + $this->timestamps());
        $this->forge->addForeignKey('default_bank_account_id', 'bank_account_list', 'id');
        $this->createStandardTable('company_profile');
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $this->forge->dropTable('company_profile', true);
    }
}
