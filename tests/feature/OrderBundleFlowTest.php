<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Libraries\PdfMerge;
use App\Models\AttachmentModel;
use App\Models\PaymentMethodModel;
use App\Services\AccessScope;
use App\Services\OrderBundle;
use App\Services\PaymentService;
use RuntimeException;
use Tests\Support\AppTestCase;

/**
 * Combined order report on a real order: only when finished, posted keys re-checked, attachments merged.
 *
 * @internal
 */
final class OrderBundleFlowTest extends AppTestCase
{
    /** @var list<string> Files written under writable/uploads/ by this test. */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    /**
     * Store a test attachment file and its row.
     *
     * @return int Attachment id.
     */
    private function attach(string $type, int $id, string $name, string $mime, string $bytes, int $userId): int
    {
        $relative = 'attachments/zz-test-' . bin2hex(random_bytes(6)) . '.' . pathinfo($name, PATHINFO_EXTENSION);
        $absolute = WRITEPATH . 'uploads/' . $relative;

        if (! is_dir(dirname($absolute))) {
            mkdir(dirname($absolute), 0775, true);
        }

        file_put_contents($absolute, $bytes);
        $this->files[] = $absolute;

        return (int) (new AttachmentModel())->insert([
            'entity_type' => $type,
            'entity_id'   => $id,
            'file_name'   => $name,
            'file_path'   => $relative,
            'mime_type'   => $mime,
            'uploaded_by' => $userId,
        ]);
    }

    public function testBundleOfFinishedOrder(): void
    {
        $admin   = $this->createUser('app_admin');
        $service = $this->createProduct('SERVICE', '500000');
        $invoice = $this->issuedInvoice($this->createCustomer(), [['product_id' => $service, 'quantity' => '1', 'unit_price' => '500000']], $admin);
        $bundle  = new OrderBundle(new AccessScope($admin));
        $pid     = (int) $invoice['proposal_id'];

        // Not paid yet: not available.
        $this->assertFalse($bundle->options($pid)['complete']);

        try {
            $bundle->build($pid, ['proposal:' . $pid]);
            $this->fail('unfinished order must be refused');
        } catch (RuntimeException $e) {
            $this->assertSame(lang('Bundle.notComplete'), $e->getMessage());
        }

        (new PaymentService())->record((int) $invoice['id'], [
            'payment_date'      => date('Y-m-d'),
            'amount'            => $invoice['grand_total'],
            'payment_method_id' => (new PaymentMethodModel())->idForCode('TRANSFER'),
        ], [], $admin);

        $img = imagecreatetruecolor(300, 200);
        ob_start();
        imagepng($img);
        $png  = (string) ob_get_clean();
        $good = $this->attach('invoice', (int) $invoice['id'], 'foto.png', 'image/png', $png, $admin);
        $bad  = $this->attach('proposal', $pid, 'scan.pdf', 'application/pdf', "%PDF-1.7\nbroken", $admin);
        $word = $this->attach('proposal', $pid, 'old.docx', 'application/zip', 'PK', $admin);

        $options = $bundle->options($pid);
        $this->assertTrue($options['complete'], 'paid, services only (no delivery)');

        $byKey = array_column($options['items'], null, 'key');
        $this->assertTrue($byKey['proposal:' . $pid]['checked']);
        $this->assertTrue($byKey['att:' . $good]['checked']);
        $this->assertTrue($byKey['att:' . $word]['disabled'], 'Word files cannot be merged');

        $result = $bundle->build($pid, ['proposal:' . $pid, 'invoice:' . $invoice['id'], 'att:' . $good, 'att:' . $bad, 'att:' . $word, 'invoice:999999']);
        $this->assertStringStartsWith('%PDF', $result['bytes']);
        $this->assertStringStartsWith('Laporan ', $result['filename']);

        $path = WRITEPATH . 'cache/zz-bundle-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, $result['bytes']);
        $this->files[] = $path;
        // cover (>= 1) + proposal (>= 1) + invoice (>= 1) + image (1) + notice for the broken scan (1); Word file and foreign key ignored
        $this->assertGreaterThanOrEqual(5, PdfMerge::pageCount($path));

        // The report is logged, appears in the order timeline, and lead times are known for the finished order.
        $this->assertSame(1, db_connect()->table('audit_log')->where(['table_name' => 'order_bundle', 'record_id' => $pid])->countAllResults());
        $codes = array_column((new \App\Services\OrderTimeline())->forProposal($pid), 'code');
        foreach (['PROPOSAL_CREATED', 'STATUS_STORE_APPROVE', 'INVOICE_ISSUED', 'PAYMENT', 'ATTACHMENT', 'BUNDLE'] as $code) {
            $this->assertContains($code, $codes);
        }
        $lead = (new \App\Services\LeadTime())->forProposal($pid);
        $this->assertNotNull($lead['total']['seconds'], 'services only: finished when paid');
        $this->assertCount(1, (new \App\Services\Reports(new AccessScope($admin)))->leadTime(date('Y-m-d'), date('Y-m-d'))['rows']);
        $this->assertCount(0, (new \App\Services\Reports(new AccessScope($this->createUser('sales'))))->leadTime(date('Y-m-d'), date('Y-m-d'))['rows'], 'scoped');

        // Verification code of the printed invoice resolves; a wrong code does not.
        $verify = new \App\Services\DocumentVerification();
        $code   = $verify->codeFor('INV', (int) $invoice['id']);
        $this->assertSame(['doc_type' => 'INV', 'doc_id' => (int) $invoice['id']], $verify->resolveCode(strtolower(\App\Services\DocumentVerification::formatCode($code))));
        $this->assertSame($code, $verify->codeFor('INV', (int) $invoice['id']), 'stable');
        $this->assertNull($verify->resolveCode('ZZZZ-ZZZZ'));

        $this->expectException(RuntimeException::class);
        $bundle->build($pid, ['att:' . $word, 'receipt:999999']);
    }
}
