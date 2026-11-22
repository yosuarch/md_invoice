<?php

declare(strict_types=1);

namespace App\Database\Support;

/**
 * Database triggers that make financial records and logs tamper-resistant for every application user,
 * admins included (the app never deletes them; these triggers also stop bugs and direct SQL from the app user):
 *
 * - audit_log, proposal_status_history: append-only (no UPDATE, no DELETE)
 * - payment, refund: never deleted, amount/date/invoice fixed, no soft delete, a reversal cannot be undone
 * - payment_withholding: never deleted, amount fixed (proof number/date may be completed)
 * - credit_note(+_item), invoice_withholding: immutable
 * - invoice(+_item): never hard-deleted; once issued no soft delete and number/total/customer/lines fixed
 * - delivery_note(+_item): never deleted (cancelled instead), number/invoice fixed, lines immutable
 * - delivery_note_event: append-only tracking log
 *
 * Creating triggers needs the TRIGGER privilege, which the application DB user should NOT have in production.
 * A DBA runs the SQL from `php spark db:protection-sql` once (and again after migrations that change these tables).
 * The development variant adds a bypass (`SET @allow_test_purge = 1`) used only to remove ZZ test data.
 */
final class ProtectionTriggers
{
    /**
     * name => [table, event, condition, message]
     *
     * @return array<string, array{0: string, 1: string, 2: string, 3: string}>
     */
    public static function definitions(): array
    {
        return [
            'trg_audit_log_no_update' => ['audit_log', 'UPDATE', 'TRUE', 'audit_log is append-only'],
            'trg_audit_log_no_delete' => ['audit_log', 'DELETE', 'TRUE', 'audit_log is append-only'],
            'trg_psh_no_update'       => ['proposal_status_history', 'UPDATE', 'TRUE', 'proposal_status_history is append-only'],
            'trg_psh_no_delete'       => ['proposal_status_history', 'DELETE', 'TRUE', 'proposal_status_history is append-only'],

            'trg_payment_no_delete' => ['payment', 'DELETE', 'TRUE', 'payments are never deleted (reverse them)'],
            'trg_payment_fixed'     => ['payment', 'UPDATE',
                'NEW.amount <> OLD.amount OR NEW.invoice_id <> OLD.invoice_id OR NEW.payment_date <> OLD.payment_date'
                . ' OR NEW.deleted_at IS NOT NULL OR (OLD.reversed_at IS NOT NULL AND NOT (NEW.reversed_at <=> OLD.reversed_at))',
                'payment amounts cannot be changed (reverse the payment)'],
            'trg_pw_no_delete' => ['payment_withholding', 'DELETE', 'TRUE', 'withheld taxes are never deleted'],
            'trg_pw_fixed'     => ['payment_withholding', 'UPDATE',
                'NEW.amount <> OLD.amount OR NEW.payment_id <> OLD.payment_id OR NEW.tax_kind <> OLD.tax_kind OR NEW.deleted_at IS NOT NULL',
                'withheld tax amounts cannot be changed'],
            'trg_refund_no_delete' => ['refund', 'DELETE', 'TRUE', 'refunds are never deleted (reverse them)'],
            'trg_refund_fixed'     => ['refund', 'UPDATE',
                'NEW.amount <> OLD.amount OR NEW.invoice_id <> OLD.invoice_id OR NEW.refund_date <> OLD.refund_date'
                . ' OR NEW.deleted_at IS NOT NULL OR (OLD.reversed_at IS NOT NULL AND NOT (NEW.reversed_at <=> OLD.reversed_at))',
                'refund amounts cannot be changed (reverse the refund)'],

            'trg_cn_no_delete'  => ['credit_note', 'DELETE', 'TRUE', 'credit notes are never deleted'],
            'trg_cn_fixed'      => ['credit_note', 'UPDATE', 'TRUE', 'credit notes cannot be changed'],
            'trg_cni_no_delete' => ['credit_note_item', 'DELETE', 'TRUE', 'credit note lines are never deleted'],
            'trg_cni_fixed'     => ['credit_note_item', 'UPDATE', 'TRUE', 'credit note lines cannot be changed'],
            'trg_iw_no_delete'  => ['invoice_withholding', 'DELETE', 'TRUE', 'expected withholding is never deleted'],
            'trg_iw_fixed'      => ['invoice_withholding', 'UPDATE', 'TRUE', 'expected withholding cannot be changed'],

            'trg_invoice_no_delete' => ['invoice', 'DELETE', 'TRUE', 'invoices are never deleted'],
            'trg_invoice_issued'    => ['invoice', 'UPDATE',
                'OLD.issued_at IS NOT NULL AND (NEW.deleted_at IS NOT NULL OR NEW.invoice_number <> OLD.invoice_number'
                . ' OR NEW.grand_total <> OLD.grand_total OR NEW.customer_id <> OLD.customer_id)',
                'an issued invoice cannot be deleted or changed (use a credit note or void)'],
            'trg_invoice_item_no_delete' => ['invoice_item', 'DELETE', 'TRUE', 'invoice lines are never hard-deleted'],
            'trg_invoice_item_issued'    => ['invoice_item', 'UPDATE',
                '(SELECT i.issued_at FROM invoice i WHERE i.id = OLD.invoice_id) IS NOT NULL',
                'lines of an issued invoice cannot be changed'],

            'trg_dn_no_delete'  => ['delivery_note', 'DELETE', 'TRUE', 'delivery notes are never deleted (cancel them)'],
            'trg_dn_fixed'      => ['delivery_note', 'UPDATE',
                'NEW.delivery_number <> OLD.delivery_number OR NEW.invoice_id <> OLD.invoice_id OR NEW.deleted_at IS NOT NULL',
                'delivery note number and invoice cannot be changed'],
            'trg_dni_no_delete' => ['delivery_note_item', 'DELETE', 'TRUE', 'delivery note lines are never deleted'],
            'trg_dni_fixed'     => ['delivery_note_item', 'UPDATE', 'TRUE', 'delivery note lines cannot be changed'],
            'trg_dne_no_update' => ['delivery_note_event', 'UPDATE', 'TRUE', 'the delivery tracking log is append-only'],
            'trg_dne_no_delete' => ['delivery_note_event', 'DELETE', 'TRUE', 'the delivery tracking log is append-only'],

            'trg_prev_no_update' => ['proposal_revision', 'UPDATE', 'TRUE', 'proposal revisions are append-only'],
            'trg_prev_no_delete' => ['proposal_revision', 'DELETE', 'TRUE', 'proposal revisions are append-only'],
            'trg_att_no_delete'  => ['attachment', 'DELETE', 'TRUE', 'attachments are never deleted (they are removed with a soft delete)'],
            'trg_att_fixed'      => ['attachment', 'UPDATE',
                'NEW.file_path <> OLD.file_path OR NEW.entity_type <> OLD.entity_type OR NEW.entity_id <> OLD.entity_id',
                'the file and document of an attachment cannot be changed'],
        ];
    }

    /**
     * SQL statements (DROP + CREATE per trigger), each a single statement without DELIMITER.
     *
     * @param bool $testBypass Add the development-only bypass (@allow_test_purge = 1).
     *
     * @return list<string>
     */
    public static function statements(bool $testBypass): array
    {
        $sql = [];

        foreach (self::definitions() as $name => [$table, $event, $condition, $message]) {
            $body = "IF {$condition} THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}'; END IF;";

            if ($testBypass) {
                $body = "IF COALESCE(@allow_test_purge, 0) <> 1 THEN {$body} END IF;";
            }

            $sql[] = "DROP TRIGGER IF EXISTS `{$name}`";
            $sql[] = "CREATE TRIGGER `{$name}` BEFORE {$event} ON `{$table}` FOR EACH ROW BEGIN {$body} END";
        }

        return $sql;
    }
}
