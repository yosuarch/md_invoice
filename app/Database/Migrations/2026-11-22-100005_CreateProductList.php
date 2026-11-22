<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Support\BaseMigration;

/**
 * product_list: sellable goods/services with their current default price.
 */
class CreateProductList extends BaseMigration
{
    /**
     * @return void
     */
    public function up(): void
    {
        $this->forge->addField($this->idField() + [
            'pl_sku'      => $this->varchar(32),
            'pl_title'    => $this->varchar(200),
            'pl_alias'    => $this->varchar(200, true),
            'unit_id'     => $this->fk(),
            'pl_price'    => $this->money(),
            'tax_rate_id' => $this->fk(true),
            'is_active'   => $this->flag(1),
        ] + $this->timestamps());
        $this->forge->addUniqueKey('pl_sku');
        $this->forge->addKey('pl_title');
        $this->forge->addForeignKey('unit_id', 'unit_of_measurement', 'id');
        $this->forge->addForeignKey('tax_rate_id', 'tax_rate', 'id');
        $this->createStandardTable('product_list');
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $this->forge->dropTable('product_list', true);
    }
}
