<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\CustomerAddressModel;
use App\Models\CustomerContactModel;
use App\Models\CustomerModel;
use App\Models\CustomerSalesRepModel;
use App\Models\PaymentTermModel;
use App\Models\ProposalModel;
use App\Models\SalesRepModel;
use App\Models\SchoolLevelModel;
use App\Services\CustomerService;
use App\Services\ValidationFailed;
use CodeIgniter\HTTP\RedirectResponse;
use RuntimeException;
use Throwable;

/**
 * Customers - mostly schools (permission customers.view / customers.manage).
 */
class Customers extends BaseController
{
    private const FIELDS = [
        'cl_organization_name', 'npsn', 'school_level_id', 'school_status', 'cl_tax_id', 'cl_email', 'cl_phone',
        'default_payment_term_id', 'credit_limit', 'is_active',
    ];

    private CustomerModel $model;
    private CustomerService $service;

    public function __construct()
    {
        $this->model   = new CustomerModel();
        $this->service = new CustomerService();
    }

    /**
     * Searchable, filterable, paginated list.
     *
     * @return string
     */
    public function index(): string
    {
        $filters = [
            'q'             => trim((string) $this->request->getGet('q')),
            'status'        => (string) $this->request->getGet('status'),
            'level'         => (string) $this->request->getGet('level'),
            'school_status' => (string) $this->request->getGet('school_status'),
            'rep'           => (string) $this->request->getGet('rep'),
        ];

        $myRep     = (new SalesRepModel())->forUser($this->userId());
        $customers = $this->scope()->customers($this->model->search($filters, $myRep !== null ? (int) $myRep['id'] : null))->paginate(20);

        return view('customers/index', [
            'customers' => $customers,
            'repNames'  => $this->repNamesFor(array_column($customers, 'id')),
            'pager'     => $this->model->pager,
            'filters'   => $filters,
            'levels'    => (new SchoolLevelModel())->options(),
            'reps'      => (new SalesRepModel())->activeOptions(),
            'hasMyRep'  => $myRep !== null,
        ]);
    }

    /**
     * Customer detail with school data, sales reps, contacts and addresses.
     *
     * @param int $id Customer id.
     *
     * @return string
     */
    public function show(int $id): string
    {
        $this->guardCustomer($id);

        $customer = $this->findOr404($this->model, $id);

        return view('customers/show', [
            'customer'  => $customer,
            'term'      => $customer['default_payment_term_id'] ? (new PaymentTermModel())->find($customer['default_payment_term_id']) : null,
            'level'     => $customer['school_level_id'] ? (new SchoolLevelModel())->find($customer['school_level_id']) : null,
            'reps'      => (new CustomerSalesRepModel())->repsFor($id),
            'contacts'  => (new CustomerContactModel())->forCustomer($id),
            'addresses' => (new CustomerAddressModel())->forCustomer($id),
            'proposals' => user_can('proposals.view') ? $this->scope()->proposals((new ProposalModel())->search(['customer' => (string) $id]))->findAll(5) : null,
            'invoices'  => user_can('invoices.view') ? $this->scope()->invoices((new \App\Models\InvoiceModel())->search(['customer' => (string) $id]))->findAll(5) : null,
            'activityPanel' => user_can('crm.view') ? \App\Services\ActivityPanel::forCustomer($id, $this->scope()) : null,
        ]);
    }

    /**
     * Create form.
     *
     * @return string
     */
    public function new(): string
    {
        $myRep = (new SalesRepModel())->forUser($this->userId());

        return view('customers/form', [
            'customer'     => [],
            'assignedReps' => $myRep !== null ? [(int) $myRep['id']] : [],
        ] + $this->formOptions([]));
    }

    /**
     * Store a new customer (UUID is generated).
     *
     * @return RedirectResponse
     */
    public function create(): RedirectResponse
    {
        try {
            $id = $this->service->create($this->postFields(self::FIELDS), $this->postedRepIds());
        } catch (ValidationFailed $e) {
            return $this->backWithErrors($e->errors);
        } catch (Throwable $e) {
            return $this->failed($e, 'Customer create');
        }

        return redirect()->to("customers/{$id}")->with('message', lang('Customers.created'));
    }

    /**
     * Edit form.
     *
     * @param int $id Customer id.
     *
     * @return string
     */
    public function edit(int $id): string
    {
        $this->guardCustomer($id);

        $assigned = (new CustomerSalesRepModel())->repIdsFor($id);

        return view('customers/form', [
            'customer'     => $this->findOr404($this->model, $id),
            'assignedReps' => $assigned,
        ] + $this->formOptions($assigned));
    }

    /**
     * Update a customer.
     *
     * @param int $id Customer id.
     *
     * @return RedirectResponse
     */
    public function update(int $id): RedirectResponse
    {
        $this->guardCustomer($id);

        $this->findOr404($this->model, $id);

        try {
            $this->service->update($id, $this->postFields(self::FIELDS), $this->postedRepIds());
        } catch (ValidationFailed $e) {
            return $this->backWithErrors($e->errors);
        } catch (Throwable $e) {
            return $this->failed($e, 'Customer update');
        }

        return redirect()->to("customers/{$id}")->with('message', lang('Customers.updated'));
    }

    /**
     * Delete a customer that no document uses.
     *
     * @param int $id Customer id.
     *
     * @return RedirectResponse
     */
    public function delete(int $id): RedirectResponse
    {
        $this->guardCustomer($id);

        $customer = $this->findOr404($this->model, $id);

        try {
            $this->service->delete($id);
        } catch (RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            return $this->failed($e, 'Customer delete');
        }

        return redirect()->to('customers')->with('message', lang('Customers.deleted', [up($customer['cl_organization_name'])]));
    }

    /**
     * Sales rep ids from the form (checkbox list "sales_reps[]").
     *
     * @return list<int>
     */
    private function postedRepIds(): array
    {
        $ids = $this->request->getPost('sales_reps');

        return is_array($ids) ? array_values(array_filter(array_map('intval', $ids))) : [];
    }

    /**
     * Rep names per customer for the list (one query for the page).
     *
     * @param list<int|string> $customerIds
     *
     * @return array<int, list<string>>
     */
    private function repNamesFor(array $customerIds): array
    {
        if ($customerIds === []) {
            return [];
        }

        $rows = db_connect()->table('customer_sales_rep csr')
            ->select('csr.customer_id, r.rep_name')
            ->join('sales_representative r', 'r.id = csr.sales_rep_id')
            ->whereIn('csr.customer_id', $customerIds)
            ->where('r.deleted_at', null)
            ->orderBy('r.rep_name')
            ->get()->getResultArray();

        $names = [];

        foreach ($rows as $row) {
            $names[(int) $row['customer_id']][] = $row['rep_name'];
        }

        return $names;
    }

    /**
     * Select options for the form.
     *
     * @param list<int> $assignedReps Reps to keep in the list even if inactive.
     *
     * @return array<string, mixed>
     */
    private function formOptions(array $assignedReps): array
    {
        $terms = [];

        foreach ((new PaymentTermModel())->orderBy('due_days')->findAll() as $t) {
            $terms[(int) $t['id']] = $t['code'] . ' (' . ((int) $t['due_days'] === 0 ? 'on delivery' : $t['due_days'] . ' days') . ')';
        }

        return [
            'terms'  => $terms,
            'levels' => (new SchoolLevelModel())->options(),
            'reps'   => (new SalesRepModel())->activeOptions($assignedReps),
        ];
    }
}
