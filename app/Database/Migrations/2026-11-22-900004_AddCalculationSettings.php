<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Support\BaseMigration;

/**
 * Calculation settings (Settings → Calculation) and exact incl.-tax lines:
 * - company_profile.calc_tax_mode (LINE / TOTAL), calc_rounding (HALF_UP / DOWN), calc_amount_decimals (2 / 0),
 *   calc_unit_decimals (2–6)
 * - item lines: unit_price DECIMAL(19,6) (unit price excl. tax derived from an incl. price keeps its decimals) and
 *   discount_incl (the discount as typed on lines priced incl. tax)
 */
class AddCalculationSettings extends BaseMigration
{
    private const ITEM_TABLES = ['client_proposal_item', 'invoice_item', 'credit_note_item'];

    /**
     * @return void
     */
    public function up(): void
    {
        $this->forge->addColumn('company_profile', [
            'calc_tax_mode'        => ['type' => 'ENUM', 'constraint' => ['LINE', 'TOTAL'], 'null' => false, 'default' => 'LINE'],
            'calc_rounding'        => ['type' => 'ENUM', 'constraint' => ['HALF_UP', 'DOWN'], 'null' => false, 'default' => 'HALF_UP'],
            'calc_amount_decimals' => ['type' => 'TINYINT', 'constraint' => 1, 'unsigned' => true, 'null' => false, 'default' => 2],
            'calc_unit_decimals'   => ['type' => 'TINYINT', 'constraint' => 1, 'unsigned' => true, 'null' => false, 'default' => 2],
        ]);

        foreach (self::ITEM_TABLES as $table) {
            $this->forge->modifyColumn($table, [
                'unit_price' => ['name' => 'unit_price', 'type' => 'DECIMAL', 'constraint' => '19,6', 'null' => false],
            ]);
            $this->forge->addColumn($table, [
                'discount_incl' => ['null' => true, 'after' => 'discount_amount'] + $this->money(false),
            ]);
        }
    }

    /**
     * @return void
     */
    public function down(): void
    {
        foreach (self::ITEM_TABLES as $table) {
            if ($this->db->fieldExists('discount_incl', $table)) {
                $this->forge->dropColumn($table, 'discount_incl');
            }

            // the ALTER rounds the values to 2 decimals (no UPDATE: protection triggers reject changes to issued lines)
            $this->forge->modifyColumn($table, [
                'unit_price' => ['name' => 'unit_price', 'type' => 'DECIMAL', 'constraint' => '15,2', 'null' => false],
            ]);
        }

        foreach (['calc_tax_mode', 'calc_rounding', 'calc_amount_decimals', 'calc_unit_decimals'] as $column) {
            if ($this->db->fieldExists($column, 'company_profile')) {
                $this->forge->dropColumn('company_profile', $column);
            }
        }
    }
}
