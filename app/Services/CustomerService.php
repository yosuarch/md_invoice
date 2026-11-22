<?php

declare(strict_types=1);

namespace App\Services;

use App\Libraries\Uuid;
use App\Models\CustomerAddressModel;
use App\Models\CustomerContactModel;
use App\Models\CustomerModel;
use App\Models\CustomerSalesRepModel;
use App\Models\SalesRepModel;
use RuntimeException;

/**
 * Customer operations that touch several rows: UUID generation, sales rep assignment,
 * the single primary contact and the single default address per type. Every method runs in one transaction.
 */
final class CustomerService
{
    public function __construct(
        private readonly CustomerModel $customers = new CustomerModel(),
        private readonly CustomerContactModel $contacts = new CustomerContactModel(),
        private readonly CustomerAddressModel $addresses = new CustomerAddressModel(),
        private readonly CustomerSalesRepModel $customerReps = new CustomerSalesRepModel(),
    ) {
    }

    /**
     * Create a customer with a generated UUIDv7 (cl_id) and assigned sales reps.
     *
     * @param array<string, mixed> $data   Customer fields (cl_id is ignored).
     * @param list<int>            $repIds Sales representative ids.
     *
     * @return int New customer id.
     *
     * @throws ValidationFailed
     */
    public function create(array $data, array $repIds = []): int
    {
        $data['cl_id'] = Uuid::v7();
        $this->assertRepsExist($repIds);

        return Transaction::run(function () use ($data, $repIds): int {
            $id = $this->customers->insert($data);

            if ($id === false) {
                throw new ValidationFailed($this->customers->errors());
            }

            $this->syncReps((int) $id, $repIds);

            return (int) $id;
        });
    }

    /**
     * Quick-add a school from the proposal form: customer + optional billing address + optional contact person,
     * assigned to the given sales rep, in one transaction. All field errors are reported together.
     *
     * @param array<string, mixed> $data  cl_organization_name, npsn, school_level_id, school_status, cl_phone,
     *                                    address_line, province, city, postal_code, contact_name, contact_phone, contact_position.
     * @param int|null             $repId Sales rep the school is assigned to (null: not assigned, for users who see all schools).
     * @param bool                 $seesAll Whether the user may see all schools (changes the duplicate NPSN message).
     *
     * @return int New customer id.
     *
     * @throws ValidationFailed
     */
    public function quickAdd(array $data, ?int $repId, bool $seesAll): int
    {
        $data = array_map(static fn ($v) => is_string($v) ? trim($v) : $v, $data);

        $customer = [
            'cl_organization_name' => $data['cl_organization_name'] ?? '',
            'npsn'                 => $data['npsn'] ?? '',
            'school_level_id'      => $data['school_level_id'] ?? '',
            'school_status'        => $data['school_status'] ?? '',
            'cl_phone'             => $data['cl_phone'] ?? '',
            'is_active'            => 1,
        ];

        $errors = [];

        if (($data['contact_name'] ?? '') !== '' && ($data['contact_phone'] ?? '') === '') {
            $errors['contact_phone'] = lang('Contacts.enterPhone');
        }

        if (($data['address_line'] ?? '') === '' && (($data['province'] ?? '') !== '' || ($data['city'] ?? '') !== '')) {
            $errors['address_line'] = lang('Customers.enterStreet');
        }

        if ($customer['npsn'] !== '' && $this->customers->where('npsn', $customer['npsn'])->countAllResults() > 0) {
            $errors['npsn'] = $seesAll
                ? lang('Customers.npsnTaken')
                : lang('Customers.npsnRegistered');
        }

        if ($errors !== []) {
            throw new ValidationFailed($errors);
        }

        return Transaction::run(function () use ($customer, $data, $repId): int {
            $id = $this->create($customer, $repId !== null ? [$repId] : []);

            if (($data['address_line'] ?? '') !== '') {
                $this->saveAddress($id, null, [
                    'address_type' => 'BILLING',
                    'address_line' => $data['address_line'],
                    'province'     => $data['province'] ?? '',
                    'city'         => $data['city'] ?? '',
                    'district'     => $data['district'] ?? '',
                    'postal_code'  => $data['postal_code'] ?? '',
                    'is_default'   => 1,
                ]);
            }

            if (($data['contact_name'] ?? '') !== '') {
                $parts = preg_split('/\s+/', (string) $data['contact_name'], 2);
                $this->saveContact($id, null, [
                    'ccp_first_name'   => $parts[0],
                    'ccp_last_name'    => $parts[1] ?? '',
                    'ccp_phone_number' => $data['contact_phone'],
                    'ccp_position'     => $data['contact_position'] ?? '',
                    'is_primary'       => 1,
                ]);
            }

            return $id;
        });
    }

