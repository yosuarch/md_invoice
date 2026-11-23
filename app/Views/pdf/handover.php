<?php
/**
 * Berita Acara Serah Terima Barang (BAST) for a delivery note. PIHAK PERTAMA = store (hands over),
 * PIHAK KEDUA = school (receives). "Mengetahui" block for the SPK signatory (e.g. the head of school) when known.
 *
 * @var array<string, mixed>       $note         Delivery note with details.
 * @var list<array<string, mixed>> $items
 * @var array<string, mixed>       $invoice
 * @var array<string, mixed>       $customer
 * @var array<string, mixed>|null  $agreement
 * @var string                     $handoverDate Y-m-d.
 * @var array<string, mixed>       $company
 * @var string                     $number
 * @var string                     $place
 */
$receiver  = (string) ($note['received_by_name'] ?? '');
$approver  = $agreement !== null && $agreement['client_signatory_name'] !== $receiver ? $agreement : null;
$row       = static fn (string $label, string $value): string => '<tr><td class="muted" style="width: 30%; padding: 1.2pt 0">' . esc($label)
    . '</td><td style="width: 3%">:</td><td style="padding: 1.2pt 0">' . $value . '</td></tr>';
$blocks    = [
    ['title' => 'PIHAK KEDUA', 'position' => 'Yang menerima', 'name' => $receiver],
    ['title' => 'PIHAK PERTAMA', 'position' => 'Yang menyerahkan', 'name' => $company['signatory_name'] ?? ''],
];

if ($approver !== null) {
    array_unshift($blocks, ['title' => 'Mengetahui,', 'position' => trim(($approver['client_signatory_position'] ?? '') . ' ' . $customer['cl_organization_name']), 'name' => $approver['client_signatory_name'], 'extra' => ! empty($approver['client_signatory_nip']) ? 'NIP ' . $approver['client_signatory_nip'] : '']);
}
?>
<?= $this->extend('pdf/layout') ?>

<?= $this->section('title') ?>BAST <?= esc($number) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= pdf_part('header', ['logo' => $logo, 'title' => 'BERITA ACARA SERAH TERIMA', 'meta' => [
    'Nomor'           => $number,
    'No. Surat Jalan' => (string) $note['delivery_number'],
    'No. Invoice'     => (string) $invoice['invoice_number'],
    'No. SPK'         => (string) ($agreement['agreement_number'] ?? ''),
]]) ?>

<p style="margin: 12pt 0 6pt">Pada hari ini, <?= esc(date_words_id($handoverDate)) ?>, kami yang bertanda tangan di bawah ini:</p>

<table style="margin-left: 8pt; width: 97%">
    <tr><td style="width: 14pt" class="bold">1.</td><td>
        <table>
            <?= $row('Nama', '<span class="bold">' . esc($company['signatory_name'] ?? '') . '</span>') ?>
            <?= $row('Jabatan', esc(trim(($company['signatory_position'] ?? '') . ' ' . $company['company_name']))) ?>
        </table>
        <div style="margin: 2pt 0 6pt">selanjutnya disebut <span class="bold">PIHAK PERTAMA</span> (yang menyerahkan).</div>
    </td></tr>
    <tr><td class="bold">2.</td><td>
        <table>
            <?= $row('Nama', $receiver !== '' ? '<span class="bold">' . esc($receiver) . '</span>' : '..................................................') ?>
            <?= $row('Instansi', esc($customer['cl_organization_name']) . (! empty($customer['npsn']) ? ' (NPSN ' . esc($customer['npsn']) . ')' : '')) ?>
            <?= $row('Alamat', esc((string) $note['shipping_address'])) ?>
        </table>
        <div style="margin: 2pt 0 6pt">selanjutnya disebut <span class="bold">PIHAK KEDUA</span> (yang menerima).</div>
    </td></tr>
</table>

<p style="margin: 4pt 0">PIHAK PERTAMA telah menyerahkan kepada PIHAK KEDUA, dan PIHAK KEDUA telah menerima dari PIHAK PERTAMA, barang sebagai berikut:</p>

<?= pdf_part('goods', ['items' => $items, 'lastTitle' => 'Kondisi', 'lastValue' => 'Baik / lengkap']) ?>

<p style="margin: 9pt 0 0">Barang tersebut telah diperiksa dan diterima dalam keadaan baik dan lengkap sesuai pesanan<?= $agreement !== null ? ' (SPK ' . esc($agreement['agreement_number']) . ')' : '' ?>.
Demikian berita acara ini dibuat dengan sebenarnya dalam rangkap 2 (dua) untuk dipergunakan sebagaimana mestinya.</p>

<div class="keep">
    <?= pdf_part('signatures', ['qr' => $qr, 'verifyCode' => $verifyCode ?? null, 'verifyPage' => $verifyPage ?? null, 'place' => $place, 'date' => $handoverDate, 'blocks' => $blocks]) ?>
</div>

<?= $this->endSection() ?>
