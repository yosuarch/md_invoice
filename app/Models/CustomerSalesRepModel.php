<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Pivot customer_list <-> sales_representative. Hard deletes (no soft delete); changes are audited.
 */
class CustomerSalesRepModel extends BaseModel
{
    protected $table          = 'customer_sales_rep';
    protected $useSoftDeletes = false;
    protected $allowedFields  = ['customer_id', 'sales_rep_id'];

    /**
     * Rep ids assigned to a customer.
     *
     * @param int $customerId Customer id.
     *
     * @return list<int>
     */
    public function repIdsFor(int $customerId): array
    {
        return array_map('intval', $this->where('customer_id', $customerId)->findColumn('sales_rep_id') ?? []);
    }

    /**
     * Reps of a customer with their details.
     *
     * @param int $customerId Customer id.
     *
     * @return list<array<string, mixed>>
     */
    public function repsFor(int $customerId): array
    {
        return $this->db->table('customer_sales_rep csr')
            ->select('r.*')
            ->join('sales_representative r', 'r.id = csr.sales_rep_id')
            ->where('csr.customer_id', $customerId)
            ->where('r.deleted_at', null)
            ->orderBy('r.rep_name')
            ->get()->getResultArray();
    }
}
