<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Support\BaseMigration;

/**
 * Shared contacts and a lean CRM:
 * - contact_role: lookup (HEADMASTER, TREASURER, OPERATOR, COMMITTEE, FOUNDATION, OTHER)
 * - contact_person: the person (name, phone, WhatsApp, email, NIP, note), stored once
 * - customer_contact_person becomes the link person ↔ school: contact_id, role_id, position, is_primary, ended_on
 *   (the person's own columns move to contact_person; proposals keep referencing the link row)
 * - crm_activity: calls, visits, WhatsApp, emails, meetings, notes and follow-ups per school
 *   (optionally linked to a person, proposal or invoice)
 */
class AddContactsAndActivities extends BaseMigration
{
    /**
     * @return void
     */
    public function up(): void
    {
        $this->forge->addField($this->idField() + [
            'code'       => $this->varchar(32),
            'name'       => $this->varchar(64),
            'sort_order' => ['type' => 'TINYINT', 'unsigned' => true, 'null' => false, 'default' => 0],
        ] + $this->timestamps());
        $this->forge->addUniqueKey('code');
        $this->createStandardTable('contact_role');

        $this->forge->addField($this->idField() + [
            'cp_first_name' => $this->varchar(64),
            'cp_last_name'  => $this->varchar(64, true),
            'cp_phone'      => $this->varchar(32, true),
            'cp_whatsapp'   => $this->flag(1),
            'cp_email'      => $this->varchar(255, true),
            'cp_nip'        => $this->varchar(32, true),
            'cp_note'       => ['type' => 'TEXT', 'null' => true],
            'is_active'     => $this->flag(1),
        ] + $this->timestamps());
        $this->forge->addKey('cp_first_name');
        $this->forge->addKey('cp_phone');
        $this->createStandardTable('contact_person');

        $this->forge->addColumn('customer_contact_person', [
            'contact_id' => $this->fk(true) + ['after' => 'customer_id'],
            'role_id'    => $this->fk(true) + ['after' => 'contact_id'],
            'ended_on'   => ['type' => 'DATE', 'null' => true, 'after' => 'is_primary'],
        ]);

        // Every existing contact row becomes one person.
        $now = date('Y-m-d H:i:s');

        foreach ($this->db->table('customer_contact_person')->get()->getResultArray() as $row) {
            $this->db->table('contact_person')->insert([
                'cp_first_name' => $row['ccp_first_name'],
                'cp_last_name'  => $row['ccp_last_name'],
                'cp_phone'      => $row['ccp_phone_number'],
                'cp_whatsapp'   => 1,
                'cp_email'      => $row['ccp_email'],
                'is_active'     => 1,
                'created_at'    => $row['created_at'] ?? $now,
                'updated_at'    => $now,
                'deleted_at'    => $row['deleted_at'],
            ]);
            $this->db->table('customer_contact_person')->where('id', $row['id'])->update(['contact_id' => $this->db->insertID()]);
        }

        $this->db->query('ALTER TABLE `customer_contact_person` MODIFY `contact_id` INT UNSIGNED NOT NULL');
        $this->db->query('ALTER TABLE `customer_contact_person` ADD CONSTRAINT `ccp_contact_id_foreign` FOREIGN KEY (`contact_id`) REFERENCES `contact_person` (`id`)');
        $this->db->query('ALTER TABLE `customer_contact_person` ADD CONSTRAINT `ccp_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `contact_role` (`id`)');
        $this->forge->dropColumn('customer_contact_person', ['ccp_first_name', 'ccp_last_name', 'ccp_phone_number', 'ccp_email']);

        $this->forge->addField($this->idField() + [
            'customer_id'   => $this->fk(),
            'contact_id'    => $this->fk(true),
            'proposal_id'   => $this->fk(true),
            'invoice_id'    => $this->fk(true),
            'activity_type' => ['type' => 'ENUM', 'constraint' => ['CALL', 'VISIT', 'WHATSAPP', 'EMAIL', 'MEETING', 'NOTE'], 'null' => false, 'default' => 'CALL'],
            'subject'       => $this->varchar(200),
            'notes'         => ['type' => 'TEXT', 'null' => true],
            'occurred_at'   => ['type' => 'DATETIME', 'null' => true],
            'due_on'        => ['type' => 'DATE', 'null' => true],
            'done_at'       => ['type' => 'DATETIME', 'null' => true],
            'outcome'       => ['type' => 'TEXT', 'null' => true],
            'assigned_to'   => $this->fk(true),
            'created_by'    => $this->fk(true),
        ] + $this->timestamps());
        $this->forge->addKey(['customer_id', 'occurred_at']);
        $this->forge->addKey(['assigned_to', 'done_at', 'due_on']);
        $this->forge->addKey('contact_id');
        $this->forge->addKey('proposal_id');
        $this->forge->addKey('invoice_id');
        $this->forge->addForeignKey('customer_id', 'customer_list', 'id');
        $this->forge->addForeignKey('contact_id', 'contact_person', 'id');
        $this->forge->addForeignKey('proposal_id', 'client_proposal_list', 'id');
        $this->forge->addForeignKey('invoice_id', 'invoice', 'id');
        $this->forge->addForeignKey('assigned_to', 'users', 'id');
        $this->forge->addForeignKey('created_by', 'users', 'id');
        $this->createStandardTable('crm_activity');
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $this->forge->dropTable('crm_activity', true);

        $this->forge->addColumn('customer_contact_person', [
            'ccp_first_name'   => $this->varchar(64, true) + ['after' => 'customer_id'],
            'ccp_last_name'    => $this->varchar(64, true) + ['after' => 'ccp_first_name'],
            'ccp_phone_number' => $this->varchar(32, true) + ['after' => 'ccp_last_name'],
            'ccp_email'        => $this->varchar(255, true) + ['after' => 'ccp_phone_number'],
        ]);
        $this->db->query('UPDATE customer_contact_person l JOIN contact_person p ON p.id = l.contact_id
            SET l.ccp_first_name = p.cp_first_name, l.ccp_last_name = p.cp_last_name, l.ccp_phone_number = COALESCE(p.cp_phone, \'\'), l.ccp_email = p.cp_email');
        $this->db->query("UPDATE customer_contact_person SET ccp_first_name = '' WHERE ccp_first_name IS NULL");
        $this->db->query('ALTER TABLE `customer_contact_person` MODIFY `ccp_first_name` VARCHAR(64) NOT NULL, MODIFY `ccp_phone_number` VARCHAR(32) NOT NULL');

        $this->dropForeignKeyIfExists('customer_contact_person', 'ccp_contact_id_foreign');
        $this->dropForeignKeyIfExists('customer_contact_person', 'ccp_role_id_foreign');

        foreach (['ccp_contact_id_foreign', 'ccp_role_id_foreign'] as $index) {
            if ($this->indexExists('customer_contact_person', $index)) {
                $this->db->query("ALTER TABLE `customer_contact_person` DROP INDEX `{$index}`");
            }
        }

        $this->forge->dropColumn('customer_contact_person', ['contact_id', 'role_id', 'ended_on']);
        $this->forge->dropTable('contact_person', true);
        $this->forge->dropTable('contact_role', true);
    }
}
