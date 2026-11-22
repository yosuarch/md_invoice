<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Support\BaseMigration;

/**
 * Record-level access lets a sales user select only schools assigned to their sales rep. Documents created before
 * that rule never assigned the school. Backfill customer_sales_rep from existing documents:
 * - the sales rep of every proposal and invoice,
 * - the sales rep linked to the user who created a proposal.
 * From now on ProposalService / InvoiceService assign the rep automatically (CustomerService::assignRep).
 */
class BackfillCustomerSalesReps extends BaseMigration
{
    /**
     * @return void
     */
    public function up(): void
    {
        $now = date('Y-m-d H:i:s');

        $this->db->query(
            'INSERT IGNORE INTO customer_sales_rep (customer_id, sales_rep_id, created_at, updated_at)
             SELECT DISTINCT p.customer_id, p.sales_rep_id, ?, ? FROM client_proposal_list p
             JOIN sales_representative r ON r.id = p.sales_rep_id AND r.deleted_at IS NULL
             WHERE p.deleted_at IS NULL AND p.sales_rep_id IS NOT NULL',
            [$now, $now],
        );

        $this->db->query(
            'INSERT IGNORE INTO customer_sales_rep (customer_id, sales_rep_id, created_at, updated_at)
             SELECT DISTINCT i.customer_id, i.sales_rep_id, ?, ? FROM invoice i
             JOIN sales_representative r ON r.id = i.sales_rep_id AND r.deleted_at IS NULL
             WHERE i.deleted_at IS NULL AND i.sales_rep_id IS NOT NULL',
            [$now, $now],
        );

        $this->db->query(
            'INSERT IGNORE INTO customer_sales_rep (customer_id, sales_rep_id, created_at, updated_at)
             SELECT DISTINCT p.customer_id, r.id, ?, ? FROM client_proposal_list p
             JOIN sales_representative r ON r.user_id = p.created_by AND r.deleted_at IS NULL
             WHERE p.deleted_at IS NULL',
            [$now, $now],
        );
    }

    /**
     * Assignments are business data that may have been edited since; they are not removed.
     *
     * @return void
     */
    public function down(): void
    {
    }
}
