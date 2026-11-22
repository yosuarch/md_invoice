<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Support\BaseMigration;

/**
 * Document numbering, audit trail and file attachments.
 */
class CreateSupportTables extends BaseMigration
{
    /**
     * @return void
     */
    public function up(): void
    {
        // document_sequence: read with SELECT ... FOR UPDATE inside a transaction. Never soft-deleted.
        $this->forge->addField($this->idField() + [
            'doc_type'    => $this->varchar(16),
            'period'      => $this->varchar(7),
            'last_number' => ['type' => 'INT', 'unsigned' => true, 'null' => false, 'default' => 0],
        ] + $this->timestamps(false));
        $this->forge->addUniqueKey(['doc_type', 'period']);
        $this->createStandardTable('document_sequence');

        // audit_log (append-only: created_at only)
        $this->forge->addField($this->idField(true) + [
            'user_id'    => $this->fk(true),
            'table_name' => $this->varchar(64),
            'record_id'  => ['type' => 'INT', 'unsigned' => true, 'null' => false],
            'action'     => ['type' => 'ENUM', 'constraint' => ['CREATE', 'UPDATE', 'DELETE'], 'null' => false],
            'old_values' => ['type' => 'JSON', 'null' => true],
            'new_values' => ['type' => 'JSON', 'null' => true],
            'ip_address' => $this->varchar(45, true),
            'created_at' => ['type' => 'DATETIME', 'null' => false],
        ]);
        $this->forge->addKey(['table_name', 'record_id']);
        $this->forge->addForeignKey('user_id', 'users', 'id');
        $this->createStandardTable('audit_log');

        // attachment (polymorphic: entity_type + entity_id)
        $this->forge->addField($this->idField() + [
            'entity_type' => $this->varchar(32),
            'entity_id'   => ['type' => 'INT', 'unsigned' => true, 'null' => false],
            'file_name'   => $this->varchar(255),
            'file_path'   => $this->varchar(255),
            'mime_type'   => $this->varchar(100),
            'uploaded_by' => $this->fk(),
        ] + $this->timestamps());
        $this->forge->addKey(['entity_type', 'entity_id']);
        $this->forge->addForeignKey('uploaded_by', 'users', 'id');
        $this->createStandardTable('attachment');
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $this->forge->dropTable('attachment', true);
        $this->forge->dropTable('audit_log', true);
        $this->forge->dropTable('document_sequence', true);
    }
}
