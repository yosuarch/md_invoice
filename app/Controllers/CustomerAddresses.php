<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\CustomerAddressModel;
use App\Models\CustomerModel;
use App\Models\RegionModel;
use App\Services\CustomerService;
use App\Services\ValidationFailed;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use Throwable;

/**
 * Billing/shipping addresses of a customer (permission customers.manage).
 */
class CustomerAddresses extends BaseController
{
    private const FIELDS = ['address_type', 'address_line', 'district', 'city', 'province', 'postal_code', 'is_default'];

    private CustomerService $service;

    public function __construct()
    {
        $this->service = new CustomerService();
    }

    /**
     * @param int $customerId Customer id.
     *
     * @return string
     */
    public function new(int $customerId): string
    {
        $type = strtoupper((string) $this->request->getGet('type'));

        return view('customers/address_form', [
            'customer' => $this->findOr404(new CustomerModel(), $customerId),
            'address'  => [],
            'type'     => array_key_exists($type, CustomerAddressModel::TYPES) ? $type : 'BILLING',
            'regions'  => $this->regionOptions([]),
        ]);
    }

    /**
     * @param int $customerId Customer id.
     *
     * @return RedirectResponse
     */
    public function create(int $customerId): RedirectResponse
    {
        return $this->save($customerId, null);
    }

    /**
     * @param int $customerId Customer id.
     * @param int $id         Address id.
     *
     * @return string
     */
    public function edit(int $customerId, int $id): string
    {
        $address = $this->findAddress($customerId, $id);

        return view('customers/address_form', [
            'customer' => $this->findOr404(new CustomerModel(), $customerId),
            'address'  => $address,
            'type'     => $address['address_type'],
            'regions'  => $this->regionOptions($address),
        ]);
    }

    /**
     * @param int $customerId Customer id.
     * @param int $id         Address id.
     *
     * @return RedirectResponse
     */
    public function update(int $customerId, int $id): RedirectResponse
    {
        $this->findAddress($customerId, $id);

        return $this->save($customerId, $id);
    }

    /**
     * @param int $customerId Customer id.
     * @param int $id         Address id.
     *
     * @return RedirectResponse
     */
    public function delete(int $customerId, int $id): RedirectResponse
    {
        $this->findAddress($customerId, $id);

        try {
            $this->service->deleteAddress($customerId, $id);
        } catch (Throwable $e) {
            return $this->failed($e, 'Address delete');
        }

        return redirect()->to("customers/{$customerId}#addresses")->with('message', lang('Customers.addressDeleted'));
    }

    /**
     * Create or update via the service (keeps a single default per type).
     */
    private function save(int $customerId, ?int $id): RedirectResponse
    {
        $this->findOr404(new CustomerModel(), $customerId);

        try {
            $this->service->saveAddress($customerId, $id, $this->postFields(self::FIELDS));
        } catch (ValidationFailed $e) {
            return $this->backWithErrors($e->errors);
        } catch (Throwable $e) {
            return $this->failed($e, 'Address save');
        }

        return redirect()->to("customers/{$customerId}#addresses")->with('message', lang($id === null ? 'Customers.addressAdded' : 'Customers.addressUpdated'));
    }

    /**
     * @return array<string, mixed>
     *
     * @throws PageNotFoundException
     */
    private function findAddress(int $customerId, int $id): array
    {
        $address = (new CustomerAddressModel())->where('customer_id', $customerId)->find($id);

        return is_array($address) ? $address : throw PageNotFoundException::forPageNotFound();
    }

    /**
     * Province list and the regency/city list of the selected province (old input wins over the stored value).
     *
     * @param array<string, mixed> $address Stored address (empty on create).
     *
     * @return array{provinces: list<array{code: string, name: string}>, cities: list<array{code: string, name: string}>, districts: list<array{code: string, name: string}>}
     */
    private function regionOptions(array $address): array
    {
        $regions  = new RegionModel();
        $province = (string) old('province', $address['province'] ?? '', false);
        $code     = $province !== '' ? $regions->provinceCode($province) : null;
        $cityCode = $code !== null ? $regions->childCode($code, (string) old('city', $address['city'] ?? '', false)) : null;

        return [
            'provinces' => $regions->provinces(),
            'cities'    => $code !== null ? $regions->children($code) : [],
            'districts' => $cityCode !== null ? $regions->children($cityCode) : [],
        ];
    }
}
