<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Support\BaseMigration;
use App\Services\ProposalRevisions;

/**
 * Negotiation history of proposals:
 * - proposal_revision: append-only snapshot (items + totals) of every version sent to the customer (and of the
 *   approved version when it differs from the last one sent)
 * - client_proposal_list.revision_no: number of the latest revision (0 = never sent), printed on the PDF
 * Proposals that were already sent get revision 1 = their current content.
 */
class AddProposalRevisions extends BaseMigration
{
    /**
     * @return void
     */
    public function up(): void
    {
        $this->forge->addField($this->idField() + [
            'proposal_id'    => $this->fk(),
            'revision_no'    => ['type' => 'SMALLINT', 'unsigned' => true, 'null' => false],
            'status_code'    => $this->varchar(32),
            'items'          => ['type' => 'LONGTEXT', 'null' => false],
            'subtotal'       => $this->money(),
            'discount_total' => $this->money(),
            'tax_total'      => $this->money(),
            'grand_total'    => $this->money(),
            'valid_until'    => ['type' => 'DATE', 'null' => true],
            'note'           => ['type' => 'TEXT', 'null' => true],
            'created_by'     => $this->fk(true),
            'created_at'     => ['type' => 'DATETIME', 'null' => false],
        ]);
        $this->forge->addUniqueKey(['proposal_id', 'revision_no']);
        $this->forge->addForeignKey('proposal_id', 'client_proposal_list', 'id');
        $this->forge->addForeignKey('created_by', 'users', 'id');
        $this->createStandardTable('proposal_revision');

        $this->forge->addColumn('client_proposal_list', [
            'revision_no' => ['type' => 'SMALLINT', 'unsigned' => true, 'null' => false, 'default' => 0, 'after' => 'status_id'],
        ]);

        // Already sent proposals: their current content becomes revision 1.
        $sent = $this->db->table('client_proposal_list p')
            ->select('p.id, p.created_by, s.code')
            ->join('client_proposal_status s', 's.id = p.status_id')
            ->whereIn('s.code', ['PROPOSE', 'UNDER_REVIEW', 'STORE_APPROVE'])
            ->get()->getResultArray();

        foreach ($sent as $row) {
            $snap = ProposalRevisions::snapshot((int) $row['id'], $this->db);
            $this->db->table('proposal_revision')->insert([
                'proposal_id'    => $row['id'],
                'revision_no'    => 1,
                'status_code'    => $row['code'],
                'items'          => json_encode($snap['items'], JSON_UNESCAPED_UNICODE),
                'subtotal'       => $snap['subtotal'],
                'discount_total' => $snap['discount_total'],
                'tax_total'      => $snap['tax_total'],
                'grand_total'    => $snap['grand_total'],
                'valid_until'    => $snap['valid_until'],
                'note'           => 'Recorded when revision tracking was introduced.',
                'created_by'     => $row['created_by'],
                'created_at'     => date('Y-m-d H:i:s'),
            ]);
            $this->db->table('client_proposal_list')->where('id', $row['id'])->update(['revision_no' => 1]);
        }
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $this->forge->dropColumn('client_proposal_list', 'revision_no');
        $this->forge->dropTable('proposal_revision', true);
    }
}
