<?php

declare(strict_types=1);

namespace App\Database\Support;

use CodeIgniter\Database\Migration;

/**
 * Shared column definitions so every migration follows the database conventions
 * in .guidelines/database.md (INT UNSIGNED keys, DECIMAL money, soft-delete timestamps, InnoDB).
 */
abstract class BaseMigration extends Migration
{
    /**
     * Auto-increment primary key column.
     *
     * @param bool $big Use BIGINT (for high-volume log tables).
     *
     * @return array<string, array<string, mixed>>
     */
    protected function idField(bool $big = false): array
    {
        return ['id' => ['type' => $big ? 'BIGINT' : 'INT', 'unsigned' => true, 'auto_increment' => true]];
    }

    /**
     * Foreign key column (same type as every referenced id).
     *
     * @param bool $nullable Whether the reference is optional.
     *
     * @return array<string, mixed>
     */
    protected function fk(bool $nullable = false): array
    {
        return ['type' => 'INT', 'unsigned' => true, 'null' => $nullable];
    }

    /**
     * Money column DECIMAL(15,2).
     *
     * @param bool $withDefault Default to 0.00 (totals) or require a value (prices, amounts).
     *
     * @return array<string, mixed>
     */
    protected function money(bool $withDefault = true): array
    {
        $field = ['type' => 'DECIMAL', 'constraint' => '15,2', 'null' => false];

        if ($withDefault) {
            $field['default'] = '0.00';
        }

        return $field;
    }

    /**
     * Quantity column DECIMAL(12,3).
     *
     * @return array<string, mixed>
     */
    protected function quantity(): array
    {
        return ['type' => 'DECIMAL', 'constraint' => '12,3', 'null' => false];
    }

    /**
     * Tax rate percent column DECIMAL(5,2), e.g. 11.00.
     *
     * @return array<string, mixed>
     */
    protected function rate(): array
    {
        return ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => false, 'default' => '0.00'];
    }

    /**
     * Boolean flag TINYINT(1).
     *
     * @param int $default 1 or 0.
     *
     * @return array<string, mixed>
     */
    protected function flag(int $default): array
    {
        return ['type' => 'TINYINT', 'constraint' => 1, 'null' => false, 'default' => $default];
    }

    /**
     * Required/optional VARCHAR.
     *
     * @param int  $length   Max length.
     * @param bool $nullable Whether NULL is allowed.
     *
     * @return array<string, mixed>
     */
    protected function varchar(int $length, bool $nullable = false): array
    {
        return ['type' => 'VARCHAR', 'constraint' => $length, 'null' => $nullable];
    }

    /**
     * Standard created_at / updated_at / deleted_at columns.
     *
     * @param bool $softDelete Include deleted_at (false for log/sequence tables).
     *
     * @return array<string, array<string, mixed>>
     */
    protected function timestamps(bool $softDelete = true): array
    {
        $fields = [
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ];

        if ($softDelete) {
            $fields['deleted_at'] = ['type' => 'DATETIME', 'null' => true];
        }

        return $fields;
    }

    /**
     * Item line columns shared by client_proposal_item, invoice_item and credit_note_item.
     * Values are copied from the product so old documents never change.
     *
     * @param string $quantityColumn Name of the quantity column.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function itemLineFields(string $quantityColumn): array
    {
        return [
            'product_id'      => $this->fk(),
            'item_title'      => $this->varchar(200),
            'unit_code'       => $this->varchar(16),
            $quantityColumn   => $this->quantity(),
            'unit_price'      => $this->money(false),
            'discount_amount' => $this->money(),
            'tax_rate'        => $this->rate(),
            'tax_amount'      => $this->money(),
            'line_total'      => $this->money(false),
        ];
    }

    /**
     * Create the table with the project's table options (InnoDB; charset/collation from Config\Database).
     *
     * @param string $table Table name.
     *
     * @return void
     */
    protected function createStandardTable(string $table): void
    {
        $this->forge->addPrimaryKey('id');
        $this->forge->createTable($table, false, ['ENGINE' => 'InnoDB']);
    }

    /**
     * Drop a foreign key if it exists (rollbacks stay repeatable after a partly failed run).
     *
     * @param string $table Table name.
     * @param string $name  Constraint name.
     *
     * @return void
     */
    protected function dropForeignKeyIfExists(string $table, string $name): void
    {
        $exists = $this->db->query(
            "SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
            [$table, $name],
        )->getRow();

        if ($exists !== null) {
            $this->db->query("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$name}`");
        }
    }

    /**
     * Whether an index exists on a table.
     *
     * @param string $table Table name.
     * @param string $name  Index name.
     *
     * @return bool
     */
    protected function indexExists(string $table, string $name): bool
    {
        return $this->db->query(
            'SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1',
            [$table, $name],
        )->getRow() !== null;
    }
}
