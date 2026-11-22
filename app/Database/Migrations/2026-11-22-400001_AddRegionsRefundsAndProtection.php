<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Support\BaseMigration;
use App\Database\Support\ProtectionTriggers;

/**
 * - region: Indonesian provinces / regencies (cascading address dropdowns), seeded by RegionSeeder
 * - payment: reversal (failed/mistaken payments are reversed with a reason, never deleted)
 * - refund: money paid back to the customer (overpayment, cancellation after payment), reversible
 * - invoice.refunded_total; credit_note.reason_type
 * - protection triggers (App\Database\Support\ProtectionTriggers), created here only if the DB user has TRIGGER
 */
class AddRegionsRefundsAndProtection extends BaseMigration
{

    /**
     * @return void
     */
    public function up(): void
    {
        // region
        $this->forge->addField($this->idField() + [
            'code'        => $this->varchar(13),
            'parent_code' => $this->varchar(13, true),
            'name'        => $this->varchar(100),
            'level'       => ['type' => 'TINYINT', 'unsigned' => true, 'null' => false],
        ] + $this->timestamps());
        $this->forge->addUniqueKey('code');
        $this->forge->addKey('parent_code');
        $this->createStandardTable('region');

        // payment reversal
        $this->forge->addColumn('payment', [
            'reversed_at'     => ['type' => 'DATETIME', 'null' => true, 'after' => 'recorded_by'],
            'reversed_by'     => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'reversed_at'],
            'reversal_reason' => ['type' => 'TEXT', 'null' => true, 'after' => 'reversed_by'],
        ]);
        $this->db->query('ALTER TABLE `payment` ADD CONSTRAINT `payment_reversed_by_foreign` FOREIGN KEY (`reversed_by`) REFERENCES `users` (`id`)');

        // refund
        $this->forge->addField($this->idField() + [
            'invoice_id'        => $this->fk(),
            'refund_date'       => ['type' => 'DATE', 'null' => false],
            'amount'            => $this->money(false),
            'payment_method_id' => $this->fk(),
            'bank_account_id'   => $this->fk(true),
            'reference_number'  => $this->varchar(64, true),
            'reason'            => ['type' => 'TEXT', 'null' => false],
            'recorded_by'       => $this->fk(),
            'reversed_at'       => ['type' => 'DATETIME', 'null' => true],
            'reversed_by'       => $this->fk(true),
            'reversal_reason'   => ['type' => 'TEXT', 'null' => true],
        ] + $this->timestamps());
        $this->forge->addForeignKey('invoice_id', 'invoice', 'id');
        $this->forge->addForeignKey('payment_method_id', 'payment_method', 'id');
        $this->forge->addForeignKey('bank_account_id', 'bank_account_list', 'id');
        $this->forge->addForeignKey('recorded_by', 'users', 'id');
        $this->forge->addForeignKey('reversed_by', 'users', 'id');
        $this->createStandardTable('refund');
        $this->db->query('ALTER TABLE `refund` ADD CONSTRAINT `chk_refund_amount_positive` CHECK (`amount` > 0)');

        $this->forge->addColumn('invoice', [
            'refunded_total' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'null' => false, 'default' => '0.00', 'after' => 'credit_total'],
        ]);
        $this->forge->addColumn('credit_note', [
            'reason_type' => [
                'type' => 'ENUM', 'constraint' => ['RETURN', 'PRICE_CORRECTION', 'CANCEL_BY_CLIENT', 'CANCEL_BY_STORE', 'OTHER'],
                'null' => false, 'default' => 'OTHER', 'after' => 'issue_date',
            ],
        ]);

        $this->createTriggers();
    }

    /**
     * Create the protection triggers if this DB user may (TRIGGER privilege). Otherwise print a warning:
     * a DBA must run the SQL from `php spark db:protection-sql` (the production app user should not have TRIGGER).
     *
     * @return void
     */
    private function createTriggers(): void
    {
        try {
            foreach (ProtectionTriggers::statements(ENVIRONMENT !== 'production') as $statement) {
                $this->db->query($statement);
            }
        } catch (\Throwable $e) {
            $message = 'Protection triggers not created (' . $e->getMessage() . '). Ask a DBA to run: php spark db:protection-sql';
            log_message('warning', $message);

            if (is_cli()) {
                \CodeIgniter\CLI\CLI::write($message, 'yellow');
            }
        }
    }

    /**
     * @return void
     */
    public function down(): void
    {
        foreach ($this->db->query("SHOW TRIGGERS WHERE `Trigger` LIKE 'trg\\_%'")->getResultArray() as $t) {
            $this->db->query('DROP TRIGGER IF EXISTS `' . $t['Trigger'] . '`');
        }

        $this->forge->dropColumn('credit_note', 'reason_type');
        $this->forge->dropColumn('invoice', 'refunded_total');
        $this->forge->dropTable('refund', true);
        $this->db->query('ALTER TABLE `payment` DROP FOREIGN KEY `payment_reversed_by_foreign`');
        $this->forge->dropColumn('payment', ['reversed_at', 'reversed_by', 'reversal_reason']);
        $this->forge->dropTable('region', true);
    }
}
