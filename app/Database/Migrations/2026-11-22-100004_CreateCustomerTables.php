<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Support\BaseMigration;

/**
 * Customers with their contact persons and billing/shipping addresses.
 */
class CreateCustomerTables extends BaseMigration
{
    /**
     * @return void
     */
    public function up(): void
    {
        // customer_list
        $this->forge->addField($this->idField() + [
            'cl_id'                   => $this->varchar(16),
            'cl_organization_name'    => $this->varchar(200),
            'cl_tax_id'               => $this->varchar(32, true),
            'cl_email'                => $this->varchar(255, true),
            'cl_phone'                => $this->varchar(32, true),
            'default_payment_term_id' => $this->fk(true),
            'credit_limit'            => ['type' => 'DECIMAL', 'constraint' => '15,2', 'null' => true],
            'is_active'               => $this->flag(1),
        ] + $this->timestamps());
        $this->forge->addUniqueKey('cl_id');
        $this->forge->addKey('cl_organization_name');
        $this->forge->addForeignKey('default_payment_term_id', 'payment_terms', 'id');
        $this->createStandardTable('customer_list');

        // customer_contact_person
        $this->forge->addField($this->idField() + [
            'customer_id'      => $this->fk(),
            'ccp_first_name'   => $this->varchar(64),
            'ccp_last_name'    => $this->varchar(64, true),
            'ccp_phone_number' => $this->varchar(32),
            'ccp_email'        => $this->varchar(255, true),
            'ccp_position'     => $this->varchar(64, true),
            'is_primary'       => $this->flag(0),
        ] + $this->timestamps());
        $this->forge->addForeignKey('customer_id', 'customer_list', 'id');
        $this->createStandardTable('customer_contact_person');

        // customer_address
        $this->forge->addField($this->idField() + [
            'customer_id'  => $this->fk(),
            'address_type' => ['type' => 'ENUM', 'constraint' => ['BILLING', 'SHIPPING'], 'null' => false],
            'address_line' => ['type' => 'TEXT', 'null' => false],
            'city'         => $this->varchar(100, true),
            'province'     => $this->varchar(100, true),
            'postal_code'  => $this->varchar(10, true),
            'is_default'   => $this->flag(0),
        ] + $this->timestamps());
        $this->forge->addForeignKey('customer_id', 'customer_list', 'id');
        $this->createStandardTable('customer_address');
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $this->forge->dropTable('customer_address', true);
        $this->forge->dropTable('customer_contact_person', true);
        $this->forge->dropTable('customer_list', true);
    }
}
