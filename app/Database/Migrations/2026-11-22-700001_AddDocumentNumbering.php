<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Support\BaseMigration;

/**
 * - document_numbering: number format per document type, editable in Settings (defaults in Config\Documents,
 *   rows created by NumberingSeeder)
 * - delivery_note.handover_number: the BAST gets its own number (own format), assigned with the delivery note
 */
class AddDocumentNumbering extends BaseMigration
{
    /**
     * @return void
     */
    public function up(): void
    {
        $this->forge->addField($this->idField() + [
            'doc_type' => $this->varchar(16),
            'pattern'  => $this->varchar(64),
            'period'   => ['type' => 'ENUM', 'constraint' => ['monthly', 'yearly', 'never'], 'null' => false, 'default' => 'monthly'],
        ] + $this->timestamps(false));
        $this->forge->addUniqueKey('doc_type');
        $this->createStandardTable('document_numbering');

        $this->forge->addColumn('delivery_note', [
            'handover_number' => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true, 'after' => 'delivery_number'],
        ]);
        $this->db->query('ALTER TABLE `delivery_note` ADD UNIQUE KEY `delivery_note_handover_number` (`handover_number`)');
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $this->db->query('ALTER TABLE `delivery_note` DROP INDEX `delivery_note_handover_number`');
        $this->forge->dropColumn('delivery_note', 'handover_number');
        $this->forge->dropTable('document_numbering', true);
    }
}
