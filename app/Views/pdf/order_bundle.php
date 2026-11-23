<?php
/**
 * Cover and index of the combined order report (App\Services\OrderBundle). Printed in Indonesian.
 *
 * @var array<string, mixed>       $proposal Proposal with details.
 * @var array<string, mixed>|null  $invoice  Paid invoice with details.
 * @var array<string, mixed>       $customer Customer block (PrintDocuments::customer()).
 * @var list<array<string, mixed>> $parts    title, group, type (document|attachment), page, pages, merged.
 * @var array{paid: ?string, delivered: ?string} $dates
 * @var array<string, array{from: ?string, to: ?string, days: ?float}> $leadTimes LeadTime::durations().
 * @var bool                       $hasGoods
 * @var array<string, mixed>       $company
 * @var string|null                $logo
 * @var string                     $number   Proposal number.
 */
$row = static fn (string $label, string $value): string => '<tr><td class="muted" style="width: 30%; padding: 2.5pt 0">' . esc($label)
    . '</td><td style="width: 3%; padding: 2.5pt 0">:</td><td style="padding: 2.5pt 0">' . $value . '</td></tr>';
?>
<?= $this->extend('pdf/layout') ?>

<?= $this->section('title') ?>Laporan <?= esc($number) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= pdf_part('header', ['logo' => $logo, 'title' => 'LAPORAN DOKUMEN PESANAN', 'meta' => [
    'No. Penawaran' => $number,
    'No. Invoice'   => (string) ($invoice['invoice_number'] ?? ''),
    'Dicetak'       => format_date_id(date('Y-m-d')),
]]) ?>

<div class="box" style="margin-top: 12pt">
    <table>
        <?= $row('Pelanggan', '<span class="bold">' . esc($customer['cl_organization_name'] ?? '') . '</span>'
            . (! empty($customer['npsn']) ? ' (NPSN ' . esc($customer['npsn']) . ')' : '')) ?>
        <?php if (! empty($customer['address_text'])) : ?><?= $row('Alamat', esc($customer['address_text'])) ?><?php endif ?>
        <?= $row('Perihal', esc($proposal['cpl_proposal_title'] ?? '')) ?>
        <?php if (! empty($proposal['funding_source_name'])) : ?><?= $row('Sumber dana', esc($proposal['funding_source_name'])) ?><?php endif ?>
        <?php if ($invoice !== null) : ?>
            <?= $row('Nilai invoice', '<span class="bold">' . esc(format_amount($invoice['grand_total'])) . '</span>') ?>
        <?php endif ?>
        <?php if (! empty($dates['paid'])) : ?><?= $row('Lunas', esc(format_date_id($dates['paid']))) ?><?php endif ?>
        <?php if (! empty($dates['delivered'])) : ?><?= $row('Diterima', esc(format_date_id($dates['delivered']))) ?><?php endif ?>
        <?= $row('Penjual', esc($company['company_name'] ?? '')) ?>
    </table>
</div>

<?php if (! empty($leadTimes)) : ?>
<?php
$stepLabels = [
    'preparation' => 'Penyusunan penawaran (dibuat → dikirim)',
    'approval'    => 'Negosiasi & persetujuan (dikirim → disetujui)',
    'invoicing'   => 'Penagihan (disetujui → invoice terbit)',
    'payment'     => 'Pembayaran (invoice terbit → lunas)',
    'packing'     => 'Persiapan kirim (invoice terbit → surat jalan)',
    'shipping'    => 'Pengiriman keluar (surat jalan → dikirim)',
    'transit'     => 'Dalam perjalanan (dikirim → diterima)',
    'total'       => 'Total (penawaran dibuat → pesanan selesai)',
];
$fmt   = static fn (?string $v): string => $v === null ? '-' : date('d/m/Y H:i', strtotime($v));
$units = ['day' => 'hari', 'hour' => 'jam', 'minute' => 'menit'];
$dur   = static function (?int $seconds) use ($units): string {
    $p = \App\Services\LeadTime::parts($seconds);

    return $p === null ? '-' : esc($p[0] . ' ' . $units[$p[1]]);
};
?>
<div class="section-title" style="margin-top: 12pt">Waktu proses</div>
<table class="items">
    <thead>
        <tr>
            <th>Tahap</th>
            <th style="width: 17%">Mulai</th>
            <th style="width: 17%">Selesai</th>
            <th class="num" style="width: 14%">Lama</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($stepLabels as $key => $label) : ?>
            <?php $step = $leadTimes[$key] ?? ['from' => null, 'to' => null, 'days' => null] ?>
            <?php $noGoods = ! $hasGoods && in_array($key, \App\Services\LeadTime::DELIVERY_STEPS, true) ?>
            <tr<?= $key === 'total' ? ' style="font-weight: bold"' : '' ?>>
                <td><?= esc($label) ?></td>
                <td class="small"><?= $noGoods ? '-' : esc($fmt($step['from'])) ?></td>
                <td class="small"><?= $noGoods ? '-' : esc($fmt($step['to'])) ?></td>
                <td class="num"><?= $noGoods ? '<span class="small muted">tanpa barang</span>' : ($dur($step['seconds'] ?? null)) ?></td>
            </tr>
        <?php endforeach ?>
    </tbody>
</table>
<?php endif ?>

<div class="section-title" style="margin-top: 12pt">Daftar isi</div>
<table class="items">
    <thead>
        <tr>
            <th style="width: 6%">No.</th>
            <th>Dokumen</th>
            <th style="width: 30%">Bagian dari</th>
            <th class="num" style="width: 12%">Halaman</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($parts as $i => $part) : ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td>
                    <?= $part['type'] === 'attachment' ? '<span class="muted">Lampiran:</span> ' : '' ?><?= esc($part['title']) ?>
                    <?php if (! $part['merged']) : ?><div class="small muted">Tidak dapat digabung, unduh file aslinya dari aplikasi.</div><?php endif ?>
                </td>
                <td class="small"><?= esc($part['group']) ?></td>
                <td class="num"><?= $part['pages'] > 1 ? $part['page'] . '–' . ($part['page'] + $part['pages'] - 1) : $part['page'] ?></td>
            </tr>
        <?php endforeach ?>
    </tbody>
</table>

<p class="small muted" style="margin-top: 10pt">
    Laporan ini menggabungkan dokumen pesanan yang dicetak dari aplikasi dan lampiran yang diunggah. Keaslian setiap dokumen
    dapat diperiksa melalui kode QR "pindai untuk verifikasi" pada dokumen tersebut.
</p>

<?= $this->endSection() ?>
