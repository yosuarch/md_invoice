<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Support\BaseMigration;

/**
 * Lookup tables. Application logic refers to rows by `code`, never by id.
 * Data is loaded by App\Database\Seeds\LookupSeeder.
 */
class CreateLookupTables extends BaseMigration
{
    /**
     * @return void
     */
    public function up(): void
    {
        // payment_method
        $this->forge->addField($this->idField() + [
            'code' => $this->varchar(32),
            'name' => $this->varchar(64),
        ] + $this->timestamps());
        $this->forge->addUniqueKey('code');
        $this->createStandardTable('payment_method');

        // payment_terms
        $this->forge->addField($this->idField() + [
            'code'        => $this->varchar(32),
            'due_days'    => ['type' => 'SMALLINT', 'unsigned' => true, 'null' => false],
            'description' => $this->varchar(255, true),
        ] + $this->timestamps());
        $this->forge->addUniqueKey('code');
        $this->createStandardTable('payment_terms');

        // shipment
        $this->forge->addField($this->idField() + [
            'code' => $this->varchar(32),
            'name' => $this->varchar(64),
        ] + $this->timestamps());
        $this->forge->addUniqueKey('code');
        $this->createStandardTable('shipment');

        // bank_account_list (company's receiving accounts)
        $this->forge->addField($this->idField() + [
            'bank_name'           => $this->varchar(64),
            'bank_account_number' => $this->varchar(32),
            'bank_account_name'   => $this->varchar(128),
            'is_active'           => $this->flag(1),
        ] + $this->timestamps());
        $this->createStandardTable('bank_account_list');

        // unit_of_measurement
        $this->forge->addField($this->idField() + [
            'code' => $this->varchar(16),
            'name' => $this->varchar(64),
        ] + $this->timestamps());
        $this->forge->addUniqueKey('code');
        $this->createStandardTable('unit_of_measurement');

        // tax_rate: UNIQUE (code, valid_from) so a rate can change over time (e.g. PPN 11% -> 12%).
        $this->forge->addField($this->idField() + [
            'code'       => $this->varchar(16),
            'rate'       => $this->rate(),
            'valid_from' => ['type' => 'DATE', 'null' => false],
            'valid_to'   => ['type' => 'DATE', 'null' => true],
        ] + $this->timestamps());
        $this->forge->addUniqueKey(['code', 'valid_from']);
        $this->createStandardTable('tax_rate');

        // client_proposal_status
        $this->forge->addField($this->idField() + [
            'code'            => $this->varchar(32),
            'cps_description' => $this->varchar(255, true),
            'sort_order'      => ['type' => 'TINYINT', 'unsigned' => true, 'null' => false],
        ] + $this->timestamps());
        $this->forge->addUniqueKey('code');
        $this->createStandardTable('client_proposal_status');

        // invoice_status
        $this->forge->addField($this->idField() + [
            'code'        => $this->varchar(32),
            'description' => $this->varchar(255, true),
            'sort_order'  => ['type' => 'TINYINT', 'unsigned' => true, 'null' => false],
        ] + $this->timestamps());
        $this->forge->addUniqueKey('code');
        $this->createStandardTable('invoice_status');
    }

    /**
     * @return void
     */
    public function down(): void
    {
        foreach ([
            'invoice_status', 'client_proposal_status', 'tax_rate', 'unit_of_measurement',
            'bank_account_list', 'shipment', 'payment_terms', 'payment_method',
        ] as $table) {
            $this->forge->dropTable($table, true);
        }
    }
}
