<?php
/**
 * Surat Jalan (delivery note) PDF.
 *
 * @var array<string, mixed>       $note      Delivery note with details.
 * @var list<array<string, mixed>> $items
 * @var array<string, mixed>       $invoice
 * @var array<string, mixed>       $customer
 * @var array<string, mixed>|null  $agreement
 * @var array<string, mixed>       $company
 * @var string                     $number
 * @var string                     $place
 */
$shipping = trim(($note['shipment_name'] ?? '') . ($note['courier_name'] ? ' · ' . $note['courier_name'] : ''));
?>
<?= $this->extend('pdf/layout') ?>

<?= $this->section('title') ?>Surat Jalan <?= esc($number) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= pdf_part('header', ['logo' => $logo, 'title' => 'SURAT JALAN', 'meta' => [
    'No. Surat Jalan' => $number,
    'Tanggal kirim'   => format_date_id($note['ship_date']),
    'No. Invoice'     => (string) $invoice['invoice_number'],
    'No. SPK'         => (string) ($agreement['agreement_number'] ?? ''),
    'Pengiriman'      => $shipping,
    'No. Resi'        => (string) ($note['tracking_number'] ?? ''),
]]) ?>

<?= pdf_part('parties', [
    'company'   => $company,
    'customer'  => $customer,
    'toTitle'   => 'Dikirim kepada',
    'toAddress' => (string) $note['shipping_address'],
]) ?>

<p style="margin: 9pt 0 0">Mohon diterima barang-barang berikut ini:</p>

<?= pdf_part('goods', ['items' => $items, 'lastTitle' => 'Keterangan', 'lastValue' => '']) ?>

<?php if (! empty($note['note'])) : ?>
    <div class="section-title">Catatan</div>
    <div><?= nl2br(esc($note['note'])) ?></div>
<?php endif ?>

<p class="small" style="margin: 10pt 0 0">Barang telah diperiksa dan diterima dalam keadaan baik dan lengkap. Keluhan setelah barang diterima tidak dapat dilayani kecuali dicatat pada surat jalan ini.</p>

<div class="keep">
    <?= pdf_part('signatures', ['qr' => $qr, 'place' => $place, 'date' => $note['ship_date'], 'blocks' => [
        ['title' => 'Penerima', 'position' => $customer['cl_organization_name'], 'name' => (string) ($note['received_by_name'] ?? '')],
        ['title' => 'Pengemudi / kurir', 'position' => (string) ($note['courier_name'] ?? ''), 'name' => ''],
        ['title' => 'Pengirim', 'position' => $company['company_name'], 'name' => $company['signatory_name'] ?? ''],
    ]]) ?>
</div>

<?= $this->endSection() ?>
