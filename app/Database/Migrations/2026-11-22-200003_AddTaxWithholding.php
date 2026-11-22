<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Support\BaseMigration;

/**
 * Indonesian withholding/collection by the customer's treasurer (PPN pemungut, PPh 22, PPh 23, PPh final UMKM).
 * - company tax status (PKP, SK PP 55 UMKM certificate)
 * - tax_withholding_rule: editable, data-driven rules with regulation references
 * - invoice_withholding: expected amounts computed when an invoice is issued
 * - payment_withholding: amounts actually withheld, with bukti potong / NTPN
 */
class AddTaxWithholding extends BaseMigration
{
    private const KINDS = ['PPN', 'PPH22', 'PPH23', 'PPH_FINAL'];

    /**
     * @return void
     */
    public function up(): void
    {
        $this->forge->addColumn('company_profile', [
            'is_pkp'                       => ['type' => 'TINYINT', 'constraint' => 1, 'null' => false, 'default' => 1, 'after' => 'tax_id'],
            'umkm_certificate_number'      => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true, 'after' => 'is_pkp'],
            'umkm_certificate_valid_until' => ['type' => 'DATE', 'null' => true, 'after' => 'umkm_certificate_number'],
        ]);

        // tax_withholding_rule
        $this->forge->addField($this->idField() + [
            'code'            => $this->varchar(32),
            'name'            => $this->varchar(128),
            'tax_kind'        => ['type' => 'ENUM', 'constraint' => ['PPN', 'PPH22', 'PPH23'], 'null' => false],
            'base'            => ['type' => 'ENUM', 'constraint' => ['DPP', 'PPN'], 'null' => false, 'default' => 'DPP'],
            'item_type'       => ['type' => 'ENUM', 'constraint' => ['ALL', 'GOODS', 'SERVICE'], 'null' => false, 'default' => 'ALL'],
            'customer_status' => ['type' => 'ENUM', 'constraint' => ['ALL', 'PUBLIC', 'PRIVATE'], 'null' => false, 'default' => 'ALL'],
            'rate'            => $this->rate(),
            'umkm_rate'       => ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => true],
            'threshold'       => $this->money(),
            'threshold_basis' => ['type' => 'ENUM', 'constraint' => ['DPP', 'GROSS'], 'null' => false, 'default' => 'DPP'],
            'skip_for_bos'    => $this->flag(0),
            'requires_pkp'    => $this->flag(0),
            'regulation'      => $this->varchar(255, true),
            'valid_from'      => ['type' => 'DATE', 'null' => false],
            'valid_to'        => ['type' => 'DATE', 'null' => true],
            'is_active'       => $this->flag(1),
        ] + $this->timestamps());
        $this->forge->addUniqueKey(['code', 'valid_from']);
        $this->createStandardTable('tax_withholding_rule');

        // invoice: expected and actual withholding totals
        $this->forge->addColumn('invoice', [
            'withholding_total' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'null' => false, 'default' => '0.00', 'after' => 'grand_total'],
            'withheld_total'    => ['type' => 'DECIMAL', 'constraint' => '15,2', 'null' => false, 'default' => '0.00', 'after' => 'amount_paid'],
        ]);

        // invoice_withholding (expected, copied from rules when the invoice is issued)
        $this->forge->addField($this->idField() + [
            'invoice_id'  => $this->fk(),
            'rule_id'     => $this->fk(true),
            'tax_kind'    => ['type' => 'ENUM', 'constraint' => self::KINDS, 'null' => false],
            'description' => $this->varchar(255),
            'base_amount' => $this->money(),
            'rate'        => $this->rate(),
            'amount'      => $this->money(),
            'regulation'  => $this->varchar(255, true),
        ] + $this->timestamps());
        $this->forge->addForeignKey('invoice_id', 'invoice', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('rule_id', 'tax_withholding_rule', 'id');
        $this->createStandardTable('invoice_withholding');

        // payment_withholding (actual, with proof)
        $this->forge->addField($this->idField() + [
            'payment_id'             => $this->fk(),
            'invoice_withholding_id' => $this->fk(true),
            'tax_kind'               => ['type' => 'ENUM', 'constraint' => self::KINDS, 'null' => false],
            'amount'                 => $this->money(false),
            'document_number'        => $this->varchar(64, true),
            'document_date'          => ['type' => 'DATE', 'null' => true],
        ] + $this->timestamps());
        $this->forge->addForeignKey('payment_id', 'payment', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('invoice_withholding_id', 'invoice_withholding', 'id');
        $this->createStandardTable('payment_withholding');
        $this->db->query('ALTER TABLE `payment_withholding` ADD CONSTRAINT `chk_payment_withholding_amount_positive` CHECK (`amount` > 0)');
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $this->forge->dropTable('payment_withholding', true);
        $this->forge->dropTable('invoice_withholding', true);
        $this->forge->dropColumn('invoice', ['withholding_total', 'withheld_total']);
        $this->forge->dropTable('tax_withholding_rule', true);
        $this->forge->dropColumn('company_profile', ['is_pkp', 'umkm_certificate_number', 'umkm_certificate_valid_until']);
    }
}
