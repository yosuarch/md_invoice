<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Support\BaseMigration;

/**
 * Delivery notes (surat jalan) and their items; supports partial delivery.
 */
class CreateDeliveryTables extends BaseMigration
{
    /**
     * @return void
     */
    public function up(): void
    {
        // delivery_note
        $this->forge->addField($this->idField() + [
            'delivery_number'  => $this->varchar(32),
            'invoice_id'       => $this->fk(),
            'shipment_id'      => $this->fk(),
            'shipping_address' => ['type' => 'TEXT', 'null' => false],
            'courier_name'     => $this->varchar(64, true),
            'tracking_number'  => $this->varchar(64, true),
            'delivered_at'     => ['type' => 'DATETIME', 'null' => true],
            'received_by_name' => $this->varchar(128, true),
        ] + $this->timestamps());
        $this->forge->addUniqueKey('delivery_number');
        $this->forge->addForeignKey('invoice_id', 'invoice', 'id');
        $this->forge->addForeignKey('shipment_id', 'shipment', 'id');
        $this->createStandardTable('delivery_note');

        // delivery_note_item
        $this->forge->addField($this->idField() + [
            'delivery_note_id' => $this->fk(),
            'invoice_item_id'  => $this->fk(),
            'quantity'         => $this->quantity(),
        ] + $this->timestamps());
        $this->forge->addForeignKey('delivery_note_id', 'delivery_note', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('invoice_item_id', 'invoice_item', 'id');
        $this->createStandardTable('delivery_note_item');
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $this->forge->dropTable('delivery_note_item', true);
        $this->forge->dropTable('delivery_note', true);
    }
}
