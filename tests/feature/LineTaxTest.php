<?php

declare(strict_types=1);

use App\Models\CompanyProfileModel;
use App\Models\InvoiceItemModel;
use App\Models\ProductModel;
use App\Models\ProposalItemModel;
use App\Models\TaxRateModel;
use App\Services\CalcSettings;
use App\Services\CreditNoteService;
use App\Services\DocumentLines;
use App\Services\DocumentTotals;
use App\Services\InvoiceService;
use App\Services\ProposalService;
use App\Services\ValidationFailed;
use Tests\Support\AppTestCase;

/**
 * Dolibarr-style pricing: tax rate per line (default from the product, changeable), prices typed incl. tax, minimum
 * price per product (prices.override needed below it).
 *
 * @internal
 */
final class LineTaxTest extends AppTestCase
{
    private int $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->createUser('admin');
    }

    public function testInclTaxConversionRounding(): void
    {
        $this->assertSame('100000.000000', DocumentTotals::exclFromIncl('111000', '11'));
        $this->assertSame('45045.050000', DocumentTotals::exclFromIncl('50000', '11'));
        $this->assertSame('50000.01', DocumentTotals::inclFromExcl('45045.05', '11'));
        $this->assertSame('50000.000000', DocumentTotals::exclFromIncl('50000', '0'));
    }

    public function testTaxCanBeChangedPerLineAndPricesTypedInclTax(): void
    {
        (new TaxRateModel())->insert(['code' => 'PPN12', 'rate' => '12.00', 'valid_from' => '2025-01-01']);
        $product  = $this->createProduct('GOODS', '100000');
        $customer = $this->createCustomer();

        $id = (new ProposalService())->create(['customer_id' => $customer, 'cpl_proposal_title' => 'Tax'], [
            ['product_id' => $product, 'quantity' => '1', 'unit_price' => '100000', 'tax_code' => ''],                              // no tax
            ['product_id' => $product, 'quantity' => '2', 'unit_price' => '112000', 'tax_code' => 'PPN12', 'price_base' => 'INCL'], // incl. 12%
            ['product_id' => $product, 'quantity' => '1', 'unit_price' => '100000'],                                                // product default (PPN 11%)
        ], $this->admin);

        $lines = (new ProposalItemModel())->forProposal($id);
        $this->assertSame(['0.00', '12.00', '11.00'], array_column($lines, 'tax_rate'));
        $this->assertSame([null, 'PPN12', 'PPN'], array_column($lines, 'tax_code'));
        $this->assertSame('100000.000000', $lines[1]['unit_price'], 'stored excl. tax');
        $this->assertSame('112000.00', $lines[1]['unit_price_incl'], 'typed price kept');
        $this->assertSame('INCL', $lines[1]['price_base']);
        $this->assertSame('24000.00', $lines[1]['tax_amount']);

        // Invalid code is refused.
        try {
            (new ProposalService())->create(['customer_id' => $customer, 'cpl_proposal_title' => 'Bad'], [
                ['product_id' => $product, 'quantity' => '1', 'unit_price' => '1', 'tax_code' => 'NOPE'],
            ], $this->admin);
            $this->fail('Unknown tax code accepted');
        } catch (ValidationFailed $e) {
            $this->assertArrayHasKey('items.1', $e->errors);
        }

        // The invoice copies the chosen tax, the price base and the typed price.
        $service = new ProposalService();
        $service->changeStatus($id, \App\Services\ProposalWorkflow::PROPOSE, '', $this->canAll, $this->admin);
        $service->changeStatus($id, \App\Services\ProposalWorkflow::STORE_APPROVE, '', $this->canAll, $this->admin);
        $invoiceLines = (new InvoiceItemModel())->forInvoice((new InvoiceService())->createFromProposal($id, $this->admin));
        $this->assertSame([null, 'PPN12', 'PPN'], array_column($invoiceLines, 'tax_code'));
        $this->assertSame('112000.00', $invoiceLines[1]['unit_price_incl']);
    }

    public function testMinimumPriceNeedsOverride(): void
    {
        $product = $this->createProduct('GOODS', '100000');
        $sku     = (new ProductModel())->find($product)['pl_sku'];
        (new ProductModel())->update($product, ['id' => $product, 'pl_sku' => $sku, 'pl_min_price' => '90000']);

        $below = [['product_id' => $product, 'quantity' => '10', 'unit_price' => '95000', 'discount_amount' => '60000']]; // net 89,000

        try {
            DocumentLines::prepare($below, [], date('Y-m-d'), false);
            $this->fail('Price below the minimum accepted');
        } catch (ValidationFailed $e) {
            $this->assertStringContainsString('minimum', $e->errors['items.1']);
        }

        $this->assertCount(1, DocumentLines::prepare($below, [], date('Y-m-d'), true), 'allowed with prices.override');
        $this->assertCount(1, DocumentLines::prepare([['product_id' => $product, 'quantity' => '1', 'unit_price' => '90000']], [], date('Y-m-d'), false));
        $this->assertTrue((new \CodeIgniter\Shield\Models\UserModel())->findById($this->admin)->can('prices.override'));
        $this->assertFalse((new \CodeIgniter\Shield\Models\UserModel())->findById($this->createUser('sales'))->can('prices.override'));
    }

    public function testNonPkpCompanyChargesNoTax(): void
    {
        (new CompanyProfileModel())->saveProfile(['company_name' => 'Test', 'is_pkp' => 0]);
        $lines = DocumentLines::prepare([['product_id' => $this->createProduct(), 'quantity' => '1', 'unit_price' => '1000', 'tax_code' => 'PPN']], [], date('Y-m-d'));

        $this->assertSame('0.00', $lines[0]['tax_rate']);
        $this->assertNull($lines[0]['tax_code']);
    }
    public function testInclTaxTotalsAreExactThroughInvoiceAndCreditNote(): void
    {
        // The reported case: 150 × Rp 190.000 incl. PPN 11% = Rp 28.500.000 (was 28.499.999,81).
        (new CompanyProfileModel())->saveProfile(['company_name' => 'Test', 'is_pkp' => 1]); // other tests of the class may change it
        $product =$this->createProduct('GOODS', '190000');
        $invoice = $this->issuedInvoice($this->createCustomer(), [
            ['product_id' => $product, 'quantity' => '150', 'unit_price' => '190000', 'price_base' => 'INCL', 'tax_code' => 'PPN'],
        ], $this->admin);

        $this->assertSame('28500000.00', $invoice['grand_total']);
        $this->assertSame('25675675.68', $invoice['subtotal']);
        $this->assertSame('2824324.32', $invoice['tax_total']);

        $line = (new InvoiceItemModel())->forInvoice((int) $invoice['id'])[0];
        $this->assertSame('171171.170000', $line['unit_price']);
        $this->assertSame('190000.00', $line['unit_price_incl']);

        // Crediting the whole line gives back exactly the incl. total.
        $cn = (new CreditNoteService())->create((int) $invoice['id'], ['reason' => 'Cancelled', 'reason_type' => 'CANCEL_BY_CLIENT'], [
            $line['id'] => ['quantity' => '150', 'unit_price' => $line['unit_price']],
        ], $this->admin);
        $this->assertSame('28500000.00', db_connect()->table('credit_note')->where('id', $cn)->get()->getRowArray()['grand_total']);
        $this->assertSame('CANCELLED', $this->invoice((int) $invoice['id'])['status_code']);
    }

    public function testCalculationSettingsAreUsed(): void
    {
        (new CompanyProfileModel())->saveProfile(['company_name' => 'Test', 'is_pkp' => 1, 'calc_amount_decimals' => 0, 'calc_unit_decimals' => 4, 'calc_tax_mode' => 'TOTAL']);
        CalcSettings::clearCache();

        $product = $this->createProduct('GOODS', '190000');
        $id      = (new ProposalService())->create(['customer_id' => $this->createCustomer(), 'cpl_proposal_title' => 'Whole rupiah'], [
            ['product_id' => $product, 'quantity' => '150', 'unit_price' => '190000', 'price_base' => 'INCL', 'tax_code' => 'PPN'],
            ['product_id' => $product, 'quantity' => '1', 'unit_price' => '10333.33', 'tax_code' => 'PPN'],
        ], $this->admin);

        $proposal = db_connect()->table('client_proposal_list')->where('id', $id)->get()->getRowArray();
        $lines    = (new ProposalItemModel())->forProposal($id);

        $this->assertSame('171171.171200', $lines[0]['unit_price'], '4 unit decimals');
        $this->assertSame(['25675676.00', '10333.00'], array_column($lines, 'line_total'));
        $this->assertSame('28511470.00', $proposal['grand_total']); // 28.500.000 + 10.333 + 1.137
        $this->assertSame('Rp 28.511.470', format_idr($proposal['grand_total']));
    }
}
