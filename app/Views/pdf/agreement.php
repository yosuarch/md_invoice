<?php
/**
 * Surat Perintah Kerja (SPK) / agreement PDF. PIHAK PERTAMA = store (Penyedia), PIHAK KEDUA = customer (Pemesan).
 *
 * @var array<string, mixed>       $agreement
 * @var array<string, mixed>       $proposal  Proposal with details.
 * @var list<array<string, mixed>> $items
 * @var array<string, mixed>       $customer
 * @var list<string>               $terms     Clauses.
 * @var array<string, mixed>       $company
 * @var string                     $number
 * @var string                     $place
 */

use Config\Documents;

$stamp       = bccomp((string) $proposal['grand_total'], config(Documents::class)->stampDutyThreshold, 2) > 0;
$paymentText = $proposal['payment_term_code'] === null ? 'Sesuai invoice' : ((int) $proposal['due_days'] === 0
    ? 'Dibayar saat barang/jasa diterima'
    : (int) $proposal['due_days'] . ' hari setelah tanggal invoice');
$row = static fn (string $label, string $value): string => '<tr><td class="muted" style="width: 30%; padding: 1.2pt 0">' . esc($label)
    . '</td><td style="width: 3%">:</td><td style="padding: 1.2pt 0">' . $value . '</td></tr>';
?>
<?= $this->extend('pdf/layout') ?>

<?= $this->section('title') ?>SPK <?= esc($number) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= pdf_part('header', ['logo' => $logo, 'title' => 'SURAT PERINTAH KERJA', 'meta' => [
    'Nomor'          => $number,
    'Tanggal'        => format_date_id($agreement['agreement_date']),
    'Ref. Penawaran' => $proposal['cpl_id'],
]]) ?>

<p style="margin: 12pt 0 6pt">Pada hari ini, <?= esc(date_words_id($agreement['agreement_date'])) ?>, kami yang bertanda tangan di bawah ini:</p>

<table style="margin-left: 8pt; width: 97%">
    <tr><td style="width: 14pt" class="bold">1.</td><td>
        <table>
            <?= $row('Nama', '<span class="bold">' . esc($agreement['store_signatory_name']) . '</span>') ?>
            <?= $row('Jabatan', esc(trim(($agreement['store_signatory_position'] ?? '') . ' ' . $company['company_name']))) ?>
            <?= $row('Alamat', nl2br(esc((string) ($company['address'] ?? '')))) ?>
        </table>
        <div style="margin: 2pt 0 6pt">selanjutnya disebut <span class="bold">PIHAK PERTAMA (Penyedia)</span>.</div>
    </td></tr>
    <tr><td class="bold">2.</td><td>
        <table>
            <?= $row('Nama', '<span class="bold">' . esc($agreement['client_signatory_name']) . '</span>') ?>
            <?php if (! empty($agreement['client_signatory_nip'])) : ?><?= $row('NIP', esc($agreement['client_signatory_nip'])) ?><?php endif ?>
            <?= $row('Jabatan', esc(trim(($agreement['client_signatory_position'] ?? '') . ' ' . $customer['cl_organization_name']))) ?>
            <?= $row('Alamat', esc($customer['address_text'])) ?>
        </table>
        <div style="margin: 2pt 0 6pt">selanjutnya disebut <span class="bold">PIHAK KEDUA (Pemesan)</span>.</div>
    </td></tr>
</table>

<p style="margin: 4pt 0">PIHAK KEDUA memerintahkan kepada PIHAK PERTAMA untuk melaksanakan pekerjaan dengan ketentuan sebagai berikut:</p>

<table style="margin-left: 8pt; width: 97%">
    <?= $row('Paket pekerjaan', '<span class="bold">' . esc($proposal['cpl_proposal_title']) . '</span>') ?>
    <?php if (! empty($proposal['funding_source_name'])) : ?><?= $row('Sumber dana', esc($proposal['funding_source_name'])) ?><?php endif ?>
    <?= $row('Nilai pekerjaan', '<span class="bold">' . esc(format_idr($proposal['grand_total'])) . '</span> (termasuk PPN)<br><span style="font-style: italic">' . esc(terbilang($proposal['grand_total'])) . '</span>') ?>
    <?php if (! empty($agreement['completion_date'])) : ?><?= $row('Jangka waktu', 'Diselesaikan paling lambat ' . esc(format_date_id($agreement['completion_date']))) ?><?php endif ?>
    <?= $row('Cara pembayaran', esc($paymentText)) ?>
</table>

<div class="section-title">Rincian pekerjaan</div>
<?= pdf_part('items', ['items' => $items]) ?>
<table class="totals" style="width: 48%; margin-left: auto; margin-top: 4pt">
    <tr><td>Subtotal (DPP)</td><td class="right nowrap"><?= esc(format_idr($proposal['subtotal'])) ?></td></tr>
    <tr><td>PPN</td><td class="right nowrap"><?= esc(format_idr($proposal['tax_total'])) ?></td></tr>
    <tr class="grand"><td>Nilai pekerjaan</td><td class="right nowrap"><?= esc(format_idr($proposal['grand_total'])) ?></td></tr>
</table>

<?php if ($terms !== []) : ?>
    <div class="section-title">Ketentuan</div>
    <ol class="terms">
        <?php foreach ($terms as $term) : ?><li><?= esc($term) ?></li><?php endforeach ?>
    </ol>
<?php endif ?>

<p style="margin: 8pt 0 0">Demikian Surat Perintah Kerja ini dibuat dalam rangkap 2 (dua) dan ditandatangani oleh kedua belah pihak untuk dilaksanakan sebagaimana mestinya.</p>

<div class="keep">
    <?= pdf_part('signatures', ['qr' => $qr, 'place' => $place, 'date' => $agreement['agreement_date'], 'blocks' => [
        ['title' => 'PIHAK KEDUA (Pemesan)', 'position' => trim(($agreement['client_signatory_position'] ?? '') . ' ' . $customer['cl_organization_name']), 'name' => $agreement['client_signatory_name'], 'extra' => ! empty($agreement['client_signatory_nip']) ? 'NIP ' . $agreement['client_signatory_nip'] : '', 'stamp' => $stamp],
        ['title' => 'PIHAK PERTAMA (Penyedia)', 'position' => trim(($agreement['store_signatory_position'] ?? '') . ' ' . $company['company_name']), 'name' => $agreement['store_signatory_name'], 'stamp' => $stamp],
    ]]) ?>
</div>

<?= $this->endSection() ?>
