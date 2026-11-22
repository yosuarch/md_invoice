<?php

declare(strict_types=1);

use App\Models\CustomerModel;
use App\Models\InvoiceItemModel;
use App\Models\ProductModel;
use App\Models\ProposalItemModel;
use App\Services\InvoiceService;
use App\Services\ProductService;
use App\Services\ProposalService;
use App\Services\ValidationFailed;
use Tests\Support\AppTestCase;

/**
 * Product variants (own SKU and price), per-line descriptions copied proposal → invoice, lower-case storage of names
 * and the screen language files.
 *
 * @internal
 */
final class ProductVariantsTest extends AppTestCase
{
    private int $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->createUser('admin');
    }

    public function testVariantGetsSkuAndPriceAndIsCopiedWithDescription(): void
    {
        $productId = $this->createProduct('GOODS', '50000');
        $sku       = (new ProductModel())->find($productId)['pl_sku'];
        (new ProductModel())->update($productId, ['id' => $productId, 'pl_sku' => $sku, 'pl_description' => 'Galvalum, SNI']);

        $service = new ProductService();
        $v1      = $service->saveVariant($productId, null, ['pv_name' => '  0.3 MM   x 6 M ', 'pv_price' => '75000', 'is_active' => '1'], null, false);
        $v2      = $service->saveVariant($productId, null, ['pv_name' => '0.4 mm x 6 m', 'pv_price' => '90000', 'is_active' => '1'], null, false);

        $variant = (new \App\Models\ProductVariantModel())->find($v1);
        $this->assertSame($sku . '-01', $variant['pv_sku']);
        $this->assertSame('0.3 mm x 6 m', $variant['pv_name'], 'stored lower case, spaces collapsed');
        $this->assertSame($sku . '-02', (new \App\Models\ProductVariantModel())->find($v2)['pv_sku']);

        try {
            $service->saveVariant($productId, null, ['pv_name' => '0.3 mm x 6 m', 'pv_price' => '1', 'is_active' => '1'], null, false);
            $this->fail('Duplicate variant name accepted');
        } catch (ValidationFailed $e) {
            $this->assertArrayHasKey('pv_name', $e->errors);
        }

        // The picker lists the variants instead of the product, with the product description.
        $entries = (new ProductModel())->lineDefaults(['q' => $sku], date('Y-m-d'));
        $this->assertSame([$v1, $v2], array_column($entries, 'variant_id'));
        $this->assertSame('Galvalum, SNI', $entries[0]['description']);

        $customer = $this->createCustomer();
        $proposal = (new ProposalService())->create(['customer_id' => $customer, 'cpl_proposal_title' => 'Roof'], [
            ['product_id' => $productId, 'variant_id' => $v1, 'quantity' => '2', 'unit_price' => '75000', 'item_title' => '', 'item_description' => "Galvalum, SNI\nDelivered cut to 5.8 m"],
        ], $this->admin);

        $line = (new ProposalItemModel())->forProposal($proposal)[0];
        $this->assertSame($v1, (int) $line['variant_id']);
        $this->assertSame($sku . '-01', $line['pl_sku'], 'variant SKU shown on the line');
        $this->assertStringContainsString('0.3 mm x 6 m', $line['item_title']);
        $this->assertSame("Galvalum, SNI\nDelivered cut to 5.8 m", $line['item_description']);

        $this->approve($proposal);
        $invoiceId = (new InvoiceService())->createFromProposal($proposal, $this->admin);
        $invoiceLine = (new InvoiceItemModel())->forInvoice($invoiceId)[0];
        $this->assertSame($v1, (int) $invoiceLine['variant_id']);
        $this->assertSame("Galvalum, SNI\nDelivered cut to 5.8 m", $invoiceLine['item_description']);
    }

    public function testProductWithVariantsNeedsAVariant(): void
    {
        $productId = $this->createProduct();
        (new ProductService())->saveVariant($productId, null, ['pv_name' => 'red', 'pv_price' => '1000', 'is_active' => '1'], null, false);

        $this->expectException(ValidationFailed::class);
        (new ProposalService())->create(['customer_id' => $this->createCustomer(), 'cpl_proposal_title' => 'X'], [
            ['product_id' => $productId, 'quantity' => '1', 'unit_price' => '1000'],
        ], $this->admin);
    }

    public function testNamesAreStoredLowerCaseAndShownUpperCase(): void
    {
        $id = $this->createCustomer(['cl_organization_name' => '  SMP Negeri   1 Likupang ']);

        $this->assertSame('smp negeri 1 likupang', (new CustomerModel())->find($id)['cl_organization_name']);
        $this->assertSame('SMP NEGERI 1 LIKUPANG', up('smp negeri 1 likupang'));
        $this->assertSame("jl. merdeka 1\nrt 02", normalize_name("Jl.  Merdeka 1\r\n\r\n  RT 02 "));
    }

    public function testLanguageFilesHaveTheSameKeys(): void
    {
        foreach (glob(APPPATH . 'Language/en/*.php') as $file) {
            $name = basename($file, '.php');

            if (in_array($name, ['Lookups', 'Validation'], true)) {
                continue; // English lookups come from the config; English validation from the framework
            }

            $id = APPPATH . 'Language/id/' . $name . '.php';
            $this->assertFileExists($id);
            $this->assertSame(array_keys(require $file), array_keys(require $id), "Keys of {$name}.php differ");
        }

        service('language')->setLocale('id');
        $this->assertSame('Simpan', lang('App.save'));
        service('language')->setLocale('en');
        $this->assertSame('Save', lang('App.save'));
    }

    /**
     * Send and approve a proposal.
     *
     * @param int $id Proposal id.
     *
     * @return void
     */
    private function approve(int $id): void
    {
        $service = new ProposalService();
        $service->changeStatus($id, \App\Services\ProposalWorkflow::PROPOSE, '', $this->canAll, $this->admin);
        $service->changeStatus($id, \App\Services\ProposalWorkflow::STORE_APPROVE, '', $this->canAll, $this->admin);
    }
}