    /**
     * Update customer fields and sales reps (the UUID never changes).
     *
     * @param int                  $id     Customer id.
     * @param array<string, mixed> $data   Submitted fields.
     * @param list<int>            $repIds Sales representative ids.
     *
     * @return void
     *
     * @throws ValidationFailed
     */
    public function update(int $id, array $data, array $repIds = []): void
    {
        unset($data['cl_id']);
        $data['id'] = $id; // for {id} placeholders in unique rules
        $this->assertRepsExist($repIds);

        Transaction::run(function () use ($id, $data, $repIds): void {
            if (! $this->customers->update($id, $data)) {
                throw new ValidationFailed($this->customers->errors());
            }

            $this->syncReps($id, $repIds);
        });
    }

    /**
     * Assign a sales rep to a customer if not yet assigned (used when the rep is set on a proposal or invoice:
     * whoever handles a school's documents is related to that school and must be able to select it).
     * Never removes assignments; an admin can remove them on the customer page.
     *
     * @param int      $customerId Customer id.
     * @param int|null $repId      Sales rep id (null / 0 = nothing to do).
     *
     * @return void
     */
    public function assignRep(int $customerId, ?int $repId): void
    {
        if ($customerId <= 0 || $repId === null || $repId <= 0) {
            return;
        }

        $exists = $this->customerReps->where(['customer_id' => $customerId, 'sales_rep_id' => $repId])->countAllResults() > 0;

        if (! $exists) {
            $this->customerReps->insert(['customer_id' => $customerId, 'sales_rep_id' => $repId]);
        }
    }

    /**
     * Make the customer's assigned reps exactly $repIds (adds/removes pivot rows; each change is audited).
     *
     * @param int       $customerId Customer id.
     * @param list<int> $repIds     Sales representative ids.
     *
     * @return void
     */
    private function syncReps(int $customerId, array $repIds): void
    {
        $repIds  = array_values(array_unique(array_map('intval', $repIds)));
        $current = $this->customerReps->where('customer_id', $customerId)->findAll();

        foreach ($current as $row) {
            if (! in_array((int) $row['sales_rep_id'], $repIds, true)) {
                $this->customerReps->delete($row['id']);
            }
        }

        $existing = array_map('intval', array_column($current, 'sales_rep_id'));

        foreach (array_diff($repIds, $existing) as $repId) {
            $this->customerReps->insert(['customer_id' => $customerId, 'sales_rep_id' => $repId]);
        }
    }

    /**
     * @param list<int> $repIds
     *
     * @throws ValidationFailed When an id is not a sales rep.
     */
    private function assertRepsExist(array $repIds): void
    {
        if ($repIds === []) {
            return;
        }

        $found = (new SalesRepModel())->whereIn('id', $repIds)->countAllResults();

        if ($found !== count(array_unique($repIds))) {
            throw new ValidationFailed(['sales_reps' => lang('Customers.selectValidReps')]);
        }
    }

    /**
     * Soft-delete a customer that no document references.
     *
     * @param int $id Customer id.
     *
     * @return void
     *
     * @throws RuntimeException When the customer is used by proposals or invoices.
     */
    public function delete(int $id): void
    {
        if ($this->customers->isInUse($id)) {
            throw new RuntimeException(lang('Customers.inUse'));
        }

        Transaction::run(function () use ($id): void {
            foreach ($this->contacts->where('customer_id', $id)->findColumn('id') ?? [] as $contactId) {
                $this->contacts->delete($contactId);
            }

            foreach ($this->addresses->where('customer_id', $id)->findColumn('id') ?? [] as $addressId) {
                $this->addresses->delete($addressId);
            }

            $this->customers->delete($id);
        });
    }

    /**
     * Create or update a contact of a school (kept for quick-add and older callers): the ccp_* person fields are
     * stored on the shared person (contact_person), position / primary on the school link (see ContactService).
     *
     * @param int                  $customerId Customer id.
     * @param int|null             $linkId     Link id, null to create a new person + link.
     * @param array<string, mixed> $data       ccp_first_name, ccp_last_name, ccp_phone_number, ccp_email, ccp_position, is_primary, role_id.
     *
     * @return int Link id.
     *
     * @throws ValidationFailed
     */
    public function saveContact(int $customerId, ?int $linkId, array $data): int
    {
        $person = [
            'cp_first_name' => $data['ccp_first_name'] ?? '',
            'cp_last_name'  => $data['ccp_last_name'] ?? '',
            'cp_phone'      => $data['ccp_phone_number'] ?? '',
            'cp_email'      => $data['ccp_email'] ?? '',
            'cp_whatsapp'   => $data['cp_whatsapp'] ?? 1,
        ];
        $link = ['ccp_position' => $data['ccp_position'] ?? '', 'is_primary' => $data['is_primary'] ?? 0, 'role_id' => $data['role_id'] ?? ''];
        $contacts = new ContactService();

        if ($linkId === null) {
            return $contacts->addToCustomer($customerId, null, $person, $link);
        }

        $contacts->updateLink($customerId, $linkId, $link, $person);

        return $linkId;
    }

