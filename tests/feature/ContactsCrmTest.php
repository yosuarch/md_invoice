<?php

declare(strict_types=1);

use App\Models\ActivityModel;
use App\Models\ContactPersonModel;
use App\Models\ContactRoleModel;
use App\Models\CustomerContactModel;
use App\Services\AccessScope;
use App\Services\ActivityService;
use App\Services\ContactService;
use App\Services\CustomerService;
use App\Services\ProposalService;
use App\Services\ValidationFailed;
use Tests\Support\AppTestCase;

/**
 * Shared contacts (a person linked to several schools with a role per school) and the lean CRM
 * (activities, follow-ups, scoped visibility).
 *
 * @internal
 */
final class ContactsCrmTest extends AppTestCase
{
    private int $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->createUser('app_admin');
    }

    public function testPersonIsSharedByTwoSchoolsWithOneRolePerSchool(): void
    {
        $service   = new ContactService();
        $roles     = new ContactRoleModel();
        $schoolA   = $this->createCustomer();
        $schoolB   = $this->createCustomer();
        $treasurer = $roles->idForCode('TREASURER');

        $linkA = $service->addToCustomer($schoolA, null, ['cp_first_name' => ' Siti  ', 'cp_last_name' => 'Rahma', 'cp_phone' => '0812 3456 789', 'cp_whatsapp' => '1'], ['role_id' => $treasurer]);
        $person = (int) (new CustomerContactModel())->find($linkA)['contact_id'];
        $linkB  = $service->addToCustomer($schoolB, $person, [], ['role_id' => $roles->idForCode('FOUNDATION'), 'ccp_position' => 'Bendahara Yayasan']);

        $this->assertSame('siti', (new ContactPersonModel())->find($person)['cp_first_name'], 'names stored lower case');
        $this->assertCount(2, (new CustomerContactModel())->forPerson($person));
        $this->assertSame(1, (int) (new CustomerContactModel())->find($linkA)['is_primary'], 'first contact is primary');
        $this->assertSame('BENDAHARA YAYASAN', CustomerContactModel::roleLabel((new CustomerContactModel())->findWithPerson($linkB)));
        $this->assertSame('https://wa.me/628123456789', ContactPersonModel::whatsappUrl('0812 3456 789'));

        // Linking the same person twice to a school is refused.
        try {
            $service->addToCustomer($schoolA, $person, [], []);
            $this->fail('Duplicate link accepted');
        } catch (ValidationFailed $e) {
            $this->assertArrayHasKey('contact_id', $e->errors);
        }

        // Editing the person through school B changes it for school A too.
        $service->updateLink($schoolB, $linkB, ['role_id' => $treasurer], ['cp_first_name' => 'Siti', 'cp_last_name' => 'Rahmawati', 'cp_phone' => '0812 3456 789', 'cp_whatsapp' => '1']);
        $this->assertSame('rahmawati', (new CustomerContactModel())->findWithPerson($linkA)['ccp_last_name']);
    }

    public function testPrimaryMovesWhenThePrimaryLeavesAndUsedLinksAreKept(): void
    {
        $service  = new ContactService();
        $school   = $this->createCustomer();
        $old      = (new CustomerService())->saveContact($school, null, ['ccp_first_name' => 'Old', 'ccp_phone_number' => '0811111111']);
        $new      = (new CustomerService())->saveContact($school, null, ['ccp_first_name' => 'New', 'ccp_phone_number' => '0822222222']);
        $product  = $this->createProduct();

        $this->assertSame(1, (int) (new CustomerContactModel())->find($old)['is_primary']);

        // The old contact is used on a proposal: it cannot be removed, only marked as left.
        (new ProposalService())->create(['customer_id' => $school, 'cpl_proposal_title' => 'X', 'contact_person_id' => $old],
            [['product_id' => $product, 'quantity' => '1', 'unit_price' => '1000']], $this->admin);

        try {
            $service->removeLink($school, $old);
            $this->fail('Used contact removed');
        } catch (RuntimeException) {
            $service->updateLink($school, $old, ['ended_on' => date('Y-m-d'), 'is_primary' => '1'], null);
        }

        $links = new CustomerContactModel();
        $this->assertSame(0, (int) $links->find($old)['is_primary'], 'a person who left is never primary');
        $this->assertSame(1, (int) $links->find($new)['is_primary'], 'the next current contact becomes primary');
        $this->assertSame([$new], array_map('intval', array_column($links->forCustomer($school, true), 'id')));
        $this->assertNotNull($links->findWithPerson($old), 'old documents still find the person');
    }

    public function testActivitiesFollowUpsAndScope(): void
    {
        $sales   = $this->createUser('sales');
        $scope   = new AccessScope($sales);
        $repId   = $scope->ensureRep();
        $mine    = (new CustomerService())->quickAdd(['cl_organization_name' => 'My School', 'contact_name' => 'Budi', 'contact_phone' => '0813000000'], $repId, false);
        $other   = $this->createCustomer();
        $person  = (int) (new CustomerContactModel())->forCustomer($mine)[0]['contact_id'];
        $service = new ActivityService();

        // Log a call and plan the next follow-up in one step.
        $callId = $service->save(null, ['customer_id' => $mine, 'contact_id' => $person, 'activity_type' => 'CALL', 'subject' => 'Budget discussed',
            'occurred_at' => date('Y-m-d\TH:i', time() - 3600)], $sales, ['due_on' => date('Y-m-d', strtotime('-1 day')), 'subject' => 'Send quotation']);
        $service->save(null, ['customer_id' => $other, 'activity_type' => 'NOTE', 'subject' => 'Other school', 'occurred_at' => date('Y-m-d')], $this->admin);

        $model = new ActivityModel();
        $this->assertNotNull($model->find($callId)['done_at'], 'a logged activity is done');
        $followUp = $model->where(['customer_id' => $mine, 'done_at' => null])->first();
        $this->assertSame('Send quotation', $followUp['subject']);
        $this->assertSame($sales, (int) $followUp['assigned_to']);
        $this->assertTrue(ActivityModel::isOpen($followUp));

        // Sales sees only the activities and people of their schools.
        $scope = new AccessScope($sales);
        $this->assertCount(2, $scope->activities((new ActivityModel())->withDetails())->findAll());
        $this->assertTrue($scope->canContact($person));
        $this->assertCount(1, $scope->contacts((new ContactPersonModel())->search(''))->findAll());

        // Validation: a person of another school, a future "happened" date, no date at all.
        foreach ([
            ['customer_id' => $other, 'contact_id' => $person, 'activity_type' => 'CALL', 'subject' => 'x', 'occurred_at' => date('Y-m-d')],
            ['customer_id' => $mine, 'activity_type' => 'CALL', 'subject' => 'x', 'occurred_at' => date('Y-m-d\TH:i', strtotime('+2 days'))],
            ['customer_id' => $mine, 'activity_type' => 'CALL', 'subject' => 'x'],
        ] as $bad) {
            try {
                $service->save(null, $bad, $sales);
                $this->fail('Invalid activity accepted');
            } catch (ValidationFailed) {
                $this->addToAssertionCount(1);
            }
        }

        // Completing the follow-up records the outcome and can plan the next one.
        $service->complete((int) $followUp['id'], 'Quotation sent by WhatsApp', $sales, ['due_on' => date('Y-m-d', strtotime('+3 days'))]);
        $done = $model->find((int) $followUp['id']);
        $this->assertNotNull($done['done_at']);
        $this->assertSame('Quotation sent by WhatsApp', $done['outcome']);
        $this->assertSame(1, $model->where(['customer_id' => $mine, 'done_at' => null])->countAllResults(), 'next follow-up planned');

        $this->expectException(RuntimeException::class);
        $service->complete((int) $followUp['id'], '', $sales);
    }
}
