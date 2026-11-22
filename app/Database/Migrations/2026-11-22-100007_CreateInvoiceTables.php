<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Support\BaseMigration;

/**
 * Invoices, invoice items, payments and credit notes.
 */
class CreateInvoiceTables extends BaseMigration
{
    /**
     * @return void
     */
    public function up(): void
    {
        // invoice
        $this->forge->addField($this->idField() + [
            'invoice_number'    => $this->varchar(32),
            'customer_id'       => $this->fk(),
            'proposal_id'       => $this->fk(true),
            'billing_address'   => ['type' => 'TEXT', 'null' => false],
            'customer_tax_id'   => $this->varchar(32, true),
            'issue_date'        => ['type' => 'DATE', 'null' => false],
            'due_date'          => ['type' => 'DATE', 'null' => false],
            'payment_term_id'   => $this->fk(),
            'payment_method_id' => $this->fk(true),
            'shipment_id'       => $this->fk(true),
            'bank_account_id'   => $this->fk(true),
            'status_id'         => $this->fk(),
            'subtotal'          => $this->money(),
            'discount_total'    => $this->money(),
            'tax_total'         => $this->money(),
            'shipping_cost'     => $this->money(),
            'grand_total'       => $this->money(),
            'amount_paid'       => $this->money(),
            'credit_total'      => $this->money(),
            'notes'             => ['type' => 'TEXT', 'null' => true],
            'issued_at'         => ['type' => 'DATETIME', 'null' => true],
            'created_by'        => $this->fk(true),
        ] + $this->timestamps());
        $this->forge->addUniqueKey('invoice_number');
        $this->forge->addKey(['status_id', 'due_date']);
        $this->forge->addKey('issue_date');
        $this->forge->addForeignKey('customer_id', 'customer_list', 'id');
        $this->forge->addForeignKey('proposal_id', 'client_proposal_list', 'id');
        $this->forge->addForeignKey('payment_term_id', 'payment_terms', 'id');
        $this->forge->addForeignKey('payment_method_id', 'payment_method', 'id');
        $this->forge->addForeignKey('shipment_id', 'shipment', 'id');
        $this->forge->addForeignKey('bank_account_id', 'bank_account_list', 'id');
        $this->forge->addForeignKey('status_id', 'invoice_status', 'id');
        $this->forge->addForeignKey('created_by', 'users', 'id');
        $this->createStandardTable('invoice');

        // invoice_item
        $this->forge->addField($this->idField() + ['invoice_id' => $this->fk()]
            + $this->itemLineFields('quantity') + $this->timestamps());
        $this->forge->addForeignKey('invoice_id', 'invoice', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('product_id', 'product_list', 'id');
        $this->createStandardTable('invoice_item');

        // payment
        $this->forge->addField($this->idField() + [
            'invoice_id'        => $this->fk(),
            'payment_date'      => ['type' => 'DATE', 'null' => false],
            'amount'            => $this->money(false),
            'payment_method_id' => $this->fk(),
            'bank_account_id'   => $this->fk(true),
            'reference_number'  => $this->varchar(64, true),
            'note'              => ['type' => 'TEXT', 'null' => true],
            'recorded_by'       => $this->fk(),
        ] + $this->timestamps());
        $this->forge->addForeignKey('invoice_id', 'invoice', 'id');
        $this->forge->addForeignKey('payment_method_id', 'payment_method', 'id');
        $this->forge->addForeignKey('bank_account_id', 'bank_account_list', 'id');
        $this->forge->addForeignKey('recorded_by', 'users', 'id');
        $this->createStandardTable('payment');
        $this->db->query('ALTER TABLE `payment` ADD CONSTRAINT `chk_payment_amount_positive` CHECK (`amount` > 0)');

        // credit_note
        $this->forge->addField($this->idField() + [
            'credit_note_number' => $this->varchar(32),
            'invoice_id'         => $this->fk(),
            'issue_date'         => ['type' => 'DATE', 'null' => false],
            'reason'             => ['type' => 'TEXT', 'null' => false],
            'subtotal'           => $this->money(),
            'tax_total'          => $this->money(),
            'grand_total'        => $this->money(),
            'created_by'         => $this->fk(),
        ] + $this->timestamps());
        $this->forge->addUniqueKey('credit_note_number');
        $this->forge->addForeignKey('invoice_id', 'invoice', 'id');
        $this->forge->addForeignKey('created_by', 'users', 'id');
        $this->createStandardTable('credit_note');

        // credit_note_item
        $this->forge->addField($this->idField() + ['credit_note_id' => $this->fk()]
            + $this->itemLineFields('quantity') + $this->timestamps());
        $this->forge->addForeignKey('credit_note_id', 'credit_note', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('product_id', 'product_list', 'id');
        $this->createStandardTable('credit_note_item');
    }

    /**
     * @return void
     */
    public function down(): void
    {
        foreach (['credit_note_item', 'credit_note', 'payment', 'invoice_item', 'invoice'] as $table) {
            $this->forge->dropTable($table, true);
        }
    }
}
