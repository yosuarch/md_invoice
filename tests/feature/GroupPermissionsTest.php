<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\ProposalService;
use App\Services\ProposalWorkflow;
use RuntimeException;
use Tests\Support\AppTestCase;

/**
 * Staff groups after the admin split: app_admin (system + everything), management (oversight + approvals),
 * admin (business administration without approvals).
 *
 * @internal
 */
final class GroupPermissionsTest extends AppTestCase
{
    /**
     * Permissions each group must and must not have.
     *
     * @return void
     */
    public function testGroupMatrix(): void
    {
        $users = auth()->getProvider();
        $user  = fn (string $group) => $users->findById($this->createUser($group));

        $approvals = ['proposals.approve', 'invoices.void', 'payments.reverse', 'prices.override'];
        $system    = ['settings.system', 'users.manage', 'backups.manage', 'backups.restore', 'audit.view'];
        $business  = ['settings.manage', 'customers.manage', 'products.manage', 'proposals.manage', 'invoices.manage', 'payments.record', 'credit_notes.manage', 'deliveries.manage', 'reports.view'];

        $appAdmin = $user('app_admin');
        foreach ([...$approvals, ...$system, ...$business] as $permission) {
            $this->assertTrue($appAdmin->can($permission), "app_admin: {$permission}");
        }

        $management = $user('management');
        foreach ([...$approvals, 'records.view_all', 'reports.view', 'proposals.view', 'invoices.view', 'payments.view'] as $permission) {
            $this->assertTrue($management->can($permission), "management: {$permission}");
        }
        foreach ([...$system, 'settings.manage', 'proposals.manage', 'invoices.manage', 'payments.record', 'credit_notes.manage', 'deliveries.manage', 'customers.manage'] as $permission) {
            $this->assertFalse($management->can($permission), "management must not: {$permission}");
        }

        $admin = $user('admin');
        foreach ([...$business, 'records.view_all'] as $permission) {
            $this->assertTrue($admin->can($permission), "admin: {$permission}");
        }
        foreach ([...$approvals, ...$system] as $permission) {
            $this->assertFalse($admin->can($permission), "admin must not: {$permission}");
        }
    }

    /**
     * An administrator cannot approve a proposal; management can.
     *
     * @return void
     */
    public function testOnlyManagementApproves(): void
    {
        $users      = auth()->getProvider();
        $adminId    = $this->createUser('admin');
        $admin      = $users->findById($adminId);
        $management = $users->findById($this->createUser('management'));
        $service    = new ProposalService();

        $id = $service->create(
            ['customer_id' => $this->createCustomer(), 'cpl_proposal_title' => 'Group test'],
            [['product_id' => $this->createProduct(), 'quantity' => '1', 'unit_price' => '1000']],
            $adminId,
        );
        $service->changeStatus($id, ProposalWorkflow::PROPOSE, '', static fn (string $p): bool => $admin->can($p), $adminId);

        try {
            $service->changeStatus($id, ProposalWorkflow::STORE_APPROVE, '', static fn (string $p): bool => $admin->can($p), $adminId);
            $this->fail('admin must not approve');
        } catch (RuntimeException $e) {
            $this->assertStringNotContainsString('must not', $e->getMessage());
        }

        $service->changeStatus($id, ProposalWorkflow::STORE_APPROVE, '', static fn (string $p): bool => $management->can($p), (int) $management->id);

        $status = db_connect()->table('client_proposal_list p')
            ->select('s.code')->join('client_proposal_status s', 's.id = p.status_id')
            ->where('p.id', $id)->get()->getRow('code');
        $this->assertSame(ProposalWorkflow::STORE_APPROVE, $status);
    }
}
