<?php

declare(strict_types=1);

use App\Libraries\PdfMerge;
use App\Services\OrderBundle;
use App\Services\OrderFlow;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Combined order report: finished-order rule, posted keys, merging of PDFs and images.
 *
 * @internal
 */
final class OrderBundleTest extends CIUnitTestCase
{
    /**
     * @param array<string, string> $states key => state
     *
     * @return array{steps: list<array{key: string, state: string}>}
     */
    private static function flow(array $states): array
    {
        $steps = [];

        foreach ($states as $key => $state) {
            $steps[] = ['key' => $key, 'state' => $state];
        }

        return ['steps' => $steps];
    }

    public function testIsComplete(): void
    {
        $this->assertTrue(OrderFlow::isComplete(self::flow(['proposal' => 'done', 'invoice' => 'done', 'payment' => 'done', 'delivery' => 'done'])));
        $this->assertTrue(OrderFlow::isComplete(self::flow(['proposal' => 'done', 'invoice' => 'done', 'payment' => 'done', 'delivery' => 'skipped'])), 'services only');
        $this->assertFalse(OrderFlow::isComplete(self::flow(['proposal' => 'done', 'invoice' => 'done', 'payment' => 'current', 'delivery' => 'done'])));
        $this->assertFalse(OrderFlow::isComplete(self::flow(['proposal' => 'done', 'invoice' => 'done', 'payment' => 'done', 'delivery' => 'current'])));
        $this->assertFalse(OrderFlow::isComplete(self::flow(['proposal' => 'done', 'invoice' => 'done', 'payment' => 'cancelled', 'delivery' => 'cancelled'])));
        $this->assertFalse(OrderFlow::isComplete(['steps' => []]));
    }

    public function testValidKeysKeepOrderAndDropForeignOrDisabled(): void
    {
        $items = [
            ['key' => 'proposal:3', 'disabled' => false],
            ['key' => 'att:7', 'disabled' => true],
            ['key' => 'invoice:3', 'disabled' => false],
            ['key' => 'att:8', 'disabled' => false],
        ];

        $this->assertSame(
            ['proposal:3', 'invoice:3', 'att:8'],
            OrderBundle::validKeys($items, ['att:8', 'invoice:3', 'att:7', 'invoice:99', 'proposal:3', ['x'], null]),
        );
        $this->assertSame([], OrderBundle::validKeys($items, []));
    }

    public function testMergePdfImagesAndNotice(): void
    {
        $dir = WRITEPATH . 'cache/zz-bundle-test-' . bin2hex(random_bytes(4));
        mkdir($dir, 0775, true);

        try {
            $img = imagecreatetruecolor(400, 200);
            imageinterlace($img, true);
            imagepng($img, $dir . '/a.png');
            imagejpeg($img, $dir . '/a.jpg');
            imagewebp($img, $dir . '/a.webp');
            file_put_contents($dir . '/bad.pdf', "%PDF-1.7\nbroken");

            $dompdf = new Dompdf\Dompdf();
            $dompdf->loadHtml('<p>one</p><div style="page-break-before: always">two</div>');
            $dompdf->render();
            file_put_contents($dir . '/a.pdf', $dompdf->output());

            $this->assertSame(2, PdfMerge::pageCount($dir . '/a.pdf'));
            $this->assertNull(PdfMerge::pageCount($dir . '/bad.pdf'));
            $this->assertTrue(PdfMerge::toPng($dir . '/a.png', $dir . '/png.png'));
            $this->assertTrue(PdfMerge::toPng($dir . '/a.webp', $dir . '/webp.png'));
            $this->assertFalse(PdfMerge::toPng($dir . '/bad.pdf', $dir . '/bad.png'));

            $merge = (new PdfMerge())->setStamp('Laporan ZZ', 6);
            $added = $merge->addPdf($dir . '/a.pdf') + $merge->addImage($dir . '/a.jpg') + $merge->addImage($dir . '/png.png')
                + $merge->addImage($dir . '/webp.png') + $merge->addNotice('rusak.pdf', 'tidak dapat digabung');
            file_put_contents($dir . '/out.pdf', $merge->output());

            $this->assertSame(6, $added);
            $this->assertSame(6, PdfMerge::pageCount($dir . '/out.pdf'));
        } finally {
            array_map('unlink', glob($dir . '/*') ?: []);
            rmdir($dir);
        }
    }
}
