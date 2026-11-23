<?php

declare(strict_types=1);

use App\Models\CustomerModel;
use App\Models\InvoiceModel;
use App\Models\ProposalItemModel;
use App\Models\ProposalModel;
use App\Services\AccessScope;
use App\Services\CustomerService;
use App\Services\InvoiceService;
use App\Services\ProposalRevisions;
use App\Services\ProposalService;
use App\Services\ProposalWorkflow as W;
use App\Services\ValidationFailed;
use Tests\Support\AppTestCase;

/**
 * Record-level access (sales sees only their schools and related documents) and negotiation revisions.
 *
 * @internal
 */
final class AccessAndNegotiationTest extends AppTestCase
{
    private int $admin;
    private int $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin   = $this->createUser('app_admin');
        $this->product = $this->createProduct();
    }

    public function testSalesSeesOnlyOwnSchoolsAndTheirDocuments(): void
    {
        $sales   = $this->createUser('sales');
        $finance = $this->createUser('finance');
        $scope   = new AccessScope($sales);
        $repId   = $scope->ensureRep();

        $mine  = (new CustomerService())->quickAdd(['cl_organization_name' => 'My School', 'school_status' => 'PUBLIC'], $repId, false);
        $other = $this->createCustomer();
        $scope = new AccessScope($sales);

        $this->assertTrue($scope->canCustomer($mine));
        $this->assertFalse($scope->canCustomer($other));

        $item      = [['product_id' => $this->product, 'quantity' => '1', 'unit_price' => '1000']];
        $mineProp  = $this->approvedProposal($mine, $item, $this->admin);
        $otherProp = $this->approvedProposal($other, $item, $this->admin);
        $ownProp   = (new ProposalService())->create(['customer_id' => $other, 'cpl_proposal_title' => 'Created by sales'], $item, $sales);

        $this->assertTrue($scope->canProposal($mineProp), 'proposal of an assigned school');
        $this->assertTrue($scope->canProposal($ownProp), 'proposal the user created');
        $this->assertFalse($scope->canProposal($otherProp));

        $invoiceId = (new InvoiceService())->createFromProposal($otherProp, $this->admin);
        $this->assertFalse($scope->canInvoice($invoiceId));
        $this->assertTrue((new AccessScope($finance))->canInvoice($invoiceId), 'finance sees everything');

        $visible = array_map('intval', array_column($scope->proposals((new ProposalModel())->search([]))->findAll(), 'id'));
        sort($visible);
        $this->assertSame([$mineProp, $ownProp], $visible);
        $this->assertCount(0, $scope->invoices((new InvoiceModel())->search([]))->findAll());
    }

    public function testSalesRepOfAProposalCanSelectItsSchool(): void
    {
        $sales  = $this->createUser('sales');
        $repId  = (new AccessScope($sales))->ensureRep();
        $school = $this->createCustomer(['cl_organization_name' => 'Unassigned School']);

        $this->assertFalse((new AccessScope($sales))->canCustomer($school), 'not related yet');

        // An admin creates a proposal for the school with the sales user as its rep: the school becomes theirs.
        (new ProposalService())->create(['customer_id' => $school, 'cpl_proposal_title' => 'For the rep', 'sales_rep_id' => $repId],
            [['product_id' => $this->product, 'quantity' => '1', 'unit_price' => '1000']], $this->admin);

        $scope = new AccessScope($sales);
        $this->assertTrue($scope->canCustomer($school));
        $this->assertSame(['unassigned school'], array_column( // names are stored in lower case
$scope->customers((new CustomerModel())->search(['q' => 'Unassigned', 'status' => 'active']))->findAll(), 'cl_organization_name'));

        // The sales user can now create a new proposal for that school themselves.
        $own = (new ProposalService())->create(['customer_id' => $school, 'cpl_proposal_title' => 'Own', 'sales_rep_id' => $repId],
            [['product_id' => $this->product, 'quantity' => '1', 'unit_price' => '1000']], $sales);
        $this->assertTrue($scope->canProposal($own));
    }

    public function testOnlyWarehouseAndAdminManageDeliveryNotes(): void
    {
        $users = auth()->getProvider();
        $can   = fn (string $group): array => [
            'view'   => $users->findById($this->createUser($group))->can('deliveries.view'),
            'manage' => $users->findById($this->createUser($group))->can('deliveries.manage'),
        ];

        $this->assertSame(['view' => true, 'manage' => false], $can('sales'), 'sales only views delivery notes');
        $this->assertSame(['view' => true, 'manage' => false], $can('finance'));
        $this->assertSame(['view' => true, 'manage' => true], $can('warehouse'));
        $this->assertSame(['view' => true, 'manage' => true], $can('admin'));

        $warehouse = $users->findById($this->createUser('warehouse'));
        $this->assertTrue($warehouse->can('records.view_all'), 'warehouse ships for every school');
        $this->assertFalse($warehouse->can('invoices.manage'));
        $this->assertFalse($warehouse->can('payments.record'));
    }

    public function testQuickAddRejectsAKnownNpsnWithoutDetails(): void
    {
        $sales = $this->createUser('sales');
        $repId = (new AccessScope($sales))->ensureRep();
        $this->createCustomer(['npsn' => '40100001']);

        try {
            (new CustomerService())->quickAdd(['cl_organization_name' => 'Duplicate', 'npsn' => '40100001'], $repId, false);
            $this->fail('Duplicate NPSN accepted');
        } catch (ValidationFailed $e) {
            $this->assertStringContainsString('Ask an administrator', $e->errors['npsn']);
        }
    }

    public function testNegotiationRecordsRevisions(): void
    {
        $service  = new ProposalService();
        $customer = $this->createCustomer();
        $id       = $service->create(['customer_id' => $customer, 'cpl_proposal_title' => 'Laptops'], [
            ['product_id' => $this->product, 'quantity' => '10', 'unit_price' => '100000'],
        ], $this->admin);

        $service->changeStatus($id, W::PROPOSE, '', $this->canAll, $this->admin);
        $this->assertSame(1, (int) (new ProposalModel())->find($id)['revision_no']);

        try {
            $service->changeStatus($id, W::UNDER_REVIEW, '', $this->canAll, $this->admin);
            $this->fail('Customer request without a note');
        } catch (ValidationFailed) {
            $service->changeStatus($id, W::UNDER_REVIEW, 'Asks 10% off', $this->canAll, $this->admin);
        }

        $line = (new ProposalItemModel())->where('proposal_id', $id)->first();
        $service->update($id, ['customer_id' => $customer, 'cpl_proposal_title' => 'Laptops'], [
            ['id' => $line['id'], 'quantity' => '10', 'unit_price' => '90000', 'discount_amount' => '0'],
        ]);

        $pending = (new ProposalRevisions())->history($id)['pending'];
        $this->assertSame('-111000.00', $pending['delta'], 'total incl. PPN 1,110,000 -> 999,000');

        $service->changeStatus($id, W::PROPOSE, '10% off', $this->canAll, $this->admin);
        $service->changeStatus($id, W::STORE_APPROVE, '', $this->canAll, $this->admin);

        $history = (new ProposalRevisions())->history($id);
        $this->assertCount(2, $history['revisions'], 'approval without changes adds no revision');
        $this->assertNull($history['pending']);
        $this->assertSame('10% off', $history['revisions'][0]['note']);
        $this->assertSame('unit_price', $history['revisions'][0]['diff'][0]['changes'][0]['field']);
    }

    public function testApprovalCanBeRevokedOnlyWithoutInvoice(): void
    {
        $id = $this->approvedProposal($this->createCustomer(), [['product_id' => $this->product, 'quantity' => '1', 'unit_price' => '1000']], $this->admin);
        (new InvoiceService())->createFromProposal($id, $this->admin);

        $this->expectException(RuntimeException::class);
        (new ProposalService())->changeStatus($id, W::UNDER_REVIEW, 'Change', $this->canAll, $this->admin);
    }
}
