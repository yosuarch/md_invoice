<?php

declare(strict_types=1);

namespace App\Libraries;

use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Renders a view to an A4 PDF with Dompdf (pure PHP; uses the gd and mbstring extensions).
 * Images are embedded as data URIs (remote loading is off). Page numbers are drawn on every page.
 */
final class Pdf
{
    /**
     * Render a view to PDF bytes.
     *
     * @param string               $view   View name, e.g. 'pdf/invoice'.
     * @param array<string, mixed> $data   View data.
     * @param string               $footer      Text printed left in the footer of every page.
     * @param bool                 $pageNumbers Print "Halaman x / y" (false when the document becomes part of a combined report,
     *                                          which numbers all pages itself).
     *
     * @return string PDF bytes.
     */
    public static function render(string $view, array $data, string $footer = '', bool $pageNumbers = true): string
    {
        $cache = WRITEPATH . 'cache/dompdf';

        if (! is_dir($cache)) {
            mkdir($cache, 0775, true);
        }

        $options = new Options();
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setDefaultFont('DejaVu Sans');
        $options->setDefaultPaperSize('a4');
        $options->setChroot([FCPATH, WRITEPATH]);
        $options->setTempDir($cache);
        $options->setFontCache($cache);
        $options->setDpi(96);

        $dompdf = new Dompdf($options);
        // Fresh renderer per document: its data reaches the layout but never leaks into other documents.
        $html = Services::renderer(null, null, false)->setData($data)->render($view);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // Footer on every page: document text left, page number right (A4 = 595 x 842 pt).
        $canvas = $dompdf->getCanvas();
        $font   = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
        $color  = [0.35, 0.35, 0.35];
        $canvas->page_text(42, 812, $footer, $font, 7, $color);
        if ($pageNumbers) {
            $canvas->page_text(510, 812, 'Halaman {PAGE_NUM} / {PAGE_COUNT}', $font, 7, $color);
        }

        return $dompdf->output();
    }

    /**
     * HTTP response with the PDF, shown in the browser (inline) or downloaded.
     *
     * @param ResponseInterface $response Response object.
     * @param string            $bytes    PDF bytes.
     * @param string            $filename File name without extension (slashes are replaced).
     * @param bool              $download Force download instead of inline display.
     *
     * @return ResponseInterface
     */
    public static function respond(ResponseInterface $response, string $bytes, string $filename, bool $download): ResponseInterface
    {
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', $filename) . '.pdf';

        $response->removeHeader('Cache-Control');

        return $response->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', ($download ? 'attachment' : 'inline') . '; filename="' . $name . '"')
            // Never reuse a stored copy (phones keep showing the draft otherwise); links also carry ?v= (print_url()).
            ->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private')
            ->setHeader('Pragma', 'no-cache')
            ->setHeader('Expires', '0')
            ->setBody($bytes);
    }

    /**
     * Image file as a data URI (for <img> in PDF views).
     *
     * @param string $path Absolute file path.
     *
     * @return string|null data URI, or null when the file is missing.
     */
    public static function imageDataUri(string $path): ?string
    {
        if (! is_file($path)) {
            return null;
        }

        $info = @getimagesize($path);

        return $info === false ? null : 'data:' . $info['mime'] . ';base64,' . base64_encode((string) file_get_contents($path));
    }
}