    /**
     * Remove a contact from a school (see ContactService::removeLink()).
     *
     * @param int $customerId Customer id.
     * @param int $linkId     Link id.
     *
     * @return void
     *
     * @throws RuntimeException When the contact is used on a proposal.
     */
    public function deleteContact(int $customerId, int $linkId): void
    {
        (new ContactService())->removeLink($customerId, $linkId);
    }
    /**
     * Create or update an address; keeps exactly one default address per customer and type.
     *
     * @param int                  $customerId Customer id.
     * @param int|null             $addressId  Null to create.
     * @param array<string, mixed> $data       Address fields.
     *
     * @return int Address id.
     *
     * @throws ValidationFailed
     */
    public function saveAddress(int $customerId, ?int $addressId, array $data): int
    {
        $data['customer_id'] = $customerId;
        $data['is_default']  = empty($data['is_default']) ? 0 : 1;
        $type                = (string) ($data['address_type'] ?? '');
        $current             = $addressId !== null ? $this->addresses->find($addressId) : null;
        $regionError         = $this->regionError((string) ($data['province'] ?? ''), (string) ($data['city'] ?? ''), (string) ($data['district'] ?? ''), $current);

        if ($regionError !== null) {
            throw new ValidationFailed($regionError);
        }

        return Transaction::run(function () use ($customerId, $addressId, $data, $type): int {
            $hasDefault = $this->addresses->where('customer_id', $customerId)->where('address_type', $type)
                ->where('is_default', 1)->where('id !=', $addressId ?? 0)->countAllResults() > 0;

            if (! $hasDefault) {
                $data['is_default'] = 1; // first address of a type is the default
            }

            if ($addressId === null) {
                $addressId = $this->addresses->insert($data);

                if ($addressId === false) {
                    throw new ValidationFailed($this->addresses->errors());
                }
            } elseif (! $this->addresses->update($addressId, $data + ['id' => $addressId])) {
                throw new ValidationFailed($this->addresses->errors());
            }

            if ($data['is_default'] === 1) {
                $this->clearFlag($this->addresses, 'is_default', ['customer_id' => $customerId, 'address_type' => $type], (int) $addressId);
            }

            return (int) $addressId;
        });
    }

    /**
     * Province, regency/city and district (kecamatan) must come from the region list and belong together
     * (names compared case-insensitively). Values already stored on the address (older free text) may stay unchanged.
     *
     * @param string                    $province Province name.
     * @param string                    $city     Regency/city name.
     * @param string                    $district District (kecamatan) name.
     * @param array<string, mixed>|null $current  Stored address when editing.
     *
     * @return array<string, string>|null field => message, or null when valid.
     */
    private function regionError(string $province, string $city, string $district, ?array $current): ?array
    {
        $province = trim($province);
        $city     = trim($city);
        $district = trim($district);
        $same     = static fn (string $a, ?string $b): bool => mb_strtolower($a) === mb_strtolower((string) $b);

        if ($current !== null && $same($province, $current['province']) && $same($city, $current['city']) && $same($district, $current['district'] ?? '')) {
            return null; // unchanged (may be legacy free text)
        }

        $regions = new \App\Models\RegionModel();
        $code    = $province !== '' ? $regions->provinceCode($province) : null;

        if ($province !== '' && $code === null) {
            return ['province' => lang('Customers.selectProvinceFromList')];
        }

        $cityCode = $city !== '' && $code !== null ? $regions->childCode($code, $city) : null;

        if ($city !== '' && $cityCode === null) {
            return ['city' => lang('Customers.selectCityOfProvince')];
        }

        if ($district !== '' && ($cityCode === null || $regions->childCode($cityCode, $district) === null)) {
            return ['district' => lang('Customers.selectDistrictOfCity')];
        }

        return null;
    }

    /**
     * Soft-delete an address; if it was the default, the next one of that type becomes default.
     *
     * @param int $customerId Customer id.
     * @param int $addressId  Address id.
     *
     * @return void
     */
    public function deleteAddress(int $customerId, int $addressId): void
    {
        Transaction::run(function () use ($customerId, $addressId): void {
            $address = $this->addresses->where('customer_id', $customerId)->find($addressId);

            if ($address === null) {
                return;
            }

            $this->addresses->delete($addressId);

            if ((int) $address['is_default'] === 1) {
                $next = $this->addresses->where('customer_id', $customerId)
                    ->where('address_type', $address['address_type'])->orderBy('id')->first();

                if ($next !== null) {
                    $this->addresses->update($next['id'], ['is_default' => 1]);
                }
            }
        });
    }

    /**
     * Set $flag = 0 on all sibling rows except $keepId (row by row, so each change is audited).
     *
     * @param \App\Models\BaseModel  $model  Model to update.
     * @param string                 $flag   Flag column.
     * @param array<string, mixed>   $scope  Where conditions defining the siblings.
     * @param int                    $keepId Row that keeps the flag.
     *
     * @return void
     */
    private function clearFlag(\App\Models\BaseModel $model, string $flag, array $scope, int $keepId): void
    {
        $ids = $model->where($scope)->where($flag, 1)->where('id !=', $keepId)->findColumn('id') ?? [];

        foreach ($ids as $id) {
            $model->update($id, [$flag => 0]);
        }
    }
}
