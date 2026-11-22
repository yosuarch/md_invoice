<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Support\BaseMigration;

/**
 * credit_note.shipping_amount: shipping cost credited (e.g. on a cancellation), so an invoice with shipping
 * can be credited in full. grand_total = subtotal + tax_total + shipping_amount.
 */
class AddCreditNoteShipping extends BaseMigration
{
    /**
     * @return void
     */
    public function up(): void
    {
        $this->forge->addColumn('credit_note', [
            'shipping_amount' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'null' => false, 'default' => '0.00', 'after' => 'tax_total'],
        ]);
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $this->forge->dropColumn('credit_note', 'shipping_amount');
    }
}
