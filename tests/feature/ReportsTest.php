<?php

declare(strict_types=1);

use App\Models\InvoiceWithholdingModel;
use App\Models\PaymentMethodModel;
use App\Services\AccessScope;
use App\Services\PaymentService;
use App\Services\Reports;
use Tests\Support\AppTestCase;

/**
 * Receivables aging and withholding reconciliation.
 *
 * @internal
 */
final class ReportsTest extends AppTestCase
{
    public function testAgingBuckets(): void
    {
        $this->assertSame('current', Reports::bucketFor(-5));
        $this->assertSame('current', Reports::bucketFor(0));
        $this->assertSame('d1_30', Reports::bucketFor(1));
        $this->assertSame('d31_60', Reports::bucketFor(31));
        $this->assertSame('d61_90', Reports::bucketFor(90));
        $this->assertSame('d90', Reports::bucketFor(91));
    }

    public function testReceivablesAndWithholdingReconciliation(): void
    {
        $admin   = $this->createUser('app_admin');
        $product = $this->createProduct();
        $inv     = $this->issuedInvoice($this->createCustomer(), [['product_id' => $product, 'quantity' => '30', 'unit_price' => '100000']], $admin);
        $id      = (int) $inv['id'];
        $ppn     = (new InvoiceWithholdingModel())->where(['invoice_id' => $id, 'tax_kind' => 'PPN'])->first();

        // 1,000,000 cash + PPN 330,000 collected by the treasurer, proof number not yet received.
        (new PaymentService())->record($id, [
            'payment_date' => date('Y-m-d'), 'amount' => '1000000', 'payment_method_id' => (new PaymentMethodModel())->idForCode('TRANSFER'),
        ], [['invoice_withholding_id' => $ppn['id'], 'amount' => $ppn['amount']]], $admin);

        $reports = new Reports(new AccessScope($admin));

        // Receivables 100 days after the due date.
        $later = date('Y-m-d', strtotime($inv['due_date'] . ' +100 days'));
        $recv  = $reports->receivables($later, ['q' => $inv['invoice_number']]);
        $row   = $recv['rows'][0];

        $this->assertCount(1, $recv['rows']);
        $this->assertSame(100, $row['days_past_due']);
        $this->assertSame('d90', $row['bucket']);
        $this->assertSame('2000000.00', $row['balance'], '3,330,000 − 1,000,000 − 330,000');
        $this->assertSame('45000.00', $row['taxes_pending'], 'PPh 22 not yet withheld');
        $this->assertSame('1955000.00', $row['cash_expected']);
        $this->assertSame('2000000.00', $recv['customers'][0]['d90']);

        // Withholding: PPN withheld without proof, PPh 22 not withheld yet.
        $wh     = $reports->withholding(date('Y-m-01'), date('Y-m-d'), ['q' => $inv['invoice_number']]);
        $states = array_column($wh['rows'], 'state', 'tax_kind');

        $this->assertSame(['PPN' => 'missing_proof', 'PPH22' => 'not_withheld'], $states);
        $this->assertSame('375000.00', $wh['totals']['expected']);
        $this->assertSame('330000.00', $wh['totals']['withheld']);

        // Proof recorded later -> reconciled.
        $pw = db_connect()->table('payment_withholding')->where('invoice_withholding_id', $ppn['id'])->get()->getRowArray();
        (new PaymentService())->updateProof((int) $pw['id'], 'NTPN-0001', date('Y-m-d'));

        $open = $reports->withholding(date('Y-m-01'), date('Y-m-d'), ['q' => $inv['invoice_number'], 'state' => 'open']);
        $this->assertSame(['PPH22'], array_column($open['rows'], 'tax_kind'));
    }
}
