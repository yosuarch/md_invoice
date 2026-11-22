<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Support\BaseMigration;

/**
 * Proposals (quotations), their item lines and status history.
 */
class CreateProposalTables extends BaseMigration
{
    /**
     * @return void
     */
    public function up(): void
    {
        // client_proposal_list
        $this->forge->addField($this->idField() + [
            'cpl_id'             => $this->varchar(32),
            'customer_id'        => $this->fk(),
            'contact_person_id'  => $this->fk(true),
            'cpl_proposal_title' => $this->varchar(200),
            'status_id'          => $this->fk(),
            'status_updated_by'  => $this->fk(true),
            'status_updated_at'  => ['type' => 'DATETIME', 'null' => true],
            'valid_until'        => ['type' => 'DATE', 'null' => true],
            'subtotal'           => $this->money(),
            'discount_total'     => $this->money(),
            'tax_total'          => $this->money(),
            'grand_total'        => $this->money(),
            'cpl_note'           => ['type' => 'TEXT', 'null' => true],
            'created_by'         => $this->fk(true),
        ] + $this->timestamps());
        $this->forge->addUniqueKey('cpl_id');
        $this->forge->addForeignKey('customer_id', 'customer_list', 'id');
        $this->forge->addForeignKey('contact_person_id', 'customer_contact_person', 'id');
        $this->forge->addForeignKey('status_id', 'client_proposal_status', 'id');
        $this->forge->addForeignKey('status_updated_by', 'users', 'id');
        $this->forge->addForeignKey('created_by', 'users', 'id');
        $this->createStandardTable('client_proposal_list');

        // client_proposal_item
        $this->forge->addField($this->idField() + ['proposal_id' => $this->fk()]
            + $this->itemLineFields('cpi_quantity') + $this->timestamps());
        $this->forge->addForeignKey('proposal_id', 'client_proposal_list', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('product_id', 'product_list', 'id');
        $this->createStandardTable('client_proposal_item');

        // proposal_status_history (append-only log: no updated_at / deleted_at)
        $this->forge->addField($this->idField() + [
            'proposal_id'    => $this->fk(),
            'from_status_id' => $this->fk(true),
            'to_status_id'   => $this->fk(),
            'changed_by'     => $this->fk(),
            'note'           => ['type' => 'TEXT', 'null' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => false],
        ]);
        $this->forge->addForeignKey('proposal_id', 'client_proposal_list', 'id');
        $this->forge->addForeignKey('from_status_id', 'client_proposal_status', 'id');
        $this->forge->addForeignKey('to_status_id', 'client_proposal_status', 'id');
        $this->forge->addForeignKey('changed_by', 'users', 'id');
        $this->createStandardTable('proposal_status_history');
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $this->forge->dropTable('proposal_status_history', true);
        $this->forge->dropTable('client_proposal_item', true);
        $this->forge->dropTable('client_proposal_list', true);
    }
}
