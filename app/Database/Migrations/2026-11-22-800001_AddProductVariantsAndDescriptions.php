<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Support\BaseMigration;

/**
 * Product pictures, descriptions and variants; per-line descriptions on documents.
 * - product_list: pl_description (default line description), image_path (picture in writable/uploads/products/)
 * - product_variant: named variants of a product with their own SKU (<product SKU>-NN), price and optional picture
 * - client_proposal_item / invoice_item / credit_note_item: variant_id (copied), item_description (copied from the
 *   product, editable per line for special requests / custom descriptions)
 */
class AddProductVariantsAndDescriptions extends BaseMigration
{
    private const ITEM_TABLES = ['client_proposal_item', 'invoice_item', 'credit_note_item'];

    /**
     * @return void
     */
    public function up(): void
    {
        $this->forge->addColumn('product_list', [
            'pl_description' => ['type' => 'TEXT', 'null' => true, 'after' => 'pl_alias'],
            'image_path'     => $this->varchar(255, true) + ['after' => 'pl_description'],
        ]);

        $this->forge->addField($this->idField() + [
            'product_id' => $this->fk(),
            'pv_sku'     => $this->varchar(40),
            'pv_name'    => $this->varchar(128),
            'pv_price'   => $this->money(false),
            'image_path' => $this->varchar(255, true),
            'is_active'  => $this->flag(1),
            'sort_order' => ['type' => 'SMALLINT', 'unsigned' => true, 'null' => false, 'default' => 0],
        ] + $this->timestamps());
        $this->forge->addUniqueKey('pv_sku');
        $this->forge->addKey('product_id');
        $this->forge->addForeignKey('product_id', 'product_list', 'id');
        $this->createStandardTable('product_variant');

        foreach (self::ITEM_TABLES as $table) {
            $this->forge->addColumn($table, [
                'variant_id'       => $this->fk(true) + ['after' => 'product_id'],
                'item_description' => ['type' => 'TEXT', 'null' => true, 'after' => 'item_title'],
            ]);
            $this->db->query("ALTER TABLE `{$table}` ADD CONSTRAINT `{$table}_variant_id_foreign` FOREIGN KEY (`variant_id`) REFERENCES `product_variant` (`id`)");
        }
    }

    /**
     * @return void
     */
    public function down(): void
    {
        foreach (self::ITEM_TABLES as $table) {
            $this->dropForeignKeyIfExists($table, "{$table}_variant_id_foreign");

            if ($this->indexExists($table, "{$table}_variant_id_foreign")) {
                $this->db->query("ALTER TABLE `{$table}` DROP INDEX `{$table}_variant_id_foreign`");
            }

            $this->forge->dropColumn($table, ['variant_id', 'item_description']);
        }

        $this->forge->dropTable('product_variant', true);
        $this->forge->dropColumn('product_list', ['pl_description', 'image_path']);
    }
}
