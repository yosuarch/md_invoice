<?php

declare(strict_types=1);

use App\Services\InvoiceWorkflow as I;
use App\Services\ProposalWorkflow as P;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Status rules of proposals and invoices.
 *
 * @internal
 */
final class WorkflowTest extends CIUnitTestCase
{
    public function testInvoiceStatusFromAmounts(): void
    {
        $this->assertSame(I::ISSUED, I::statusFor('1000.00', '0', '2026-12-01', '2026-11-22'));
        $this->assertSame(I::PARTIALLY_PAID, I::statusFor('400.00', '600.00', '2026-12-01', '2026-11-22'));
        $this->assertSame(I::OVERDUE, I::statusFor('400.00', '600.00', '2026-11-21', '2026-11-22'));
        $this->assertSame(I::ISSUED, I::statusFor('1000.00', '0', '2026-11-22', '2026-11-22'), 'due today is not overdue');
        $this->assertSame(I::PAID, I::statusFor('0.00', '1000.00', '2026-11-01', '2026-11-22'));
        $this->assertSame(I::PAID, I::statusFor('-50.00', '1050.00', '2026-12-01', '2026-11-22'), 'overpaid = paid with credit balance');
        $this->assertSame(I::CANCELLED, I::statusFor('-300.00', '300.00', '2026-12-01', '2026-11-22', true));
    }

    public function testProposalTransitions(): void
    {
        $this->assertNotNull(P::transition(P::DRAFT, P::PROPOSE));
        $this->assertNull(P::transition(P::DRAFT, P::STORE_APPROVE), 'a draft cannot be approved directly');
        $this->assertNull(P::transition(P::STORE_APPROVE, P::DRAFT));
        $this->assertSame('proposals.approve', P::transition(P::PROPOSE, P::STORE_APPROVE)['permission']);
        $this->assertSame('required', P::transition(P::PROPOSE, P::UNDER_REVIEW)['note'], 'customer request must be recorded');
        $this->assertTrue(P::isEditable(P::UNDER_REVIEW));
        $this->assertFalse(P::isEditable(P::PROPOSE));
    }

    public function testSalesCannotApprove(): void
    {
        $sales = static fn (string $p): bool => in_array($p, ['proposals.view', 'proposals.manage'], true);

        $this->assertArrayNotHasKey(P::STORE_APPROVE, P::available(P::PROPOSE, $sales));
        $this->assertArrayHasKey(P::UNDER_REVIEW, P::available(P::PROPOSE, $sales));
    }
}
