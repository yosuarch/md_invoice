<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Support\BaseMigration;

/**
 * Dolibarr-style pricing and tax per line:
 * - product_list.pl_price_incl_tax: the default price is entered including tax (price base); pl_min_price / pv_min_price:
 *   minimum selling price (excl. tax) — lines below it need the prices.override permission
 * - item lines: tax_code (the tax rate chosen on the line, default from the product), price_base EXCL/INCL (how the
 *   price was typed) and unit_price_incl (the typed price incl. tax). unit_price / discount_amount stay excl. tax and
 *   drive all totals (DocumentTotals).
 * Existing lines get the tax code of their product's tax rate (or none for 0%).
 */
class AddLineTaxAndPriceBase extends BaseMigration
{
    private const ITEM_TABLES = ['client_proposal_item', 'invoice_item', 'credit_note_item'];

    /**
     * @return void
     */
    public function up(): void
    {
        $this->forge->addColumn('product_list', [
            'pl_price_incl_tax' => $this->flag(0) + ['after' => 'pl_price'],
            'pl_min_price'      => ['null' => true, 'after' => 'pl_price_incl_tax'] + $this->money(false),
        ]);
        $this->forge->addColumn('product_variant', [
            'pv_min_price' => ['null' => true, 'after' => 'pv_price'] + $this->money(false),
        ]);

        foreach (self::ITEM_TABLES as $table) {
            $this->forge->addColumn($table, [
                'tax_code'        => $this->varchar(16, true) + ['after' => 'tax_rate'],
                'price_base'      => ['type' => 'ENUM', 'constraint' => ['EXCL', 'INCL'], 'null' => false, 'default' => 'EXCL', 'after' => 'unit_price'],
                'unit_price_incl' => ['null' => true, 'after' => 'price_base'] + $this->money(false),
            ]);
            $this->db->query("UPDATE `{$table}` l JOIN product_list p ON p.id = l.product_id JOIN tax_rate t ON t.id = p.tax_rate_id
                SET l.tax_code = t.code WHERE l.tax_rate > 0");
        }
    }

    /**
     * @return void
     */
    public function down(): void
    {
        foreach (self::ITEM_TABLES as $table) {
            $this->forge->dropColumn($table, ['tax_code', 'price_base', 'unit_price_incl']);
        }

        $this->forge->dropColumn('product_variant', 'pv_min_price');
        $this->forge->dropColumn('product_list', ['pl_price_incl_tax', 'pl_min_price']);
    }
}
