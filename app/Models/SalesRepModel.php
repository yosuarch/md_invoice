<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Sales representatives. A rep does not need a login; user_id optionally links a staff account
 * (used for the "My customers" filter).
 */
class SalesRepModel extends BaseModel
{
    protected $table             = 'sales_representative';
    protected array $lowercase = ['rep_name']; // stored lower case, shown with up()
    protected $allowedFields     = ['rep_name', 'rep_phone', 'rep_email', 'user_id', 'is_active'];
    protected array $nullable    = ['rep_phone', 'rep_email', 'user_id'];
    protected array $fieldLabels = ['rep_name' => 'Name', 'rep_phone' => 'Phone', 'rep_email' => 'Email', 'user_id' => 'Login account'];

    protected array $referencedBy = [
        'customer_sales_rep'   => 'sales_rep_id',
        'client_proposal_list' => 'sales_rep_id',
        'invoice'              => 'sales_rep_id',
    ];

    protected $validationRules = [
        'rep_name'  => 'required|max_length[128]',
        'rep_phone' => 'permit_empty|max_length[32]|phone',
        'rep_email' => 'permit_empty|max_length[255]|valid_email',
        'user_id'   => 'permit_empty|is_natural_no_zero|is_not_unique[users.id]|is_unique[sales_representative.user_id,id,{id}]',
        'is_active' => 'permit_empty|in_list[0,1]',
    ];

    protected $validationMessages = [
        'user_id' => ['is_unique' => 'This login account is already linked to another sales rep.'],
    ];

    /**
     * Reps with their linked username and number of customers.
     *
     * @return list<array<string, mixed>>
     */
    public function withStats(): array
    {
        return $this->select('sales_representative.*, users.username, COUNT(csr.id) AS customer_count')
            ->join('users', 'users.id = sales_representative.user_id', 'left')
            ->join('customer_sales_rep csr', 'csr.sales_rep_id = sales_representative.id', 'left')
            ->groupBy('sales_representative.id')
            ->orderBy('sales_representative.is_active', 'DESC')
            ->orderBy('sales_representative.rep_name')
            ->findAll();
    }

    /**
     * Active reps as select options, plus given ids even if inactive (already assigned).
     *
     * @param list<int> $include Ids to include.
     *
     * @return array<int, string>
     */
    public function activeOptions(array $include = []): array
    {
        $this->groupStart()->where('is_active', 1);

        if ($include !== []) {
            $this->orWhereIn('id', $include);
        }

        $this->groupEnd();

        return array_column($this->orderBy('rep_name')->findAll(), 'rep_name', 'id');
    }

    /**
     * Rep linked to a login account, if any.
     *
     * @param int $userId User id.
     *
     * @return array<string, mixed>|null
     */
    public function forUser(int $userId): ?array
    {
        return $this->where('user_id', $userId)->first();
    }
}
