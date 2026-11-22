<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\CustomerContactModel;
use App\Models\CustomerModel;
use App\Models\CustomerSalesRepModel;
use App\Models\ProductModel;
use App\Models\RegionModel;
use App\Models\SalesRepModel;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

/**
 * JSON lookups for document forms (product picker, customer picker). GET only, behind the session filter.
 */
class Lookup extends BaseController
{
    /**
     * Active products matching ?q= with the values a new document line copies.
     *
     * @return ResponseInterface
     */
    public function products(): ResponseInterface
    {
        try {
            $q    = trim((string) $this->request->getGet('q'));
            $rows = (new ProductModel())->lineDefaults(['q' => $q], date('Y-m-d'), 20);

            return $this->response->setJSON(['results' => $rows]);
        } catch (Throwable $e) {
            return $this->jsonError($e, 'Product lookup');
        }
    }

    /**
     * Active customers matching ?q= (name, NPSN, NPWP).
     *
     * @return ResponseInterface
     */
    public function customers(): ResponseInterface
    {
        try {
            $q    = trim((string) $this->request->getGet('q'));
            $rows = $this->scope()->customers((new CustomerModel())->search(['q' => $q, 'status' => 'active']))->findAll(20);

            $results = array_map(static fn (array $c): array => [
                'id'            => (int) $c['id'],
                'name'          => up($c['cl_organization_name']),
                'npsn'          => $c['npsn'],
                'school_level'  => $c['school_level_name'],
                'school_status' => $c['school_status'],
            ], $rows);

            return $this->response->setJSON(['results' => $results]);
        } catch (Throwable $e) {
            return $this->jsonError($e, 'Customer lookup');
        }
    }

    /**
     * Contacts and sales reps of one customer (fills the proposal form after picking a customer).
     *
     * @param int $id Customer id.
     *
     * @return ResponseInterface
     */
    public function customer(int $id): ResponseInterface
    {
        try {
            $customer = $this->scope()->canCustomer($id) ? (new CustomerModel())->find($id) : null;

            if ($customer === null) {
                return $this->response->setStatusCode(404)->setJSON(['error' => lang('Customers.notFound')]);
            }

            $contacts = array_map(static fn (array $c): array => [
                'id'         => (int) $c['id'],
                'name'       => up(trim($c['ccp_first_name'] . ' ' . ($c['ccp_last_name'] ?? ''))),
                'position'   => \App\Models\CustomerContactModel::roleLabel($c),
                'is_primary' => (bool) $c['is_primary'],
            ], (new CustomerContactModel())->forCustomer($id, true));

            $repIds = (new CustomerSalesRepModel())->repIdsFor($id);
            $myRep  = (new SalesRepModel())->forUser($this->userId());
            $myId   = $myRep !== null ? (int) $myRep['id'] : null;

            return $this->response->setJSON([
                'id'             => (int) $customer['id'],
                'name'           => up($customer['cl_organization_name']),
                'school_status'  => $customer['school_status'],
                'contacts'       => $contacts,
                'rep_ids'        => $repIds,
                'default_rep_id' => $myId !== null && in_array($myId, $repIds, true) ? $myId : ($repIds[0] ?? $myId),
            ]);
        } catch (Throwable $e) {
            return $this->jsonError($e, 'Customer detail lookup');
        }
    }

    /**
     * Quick-add a school from the proposal form (POST, JSON). A sales user's school is assigned to their sales rep
     * (created when they have none), so they can see it afterwards; admin/finance only when they already are a rep.
     * The response always carries the new CSRF token (tokens regenerate after every POST).
     *
     * @return ResponseInterface
     */
    public function quickCustomer(): ResponseInterface
    {
        $fields = [
            'cl_organization_name', 'npsn', 'school_level_id', 'school_status', 'cl_phone',
            'address_line', 'province', 'city', 'district', 'postal_code', 'contact_name', 'contact_phone', 'contact_position',
        ];

        try {
            // Scoped (sales) users must be assigned to see the school; admin/finance keep their own rep if they have one.
            $scope    = $this->scope();
            $repId    = $scope->seesAll() ? $scope->repId() : $scope->ensureRep();
            $id       = (new \App\Services\CustomerService())->quickAdd($this->postFields($fields), $repId, $scope->seesAll());
            $customer = (new CustomerModel())->find($id);

            return $this->response->setJSON([
                'id'            => $id,
                'name'          => up($customer['cl_organization_name']),
                'npsn'          => $customer['npsn'],
                'school_status' => $customer['school_status'],
                'csrf'          => csrf_hash(),
            ]);
        } catch (\App\Services\ValidationFailed $e) {
            return $this->response->setStatusCode(422)->setJSON(['errors' => $e->errors, 'csrf' => csrf_hash()]);
        } catch (Throwable $e) {
            log_message('error', 'Quick-add customer failed: {message}', ['message' => $e->getMessage()]);

            return $this->response->setStatusCode(500)->setJSON(['errors' => ['_' => lang('Js.schoolNotSaved')], 'csrf' => csrf_hash()]);
        }
    }

    /**
     * Regions for cascading dropdowns: ?parent= code (e.g. "73") returns its regencies/cities;
     * without parent, the provinces.
     *
     * @return ResponseInterface
     */
    public function regions(): ResponseInterface
    {
        try {
            $parent = trim((string) $this->request->getGet('parent'));

            if ($parent !== '' && preg_match('/^\d{2}(\.\d{2}){0,3}$/', $parent) !== 1) {
                return $this->response->setStatusCode(400)->setJSON(['error' => lang('Customers.invalidRegion')]);
            }

            $model = new RegionModel();
            $rows  = $parent === '' ? $model->provinces() : $model->children($parent);

            return $this->response->setJSON(['results' => $rows])->setHeader('Cache-Control', 'private, max-age=3600');
        } catch (Throwable $e) {
            return $this->jsonError($e, 'Region lookup');
        }
    }

    /**
     * Log and return a generic JSON error.
     *
     * @param Throwable $e       The error.
     * @param string    $context What was being done.
     *
     * @return ResponseInterface
     */
    private function jsonError(Throwable $e, string $context): ResponseInterface
    {
        log_message('error', '{context} failed: {message}', ['context' => $context, 'message' => $e->getMessage()]);

        return $this->response->setStatusCode(500)->setJSON(['error' => lang('Js.searchFailed')]);
    }
}
