<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Database\Seeds\LookupSeeder;
use App\Database\Seeds\NumberingSeeder;
use App\Libraries\Uuid;
use App\Models\CustomerModel;
use App\Models\FundingSourceModel;
use App\Models\InvoiceModel;
use App\Models\ProductModel;
use App\Models\TaxRateModel;
use App\Models\UnitModel;
use App\Services\CalcSettings;
use App\Services\DocumentSequence;
use App\Services\InvoiceService;
use App\Services\ProposalService;
use App\Services\ProposalWorkflow;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Base class for feature tests on the test database (test_md_invoice, see Config\Database): all migrations
 * (app + Shield) are run once per test class on a fresh schema and the lookups are seeded.
 * Helpers build the usual documents through the real services.
 *
 * @internal
 */
abstract class AppTestCase extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = true;
    protected $refresh     = true;
    protected $namespace   = null;
    protected $seed        = [LookupSeeder::class, NumberingSeeder::class];
    protected $seedOnce    = true;

    /** Permission checker that allows everything (service calls on behalf of an admin). */
    protected \Closure $canAll;

    protected function setUp(): void
    {
        parent::setUp();

        DocumentSequence::clearCache();
        CalcSettings::clearCache();
        $this->canAll = static fn (string $permission): bool => true;
    }

    /**
     * Staff user in one Shield group.
     *
     * @param string $group admin | sales | finance
     *
     * @return int User id.
     */
    protected function createUser(string $group): int
    {
        $users = auth()->getProvider();
        $name  = $group . bin2hex(random_bytes(3));
        $user  = new User(['username' => $name, 'email' => $name . '@example.test', 'password' => 'Test-Password-123!']);
        $users->save($user);

        $user = $users->findById($users->getInsertID());
        $user->syncGroups($group);

        return (int) $user->id;
    }

    /**
     * Customer (school).
     *
     * @param array<string, mixed> $data Overrides (school_status, ...).
     *
     * @return int
     */
    protected function createCustomer(array $data = []): int
    {
        return (int) (new CustomerModel())->insert($data + [
            'cl_id'                => Uuid::v7(),
            'cl_organization_name' => 'Test School ' . bin2hex(random_bytes(3)),
            'school_status'        => 'PUBLIC',
            'is_active'            => 1,
        ]);
    }

    /**
     * Product with the current PPN rate.
     *
     * @param string $type  GOODS | SERVICE
     * @param string $price Default price.
     *
     * @return int
     */
    protected function createProduct(string $type = 'GOODS', string $price = '100000'): int
    {
        $model = new ProductModel();
        $id    = $model->insert([
            'pl_sku'      => (new DocumentSequence())->next('SKU'),
            'pl_title'    => 'Test ' . strtolower($type) . ' ' . bin2hex(random_bytes(2)),
            'pl_type'     => $type,
            'unit_id'     => (new UnitModel())->idForCode('PCS'),
            'pl_price'    => $price,
            'tax_rate_id' => (int) (new TaxRateModel())->currentFor('PPN')['id'],
            'is_active'   => 1,
        ]);

        $this->assertNotFalse($id, json_encode($model->errors()));

        return (int) $id;
    }

    /**
     * Proposal approved by an admin.
     *
     * @param int                        $customerId
     * @param list<array<string, mixed>> $items   [product_id, quantity, unit_price, discount_amount?]
     * @param int                        $userId  Creator.
     * @param string|null                $funding Funding source code (e.g. BOSP).
     *
     * @return int Proposal id.
     */
    protected function approvedProposal(int $customerId, array $items, int $userId, ?string $funding = null): int
    {
        $service = new ProposalService();
        $header  = ['customer_id' => $customerId, 'cpl_proposal_title' => 'Test proposal'];

        if ($funding !== null) {
            $header['funding_source_id'] = (new FundingSourceModel())->idForCode($funding);
        }

        $id = $service->create($header, $items, $userId);
        $service->changeStatus($id, ProposalWorkflow::PROPOSE, '', $this->canAll, $userId);
        $service->changeStatus($id, ProposalWorkflow::STORE_APPROVE, '', $this->canAll, $userId);

        return $id;
    }

    /**
     * Issued invoice from a new approved proposal.
     *
     * @return array<string, mixed> Invoice row with details.
     */
    protected function issuedInvoice(int $customerId, array $items, int $userId, ?string $funding = null): array
    {
        $service   = new InvoiceService();
        $invoiceId = $service->createFromProposal($this->approvedProposal($customerId, $items, $userId, $funding), $userId);

        (new InvoiceModel())->update($invoiceId, ['id' => $invoiceId, 'billing_address' => 'Jl. Test No. 1']);
        $service->issue($invoiceId, $userId);

        return (new InvoiceModel())->withDetails()->find($invoiceId);
    }

    /**
     * Fresh invoice row with status code.
     *
     * @return array<string, mixed>
     */
    protected function invoice(int $id): array
    {
        return (new InvoiceModel())->withDetails()->find($id);
    }
}
