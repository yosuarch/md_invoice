<?php

declare(strict_types=1);

use App\Models\InvoiceItemModel;
use App\Models\InvoiceModel;
use App\Models\InvoiceWithholdingModel;
use App\Models\PaymentMethodModel;
use App\Services\CreditNoteService;
use App\Services\InvoiceService;
use App\Services\InvoiceWorkflow as W;
use App\Services\PaymentService;
use App\Services\ValidationFailed;
use Tests\Support\AppTestCase;

/**
 * Proposal → invoice → payments → credit note / void, with totals, withholding and status changes
 * (database.md §11) through the real services on the test database.
 *
 * @internal
 */
final class InvoiceFlowTest extends AppTestCase
{
    private int $admin;
    private int $goods;
    private int $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin   = $this->createUser('admin');
        $this->goods   = $this->createProduct('GOODS', '100000');
        $this->service = $this->createProduct('SERVICE', '500000');
    }

    /**
     * Public school, 30 goods × 100,000: DPP 3,000,000, PPN 330,000, total 3,330,000.
     */
    private function publicGoodsInvoice(?string $funding = null): array
    {
        return $this->issuedInvoice($this->createCustomer(), [['product_id' => $this->goods, 'quantity' => '30', 'unit_price' => '100000']], $this->admin, $funding);
    }

    private function pay(int $invoiceId, string $cash, array $withholdings = [], bool $over = false): int
    {
        return (new PaymentService())->record($invoiceId, [
            'payment_date'      => date('Y-m-d'),
            'amount'            => $cash,
            'payment_method_id' => (new PaymentMethodModel())->idForCode('TRANSFER'),
        ], $withholdings, $this->admin, $over);
    }

    public function testIssueCopiesItemsNumbersAndCalculatesWithholding(): void
    {
        $inv = $this->publicGoodsInvoice();

        $this->assertSame(W::ISSUED, $inv['status_code']);
        $this->assertMatchesRegularExpression('#^INV/' . date('Y/m') . '/\d{4}$#', $inv['invoice_number']);
        $this->assertSame('3000000.00', $inv['subtotal']);
        $this->assertSame('330000.00', $inv['tax_total']);
        $this->assertSame('3330000.00', $inv['grand_total']);
        $this->assertSame('375000.00', $inv['withholding_total'], 'PPN 330,000 collected + PPh 22 1.5% of 3,000,000');
        $this->assertCount(1, (new InvoiceItemModel())->where('invoice_id', $inv['id'])->findAll());
        $this->assertSame(['PPN', 'PPH22'], array_column((new InvoiceWithholdingModel())->where('invoice_id', $inv['id'])->orderBy('id')->findAll(), 'tax_kind'));
    }

    public function testBosFundedPurchaseHasNoPph22(): void
    {
        $inv = $this->publicGoodsInvoice('BOSP');

        $this->assertSame('330000.00', $inv['withholding_total']);
    }

    public function testInvoiceNumbersAreSequential(): void
    {
        $a = $this->publicGoodsInvoice();
        $b = $this->publicGoodsInvoice();

        $seq = static fn (array $i): int => (int) substr($i['invoice_number'], -4);
        $this->assertSame($seq($a) + 1, $seq($b));
    }

    public function testOnlyOneActiveInvoicePerProposal(): void
    {
        $proposal = $this->approvedProposal($this->createCustomer(), [['product_id' => $this->goods, 'quantity' => '1', 'unit_price' => '1000']], $this->admin);
        (new InvoiceService())->createFromProposal($proposal, $this->admin);

        $this->expectException(RuntimeException::class);
        (new InvoiceService())->createFromProposal($proposal, $this->admin);
    }

    public function testIssuedInvoiceIsLocked(): void
    {
        $inv = $this->publicGoodsInvoice();

        $this->expectException(RuntimeException::class);
        (new InvoiceService())->update((int) $inv['id'], ['billing_address' => 'Other'], []);
    }

    public function testPaymentsWithWithheldTaxesSettleTheInvoice(): void
    {
        $inv = $this->publicGoodsInvoice();
        $id  = (int) $inv['id'];

        $this->pay($id, '1000000');
        $this->assertSame(W::PARTIALLY_PAID, $this->invoice($id)['status_code']);

        $expected = (new InvoiceWithholdingModel())->where('invoice_id', $id)->findAll();
        $rows     = array_map(static fn (array $w): array => [
            'invoice_withholding_id' => $w['id'], 'amount' => $w['amount'], 'document_number' => 'NTPN-' . $w['id'], 'document_date' => date('Y-m-d'),
        ], $expected);

        // 3,330,000 − 1,000,000 − 375,000 withheld = 1,955,000 cash still open
        $this->pay($id, '1955000', $rows);

        $paid = $this->invoice($id);
        $this->assertSame(W::PAID, $paid['status_code']);
        $this->assertSame('2955000.00', $paid['amount_paid']);
        $this->assertSame('375000.00', $paid['withheld_total']);
        $this->assertSame('0.00', InvoiceModel::balance($paid));
    }

    public function testOverpaymentNeedsConfirmationAndBecomesRefundable(): void
    {
        $id = (int) $this->publicGoodsInvoice()['id'];

        try {
            $this->pay($id, '4000000');
            $this->fail('Overpayment accepted without confirmation');
        } catch (ValidationFailed $e) {
            $this->assertArrayHasKey('amount', $e->errors);
        }

        $this->pay($id, '4000000', [], true);
        $inv = $this->invoice($id);

        $this->assertSame(W::PAID, $inv['status_code']);
        $this->assertSame('670000.00', InvoiceModel::refundable($inv));
    }

    public function testReversedPaymentReopensTheInvoice(): void
    {
        $id        = (int) $this->publicGoodsInvoice()['id'];
        $paymentId = $this->pay($id, '1000000');

        (new PaymentService())->reverse($paymentId, 'Transfer bounced', $this->canAll, $this->admin);

        $inv = $this->invoice($id);
        $this->assertSame(W::ISSUED, $inv['status_code']);
        $this->assertSame('0.00', $inv['amount_paid']);
    }

    public function testVoidOnlyWithoutPayments(): void
    {
        $service = new InvoiceService();
        $paid    = (int) $this->publicGoodsInvoice()['id'];
        $this->pay($paid, '1000');

        try {
            $service->void($paid, 'Wrong customer', $this->canAll, $this->admin);
            $this->fail('Invoice with a payment was voided');
        } catch (RuntimeException) {
            $this->assertSame(W::PARTIALLY_PAID, $this->invoice($paid)['status_code']);
        }

        $open = $this->publicGoodsInvoice();
        $service->void((int) $open['id'], 'Wrong customer', $this->canAll, $this->admin);
        $this->assertSame(W::VOID, $this->invoice((int) $open['id'])['status_code']);

        // The proposal can be invoiced again after the void.
        $this->assertGreaterThan(0, $service->createFromProposal((int) $open['proposal_id'], $this->admin));
    }

    public function testFullCreditNoteCancelsTheInvoice(): void
    {
        $inv  = $this->publicGoodsInvoice();
        $item = (new InvoiceItemModel())->where('invoice_id', $inv['id'])->first();

        (new CreditNoteService())->create((int) $inv['id'], ['reason' => 'Order cancelled by school', 'reason_type' => 'CANCEL_BY_CLIENT'], [
            $item['id'] => ['quantity' => $item['quantity'], 'unit_price' => $item['unit_price']],
        ], $this->admin);

        $after = $this->invoice((int) $inv['id']);
        $this->assertSame(W::CANCELLED, $after['status_code']);
        $this->assertSame('3330000.00', $after['credit_total']);
    }

    public function testCreditNoteCannotExceedTheInvoicedQuantity(): void
    {
        $inv  = $this->publicGoodsInvoice();
        $item = (new InvoiceItemModel())->where('invoice_id', $inv['id'])->first();

        $this->expectException(ValidationFailed::class);
        (new CreditNoteService())->create((int) $inv['id'], ['reason' => 'Return', 'reason_type' => 'RETURN'], [
            $item['id'] => ['quantity' => '31', 'unit_price' => $item['unit_price']],
        ], $this->admin);
    }

    public function testServicesForPrivateSchoolOnlyPph23(): void
    {
        $inv = $this->issuedInvoice($this->createCustomer(['school_status' => 'PRIVATE']), [['product_id' => $this->service, 'quantity' => '2', 'unit_price' => '500000']], $this->admin);

        $this->assertSame('20000.00', $inv['withholding_total'], 'PPh 23 2% of 1,000,000');
    }

    public function testMarkOverdue(): void
    {
        $id = (int) $this->publicGoodsInvoice()['id'];

        (new InvoiceService())->markOverdue(date('Y-m-d', strtotime('+400 days')));

        $this->assertSame(W::OVERDUE, $this->invoice($id)['status_code']);
    }
}
