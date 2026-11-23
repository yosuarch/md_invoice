<?php

declare(strict_types=1);

namespace App\Libraries;

use setasign\Fpdi\Fpdi;
use Throwable;

/**
 * Joins PDF files and images into one PDF (FPDI on top of FPDF), with a small stamp on every page.
 * Used by App\Services\OrderBundle for the combined order report.
 *
 * The free FPDI parser cannot read every PDF (compressed cross-reference streams, encryption): pageCount() returns
 * null for those, and the caller adds a placeholder page instead.
 */
final class PdfMerge
{
    /** A4 portrait in mm. */
    private const A4 = [210.0, 297.0];

    /** Margin around images in mm. */
    private const MARGIN = 12.0;

    private Fpdi $pdf;

    /** Label at the top right of every page (e.g. "Laporan QUO/2026/11/0001"); empty = no stamp. */
    private string $label = '';

    /** Total pages of the merged file, for "Halaman N / M". */
    private int $total = 0;

    public function __construct()
    {
        $this->pdf = new Fpdi('P', 'mm', 'A4');
        $this->pdf->SetAutoPageBreak(false);
        $this->pdf->SetCreator('de\'makmur');
    }

    /**
     * Number of pages of a PDF file, or null when it cannot be read by the parser.
     *
     * @param string $path Absolute file path.
     *
     * @return int|null
     */
    public static function pageCount(string $path): ?int
    {
        try {
            return (new Fpdi())->setSourceFile($path);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Stamp every following page: the label at the top right and "Halaman N / M" at the bottom right, where the single
     * documents print their own page number (they are rendered without it, see Pdf::render()).
     *
     * @param string $label Label, e.g. "Laporan QUO/2026/11/0001".
     * @param int    $total Total pages of the merged file.
     *
     * @return self
     */
    public function setStamp(string $label, int $total): self
    {
        $this->label = $label;
        $this->total = $total;

        return $this;
    }

    /**
     * Append all pages of a PDF file.
     *
     * @param string $path Absolute file path (must be readable, see pageCount()).
     *
     * @return int Pages added.
     */
    public function addPdf(string $path): int
    {
        $count = $this->pdf->setSourceFile($path);

        for ($i = 1; $i <= $count; $i++) {
            $tpl  = $this->pdf->importPage($i);
            $size = $this->pdf->getTemplateSize($tpl);
            $this->pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $this->pdf->useTemplate($tpl);
            $this->stampPage();
        }

        return $count;
    }

    /**
     * Append one A4 page with the image scaled to fit (landscape page for wide images).
     *
     * @param string $path Absolute path of a JPEG or PNG file (convert other formats with toPng() first).
     *
     * @return int Pages added (1).
     */
    public function addImage(string $path): int
    {
        $info = getimagesize($path);
        [$w, $h] = [(float) ($info[0] ?? 1), (float) ($info[1] ?? 1)];
        $landscape = $w > $h;
        [$pw, $ph] = $landscape ? [self::A4[1], self::A4[0]] : self::A4;

        $scale = min(($pw - 2 * self::MARGIN) / $w, ($ph - 2 * self::MARGIN) / $h);
        [$iw, $ih] = [$w * $scale, $h * $scale];

        $this->pdf->AddPage($landscape ? 'L' : 'P', 'A4');
        $this->pdf->Image($path, ($pw - $iw) / 2, ($ph - $ih) / 2, $iw, $ih);
        $this->stampPage();

        return 1;
    }

    /**
     * Append a page with a short notice (e.g. an attachment that could not be merged).
     *
     * @param string $title Bold first line.
     * @param string $text  Explanation.
     *
     * @return int Pages added (1).
     */
    public function addNotice(string $title, string $text): int
    {
        $this->pdf->AddPage('P', 'A4');
        $this->pdf->SetTextColor(31, 42, 48);
        $this->pdf->SetFont('Helvetica', 'B', 13);
        $this->pdf->SetXY(25, 110);
        $this->pdf->MultiCell(160, 7, self::latin1($title), 0, 'C');
        $this->pdf->Ln(4);
        $this->pdf->SetX(25);
        $this->pdf->SetFont('Helvetica', '', 10);
        $this->pdf->MultiCell(160, 5.5, self::latin1($text), 0, 'C');
        $this->stampPage();

        return 1;
    }

    /**
     * The merged PDF.
     *
     * @return string PDF bytes.
     */
    public function output(): string
    {
        return $this->pdf->Output('S');
    }

    /**
     * Convert an image GD can read (WEBP, GIF, interlaced or palette PNG) to a plain PNG for FPDF.
     *
     * @param string $source Absolute path of the image.
     * @param string $target Absolute path of the PNG to write.
     *
     * @return bool False when GD cannot read the image.
     */
    public static function toPng(string $source, string $target): bool
    {
        $data = @file_get_contents($source);
        $img  = $data !== false ? @imagecreatefromstring($data) : false;

        if ($img === false) {
            return false;
        }

        imageinterlace($img, false);
        imagesavealpha($img, true);
        $ok = imagepng($img, $target);
        imagedestroy($img);

        return $ok;
    }

    /**
     * Stamp the current page: label top right, page number bottom right (on a white box, readable on scans).
     *
     * @return void
     */
    private function stampPage(): void
    {
        if ($this->label === '') {
            return;
        }

        $width  = $this->pdf->GetPageWidth();
        $height = $this->pdf->GetPageHeight();
        $label  = self::latin1($this->label);
        $number = 'Halaman ' . $this->pdf->PageNo() . ' / ' . $this->total;

        $this->pdf->SetFont('Helvetica', '', 6.5);
        $this->pdf->SetTextColor(120, 120, 120);
        $this->pdf->Text($width - 8 - $this->pdf->GetStringWidth($label), 6, $label);

        // Same place as the page number of the single documents (Dompdf: 510 pt from the left, 30 pt above the bottom on A4).
        $this->pdf->SetFont('Helvetica', '', 7);
        $this->pdf->SetFillColor(255, 255, 255);
        $x = $width - 30;
        $this->pdf->Rect($x - 1, $height - 11.6, $this->pdf->GetStringWidth($number) + 2, 4, 'F');
        $this->pdf->SetTextColor(89, 89, 89);
        $this->pdf->Text($x, $height - 8.6, $number);
    }

    /**
     * FPDF core fonts use Windows-1252: transliterate everything else.
     *
     * @param string $text UTF-8 text.
     *
     * @return string
     */
    private static function latin1(string $text): string
    {
        $converted = @iconv('UTF-8', 'windows-1252//TRANSLIT', $text);

        return $converted !== false ? $converted : (string) preg_replace('/[^\x20-\x7E]/', '?', $text);
    }
}
