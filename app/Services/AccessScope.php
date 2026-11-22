<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SalesRepModel;
use App\Models\UserModel;
use CodeIgniter\Model;

/**
 * Record-level access. Users with `records.view_all` (admin, finance) see everything. Everybody else (sales) only sees
 * what is related to them:
 * - customers assigned to their sales rep (customer_sales_rep)
 * - proposals they created, where they are the sales rep, or of their customers
 * - invoices of those proposals, where they are the sales rep, they created, or of their customers
 * - delivery notes, payments, credit notes, PDFs and attachments of visible invoices / proposals
 * Hidden records are answered with 404 (never "forbidden"), so their existence is not revealed.
 */
final class AccessScope
{
    private int $userId;
    private bool $all;
    private ?int $repId;

    /**
     * @param int|null $userId User (default: logged-in user).
     */
    public function __construct(?int $userId = null)
    {
        $this->userId = $userId ?? (int) auth()->id();
        $user         = $userId === null ? auth()->user() : (new UserModel())->findById($userId);
        $this->all    = $user !== null && $user->can('records.view_all');
        $rep          = (new SalesRepModel())->forUser($this->userId);
        $this->repId  = $rep !== null ? (int) $rep['id'] : null;
    }

    /**
     * Whether the user sees all records.
     *
     * @return bool
     */
    public function seesAll(): bool
    {
        return $this->all;
    }

    /**
     * Sales rep linked to the user, or null.
     *
     * @return int|null
     */
    public function repId(): ?int
    {
        return $this->repId;
    }

    /**
     * SQL condition: customer column belongs to the user's customers.
     *
     * @param string $column Qualified customer id column.
     *
     * @return string
     */
    private function ownCustomerSql(string $column): string
    {
        return $this->repId === null
            ? '0 = 1'
            : "{$column} IN (SELECT csr.customer_id FROM customer_sales_rep csr WHERE csr.sales_rep_id = {$this->repId})";
    }

    /**
     * SQL condition for proposals (table alias/name given).
     *
     * @param string $t Proposal table name or alias.
     *
     * @return string
     */
    public function proposalSql(string $t = 'client_proposal_list'): string
    {
        $parts = ["{$t}.created_by = {$this->userId}", $this->ownCustomerSql("{$t}.customer_id")];

        if ($this->repId !== null) {
            $parts[] = "{$t}.sales_rep_id = {$this->repId}";
        }

        return '(' . implode(' OR ', $parts) . ')';
    }

    /**
     * SQL condition for invoices.
     *
     * @param string $t Invoice table name or alias.
     *
     * @return string
     */
    public function invoiceSql(string $t = 'invoice'): string
    {
        $parts = [
            "{$t}.created_by = {$this->userId}",
            $this->ownCustomerSql("{$t}.customer_id"),
            "{$t}.proposal_id IN (SELECT vp.id FROM client_proposal_list vp WHERE " . $this->proposalSql('vp') . ')',
        ];

        if ($this->repId !== null) {
            $parts[] = "{$t}.sales_rep_id = {$this->repId}";
        }

        return '(' . implode(' OR ', $parts) . ')';
    }

    /**
     * Limit a customer query (customer_list) to visible customers.
     *
     * @param Model $query Model query on customer_list.
     *
     * @return Model
     */
    public function customers(Model $query): Model
    {
        return $this->all ? $query : $query->where($this->ownCustomerSql('customer_list.id'), null, false);
    }

    /**
     * Limit a proposal query.
     *
     * @param Model $query Model query on client_proposal_list.
     *
     * @return Model
     */
    public function proposals(Model $query): Model
    {
        return $this->all ? $query : $query->where($this->proposalSql(), null, false);
    }

    /**
     * Limit an invoice query (also used for queries joined with `invoice`: deliveries, payments, credit notes).
     *
     * @param Model $query Model query with the invoice table joined or as base table.
     *
     * @return Model
     */
    public function invoices(Model $query): Model
    {
        return $this->all ? $query : $query->where($this->invoiceSql(), null, false);
    }

    /**
     * Whether a customer is visible.
     *
     * @param int $customerId Customer id.
     *
     * @return bool
     */
    public function canCustomer(int $customerId): bool
    {
        return $this->all || ($this->repId !== null
            && db_connect()->table('customer_sales_rep')->where(['customer_id' => $customerId, 'sales_rep_id' => $this->repId])->countAllResults() > 0);
    }

    /**
     * Whether a proposal is visible.
     *
     * @param int $proposalId Proposal id.
     *
     * @return bool
     */
    public function canProposal(int $proposalId): bool
    {
        return $this->all || db_connect()->query(
            'SELECT 1 FROM client_proposal_list WHERE id = ? AND ' . $this->proposalSql(),
            [$proposalId],
        )->getRowArray() !== null;
    }

    /**
     * Whether an invoice is visible.
     *
     * @param int $invoiceId Invoice id.
     *
     * @return bool
     */
    public function canInvoice(int $invoiceId): bool
    {
        return $this->all || db_connect()->query(
            'SELECT 1 FROM invoice WHERE id = ? AND ' . $this->invoiceSql(),
            [$invoiceId],
        )->getRowArray() !== null;
    }

    /**
     * Limit a contact_person query: people linked to one of the user's schools.
     *
     * @param Model $query Model query on contact_person.
     *
     * @return Model
     */
    public function contacts(Model $query): Model
    {
        return $this->all ? $query : $query->where('contact_person.id IN (SELECT l.contact_id FROM customer_contact_person l WHERE l.deleted_at IS NULL AND ' . $this->ownCustomerSql('l.customer_id') . ')', null, false);
    }

    /**
     * Whether a person is visible (linked to one of the user's schools).
     *
     * @param int $contactId Person id.
     *
     * @return bool
     */
    public function canContact(int $contactId): bool
    {
        if ($this->all) {
            return true;
        }

        return db_connect()->table('customer_contact_person l')->where('l.contact_id', $contactId)->where('l.deleted_at', null)
            ->where($this->ownCustomerSql('l.customer_id'), null, false)->countAllResults() > 0;
    }

    /**
     * Limit a crm_activity query: activities of the user's schools.
     *
     * @param Model $query Model query on crm_activity.
     *
     * @return Model
     */
    public function activities(Model $query): Model
    {
        return $this->all ? $query : $query->where($this->ownCustomerSql('crm_activity.customer_id'), null, false);
    }

    /**
     * Sales rep of the user, created (linked to the user) when missing. Used when a sales user adds a school,
     * so the school can be assigned to them.
     *
     * @return int Sales rep id.
     */
    public function ensureRep(): int
    {
        if ($this->repId !== null) {
            return $this->repId;
        }

        $user = (new UserModel())->findById($this->userId);
        $id   = (new SalesRepModel())->insert([
            'rep_name'  => trim((string) ($user->full_name ?? '')) ?: (string) $user->username,
            'user_id'   => $this->userId,
            'is_active' => 1,
        ]);

        return $this->repId = (int) $id;
    }
}
