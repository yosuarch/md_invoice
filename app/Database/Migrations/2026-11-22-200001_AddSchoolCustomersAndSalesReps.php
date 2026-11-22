<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Support\BaseMigration;

/**
 * School procurement context:
 * - customer code becomes a UUIDv7 (CHAR(36)),
 * - school data on customers (NPSN, school level, public/private status),
 * - sales representatives (no login required) linked many-to-many to customers.
 */
class AddSchoolCustomersAndSalesReps extends BaseMigration
{
    /**
     * @return void
     */
    public function up(): void
    {
        // school_level lookup
        $this->forge->addField($this->idField() + [
            'code'       => $this->varchar(16),
            'name'       => $this->varchar(64),
            'sort_order' => ['type' => 'TINYINT', 'unsigned' => true, 'null' => false, 'default' => 0],
        ] + $this->timestamps());
        $this->forge->addUniqueKey('code');
        $this->createStandardTable('school_level');

        // customer_list: UUID code + school fields
        $this->forge->modifyColumn('customer_list', [
            'cl_id' => ['name' => 'cl_id', 'type' => 'CHAR', 'constraint' => 36, 'null' => false],
        ]);
        $this->forge->addColumn('customer_list', [
            'npsn'            => ['type' => 'CHAR', 'constraint' => 8, 'null' => true, 'after' => 'cl_organization_name'],
            'school_level_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'npsn'],
            'school_status'   => ['type' => 'ENUM', 'constraint' => ['PUBLIC', 'PRIVATE'], 'null' => true, 'after' => 'school_level_id'],
        ]);
        $this->db->query('ALTER TABLE `customer_list` ADD UNIQUE KEY `customer_list_npsn` (`npsn`)');
        $this->db->query('ALTER TABLE `customer_list` ADD CONSTRAINT `customer_list_school_level_id_foreign` FOREIGN KEY (`school_level_id`) REFERENCES `school_level` (`id`)');

        // sales_representative (login account optional)
        $this->forge->addField($this->idField() + [
            'rep_name'  => $this->varchar(128),
            'rep_phone' => $this->varchar(32, true),
            'rep_email' => $this->varchar(255, true),
            'user_id'   => $this->fk(true),
            'is_active' => $this->flag(1),
        ] + $this->timestamps());
        $this->forge->addUniqueKey('user_id');
        $this->forge->addForeignKey('user_id', 'users', 'id');
        $this->createStandardTable('sales_representative');

        // customer_sales_rep (pivot; hard delete, no soft delete)
        $this->forge->addField($this->idField() + [
            'customer_id'  => $this->fk(),
            'sales_rep_id' => $this->fk(),
        ] + $this->timestamps(false));
        $this->forge->addUniqueKey(['customer_id', 'sales_rep_id']);
        $this->forge->addForeignKey('customer_id', 'customer_list', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('sales_rep_id', 'sales_representative', 'id');
        $this->createStandardTable('customer_sales_rep');
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $this->forge->dropTable('customer_sales_rep', true);
        $this->forge->dropTable('sales_representative', true);
        $this->db->query('ALTER TABLE `customer_list` DROP FOREIGN KEY `customer_list_school_level_id_foreign`');
        $this->db->query('ALTER TABLE `customer_list` DROP INDEX `customer_list_npsn`');
        $this->forge->dropColumn('customer_list', ['npsn', 'school_level_id', 'school_status']);
        $this->forge->modifyColumn('customer_list', [
            'cl_id' => ['name' => 'cl_id', 'type' => 'VARCHAR', 'constraint' => 16, 'null' => false],
        ]);
        $this->forge->dropTable('school_level', true);
    }
}
