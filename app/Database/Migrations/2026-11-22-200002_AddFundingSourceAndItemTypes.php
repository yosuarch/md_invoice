<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Support\BaseMigration;

/**
 * - funding_source lookup (BOSP, APBD, ...) chosen per proposal/invoice; drives tax withholding (BOS exemptions)
 * - goods vs service type on products and item lines (PPh 22 applies to goods, PPh 23 to services)
 * - sales representative on proposals and invoices
 */
class AddFundingSourceAndItemTypes extends BaseMigration
{
    private const ITEM_TABLES = ['client_proposal_item', 'invoice_item', 'credit_note_item'];

    /**
     * @return void
     */
    public function up(): void
    {
        $this->forge->addField($this->idField() + [
            'code'        => $this->varchar(32),
            'name'        => $this->varchar(128),
            'is_bos'      => $this->flag(0),
            'description' => $this->varchar(255, true),
            'sort_order'  => ['type' => 'TINYINT', 'unsigned' => true, 'null' => false, 'default' => 0],
        ] + $this->timestamps());
        $this->forge->addUniqueKey('code');
        $this->createStandardTable('funding_source');

        $this->forge->addColumn('product_list', [
            'pl_type' => ['type' => 'ENUM', 'constraint' => ['GOODS', 'SERVICE'], 'null' => false, 'default' => 'GOODS', 'after' => 'pl_alias'],
        ]);

        foreach (self::ITEM_TABLES as $table) {
            $this->forge->addColumn($table, [
                'item_type' => ['type' => 'ENUM', 'constraint' => ['GOODS', 'SERVICE'], 'null' => false, 'default' => 'GOODS', 'after' => 'unit_code'],
            ]);
        }

        foreach (['client_proposal_list' => 'status_id', 'invoice' => 'status_id'] as $table => $after) {
            $this->forge->addColumn($table, [
                'funding_source_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => $after],
                'sales_rep_id'      => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'funding_source_id'],
            ]);
            $this->db->query("ALTER TABLE `{$table}` ADD CONSTRAINT `{$table}_funding_source_id_foreign` FOREIGN KEY (`funding_source_id`) REFERENCES `funding_source` (`id`)");
            $this->db->query("ALTER TABLE `{$table}` ADD CONSTRAINT `{$table}_sales_rep_id_foreign` FOREIGN KEY (`sales_rep_id`) REFERENCES `sales_representative` (`id`)");
        }
    }

    /**
     * @return void
     */
    public function down(): void
    {
        foreach (['client_proposal_list', 'invoice'] as $table) {
            $this->db->query("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$table}_funding_source_id_foreign`");
            $this->db->query("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$table}_sales_rep_id_foreign`");
            $this->forge->dropColumn($table, ['funding_source_id', 'sales_rep_id']);
        }

        foreach (self::ITEM_TABLES as $table) {
            $this->forge->dropColumn($table, 'item_type');
        }

        $this->forge->dropColumn('product_list', 'pl_type');
        $this->forge->dropTable('funding_source', true);
    }
}
