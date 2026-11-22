<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Support\BaseMigration;

/**
 * PDF documents:
 * - company_profile: website, document_city (place in "Minahasa Utara, 22 November 2026"), signatory, default
 *   invoice notes and default agreement (SPK) clauses
 * - client_proposal_list: payment terms, shipment method, planned delivery date (printed on the proposal, copied to the invoice)
 * - payment.receipt_number: kwitansi number (KW sequence), assigned when the payment is recorded
 * - agreement: Surat Perintah Kerja / agreement made from an approved proposal (values copied, numbered SPK)
 * - document_verification: random token per printed document for the public "scan to verify" page
 */
class AddDocumentFields extends BaseMigration
{
    /**
     * @return void
     */
    public function up(): void
    {
        $this->forge->addColumn('company_profile', [
            'website'            => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'email'],
            'document_city'      => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'after' => 'website'],
            'signatory_name'     => ['type' => 'VARCHAR', 'constraint' => 128, 'null' => true, 'after' => 'document_city'],
            'signatory_position' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true, 'after' => 'signatory_name'],
            'invoice_terms'      => ['type' => 'TEXT', 'null' => true, 'after' => 'signatory_position'],
            'agreement_terms'    => ['type' => 'TEXT', 'null' => true, 'after' => 'invoice_terms'],
        ]);

        $this->forge->addColumn('client_proposal_list', [
            'payment_term_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'sales_rep_id'],
            'shipment_id'     => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'payment_term_id'],
            'delivery_date'   => ['type' => 'DATE', 'null' => true, 'after' => 'valid_until'],
        ]);
        $this->db->query('ALTER TABLE `client_proposal_list` ADD CONSTRAINT `client_proposal_list_payment_term_id_foreign` FOREIGN KEY (`payment_term_id`) REFERENCES `payment_terms` (`id`)');
        $this->db->query('ALTER TABLE `client_proposal_list` ADD CONSTRAINT `client_proposal_list_shipment_id_foreign` FOREIGN KEY (`shipment_id`) REFERENCES `shipment` (`id`)');

        $this->forge->addColumn('payment', [
            'receipt_number' => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true, 'after' => 'invoice_id'],
        ]);
        $this->db->query('ALTER TABLE `payment` ADD UNIQUE KEY `payment_receipt_number` (`receipt_number`)');

        // agreement (SPK)
        $this->forge->addField($this->idField() + [
            'agreement_number'          => $this->varchar(32),
            'proposal_id'               => $this->fk(),
            'agreement_date'            => ['type' => 'DATE', 'null' => false],
            'completion_date'           => ['type' => 'DATE', 'null' => true],
            'client_signatory_name'     => $this->varchar(128),
            'client_signatory_position' => $this->varchar(64, true),
            'client_signatory_nip'      => $this->varchar(32, true),
            'store_signatory_name'      => $this->varchar(128),
            'store_signatory_position'  => $this->varchar(64, true),
            'terms'                     => ['type' => 'TEXT', 'null' => true],
            'created_by'                => $this->fk(true),
        ] + $this->timestamps());
        $this->forge->addUniqueKey('agreement_number');
        $this->forge->addUniqueKey('proposal_id');
        $this->forge->addForeignKey('proposal_id', 'client_proposal_list', 'id');
        $this->forge->addForeignKey('created_by', 'users', 'id');
        $this->createStandardTable('agreement');

        // document_verification
        $this->forge->addField($this->idField() + [
            'doc_type'   => $this->varchar(16),
            'doc_id'     => $this->fk(),
            'token'      => ['type' => 'CHAR', 'constraint' => 32, 'null' => false],
            'created_at' => ['type' => 'DATETIME', 'null' => false],
        ]);
        $this->forge->addUniqueKey('token');
        $this->forge->addUniqueKey(['doc_type', 'doc_id']);
        $this->createStandardTable('document_verification');
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $this->forge->dropTable('document_verification', true);
        $this->forge->dropTable('agreement', true);
        $this->db->query('ALTER TABLE `payment` DROP INDEX `payment_receipt_number`');
        $this->forge->dropColumn('payment', 'receipt_number');
        $this->db->query('ALTER TABLE `client_proposal_list` DROP FOREIGN KEY `client_proposal_list_payment_term_id_foreign`');
        $this->db->query('ALTER TABLE `client_proposal_list` DROP FOREIGN KEY `client_proposal_list_shipment_id_foreign`');
        $this->forge->dropColumn('client_proposal_list', ['payment_term_id', 'shipment_id', 'delivery_date']);
        $this->forge->dropColumn('company_profile', ['website', 'document_city', 'signatory_name', 'signatory_position', 'invoice_terms', 'agreement_terms']);
    }
}
