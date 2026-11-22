<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Support\BaseMigration;

/**
 * Delivery tracking:
 * - delivery_note.shipped_at: when the goods left (status PREPARING → SHIPPED)
 * - delivery_note_event: append-only tracking timeline (created, shipped, tracking updates, delivered, cancelled)
 */
class AddDeliveryTracking extends BaseMigration
{
    /**
     * @return void
     */
    public function up(): void
    {
        $this->forge->addColumn('delivery_note', [
            'shipped_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'tracking_number'],
        ]);

        $this->forge->addField($this->idField() + [
            'delivery_note_id' => $this->fk(),
            'event_type'       => ['type' => 'ENUM', 'constraint' => ['CREATED', 'SHIPPED', 'UPDATE', 'DELIVERED', 'CANCELLED'], 'null' => false],
            'event_at'         => ['type' => 'DATETIME', 'null' => false],
            'location'         => $this->varchar(128, true),
            'note'             => ['type' => 'TEXT', 'null' => true],
            'created_by'       => $this->fk(true),
            'created_at'       => ['type' => 'DATETIME', 'null' => false],
        ]);
        $this->forge->addKey(['delivery_note_id', 'event_at']);
        $this->forge->addForeignKey('delivery_note_id', 'delivery_note', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('created_by', 'users', 'id');
        $this->createStandardTable('delivery_note_event');
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $this->forge->dropTable('delivery_note_event', true);
        $this->forge->dropColumn('delivery_note', 'shipped_at');
    }
}
