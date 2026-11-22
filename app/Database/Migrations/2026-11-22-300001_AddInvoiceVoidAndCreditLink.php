<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Support\BaseMigration;

/**
 * Phase 4 additions:
 * - invoice: void_reason / voided_at / voided_by (who voided an issued invoice and why)
 * - credit_note_item.invoice_item_id: the invoice line being credited (caps the credited amount per line)
 */
class AddInvoiceVoidAndCreditLink extends BaseMigration
{
    /**
     * @return void
     */
    public function up(): void
    {
        $this->forge->addColumn('invoice', [
            'void_reason' => ['type' => 'TEXT', 'null' => true, 'after' => 'issued_at'],
            'voided_at'   => ['type' => 'DATETIME', 'null' => true, 'after' => 'void_reason'],
            'voided_by'   => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'voided_at'],
        ]);
        $this->db->query('ALTER TABLE `invoice` ADD CONSTRAINT `invoice_voided_by_foreign` FOREIGN KEY (`voided_by`) REFERENCES `users` (`id`)');

        $this->forge->addColumn('credit_note_item', [
            'invoice_item_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'credit_note_id'],
        ]);
        $this->db->query('ALTER TABLE `credit_note_item` ADD CONSTRAINT `credit_note_item_invoice_item_id_foreign` FOREIGN KEY (`invoice_item_id`) REFERENCES `invoice_item` (`id`)');
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $this->db->query('ALTER TABLE `credit_note_item` DROP FOREIGN KEY `credit_note_item_invoice_item_id_foreign`');
        $this->forge->dropColumn('credit_note_item', 'invoice_item_id');
        $this->db->query('ALTER TABLE `invoice` DROP FOREIGN KEY `invoice_voided_by_foreign`');
        $this->forge->dropColumn('invoice', ['void_reason', 'voided_at', 'voided_by']);
    }
}
