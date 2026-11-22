<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Support\BaseMigration;

/**
 * Phase 5 delivery notes: ship date, note, creator, and cancellation (a delivery note is never deleted;
 * one that was not delivered can be cancelled with a reason, keeping its number).
 */
class AddDeliveryNoteFields extends BaseMigration
{
    /**
     * @return void
     */
    public function up(): void
    {
        $this->forge->addColumn('delivery_note', [
            'ship_date'     => ['type' => 'DATE', 'null' => true, 'after' => 'shipment_id'],
            'note'          => ['type' => 'TEXT', 'null' => true, 'after' => 'received_by_name'],
            'created_by'    => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'note'],
            'cancelled_at'  => ['type' => 'DATETIME', 'null' => true, 'after' => 'created_by'],
            'cancelled_by'  => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'cancelled_at'],
            'cancel_reason' => ['type' => 'TEXT', 'null' => true, 'after' => 'cancelled_by'],
        ]);
        $this->db->query('ALTER TABLE `delivery_note` ADD CONSTRAINT `delivery_note_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)');
        $this->db->query('ALTER TABLE `delivery_note` ADD CONSTRAINT `delivery_note_cancelled_by_foreign` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`)');
        $this->db->query('ALTER TABLE `delivery_note` ADD INDEX `delivery_note_invoice_status` (`invoice_id`, `cancelled_at`)');
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $this->dropForeignKeyIfExists('delivery_note', 'delivery_note_created_by_foreign');
        $this->dropForeignKeyIfExists('delivery_note', 'delivery_note_cancelled_by_foreign');

        // The composite index may serve the invoice_id foreign key: give that key its own index first.
        if ($this->indexExists('delivery_note', 'delivery_note_invoice_status')) {
            $this->db->query('ALTER TABLE `delivery_note` ADD INDEX `delivery_note_invoice_id` (`invoice_id`)');
            $this->db->query('ALTER TABLE `delivery_note` DROP INDEX `delivery_note_invoice_status`');
        }
        $this->forge->dropColumn('delivery_note', ['ship_date', 'note', 'created_by', 'cancelled_at', 'cancelled_by', 'cancel_reason']);
    }
}
