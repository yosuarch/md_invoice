<?php
/**
 * Surat Penawaran Harga (proposal / quotation) PDF.
 *
 * @var array<string, mixed>       $proposal Proposal with details (payment_term_code, due_days, shipment_name, ...).
 * @var list<array<string, mixed>> $items
 * @var array<string, mixed>       $customer
 * @var array<string, mixed>|null  $contact
 * @var array<string, mixed>       $company
 * @var string                     $number
 * @var string                     $place
 */
$contactName = $contact !== null ? trim($contact['ccp_first_name'] . ' ' . ($contact['ccp_last_name'] ?? '')) : '';
$paymentText = $proposal['payment_term_code'] === null ? '' : ((int) $proposal['due_days'] === 0
    ? 'Dibayar saat barang/jasa diterima'
    : (int) $proposal['due_days'] . ' hari setelah tanggal invoice');
$conditions = array_filter([
    'Rencana pengiriman'   => format_date_id($proposal['delivery_date']),
    'Metode pengiriman'    => (string) ($proposal['shipment_name'] ?? ''),
    'Ketentuan pembayaran' => $paymentText,
    'Sumber dana'          => (string) ($proposal['funding_source_name'] ?? ''),
    'Masa berlaku'         => $proposal['valid_until'] ? 'sampai ' . format_date_id($proposal['valid_until']) : '',
]);
?>
<?= $this->extend('pdf/layout') ?>

<?= $this->section('title') ?>Penawaran <?= esc($number) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= pdf_part('header', ['logo' => $logo, 'title' => 'SURAT PENAWARAN HARGA', 'meta' => array_filter([
    'No. Penawaran' => $number,
    'Revisi'        => (int) ($proposal['revision_no'] ?? 0) > 1 ? 'Ke-' . (int) $proposal['revision_no'] : null,
    'Tanggal'       => format_date_id($proposal['created_at']),
    'Berlaku s/d'   => format_date_id($proposal['valid_until']),
], static fn ($v): bool => $v !== null)]) ?>

<?= pdf_part('parties', [
    'company'  => $company,
    'customer' => $customer,
    'toTitle'  => 'Kepada Yth.',
    'toExtra'  => $contactName !== '' ? 'u.p. ' . $contactName . ($contact['role_label'] !== '' ? ' (' . $contact['role_label'] . ')' : '') : null,
]) ?>

<p style="margin: 9pt 0 0">Dengan hormat, bersama ini kami sampaikan penawaran harga untuk <span class="bold"><?= esc($proposal['cpl_proposal_title']) ?></span> sebagai berikut:</p>

<?= pdf_part('items', ['items' => $items]) ?>

<table style="margin-top: 6pt">
    <tr>
        <td style="width: 52%; padding-right: 12pt">
            <?php if ($conditions !== []) : ?>
                <div class="section-title" style="margin-top: 0">Ketentuan</div>
                <table>
                    <?php foreach ($conditions as $label => $value) : ?>
                        <tr><td class="muted" style="width: 42%; padding: 1pt 0"><?= esc($label) ?></td><td style="padding: 1pt 0"><?= esc($value) ?></td></tr>
                    <?php endforeach ?>
                </table>
            <?php endif ?>
            <div class="section-title">Terbilang</div>
            <div class="bold" style="font-style: italic"><?= esc(terbilang($proposal['grand_total'])) ?></div>
        </td>
        <td style="width: 48%">
            <table class="totals">
                <tr><td>Subtotal (DPP)</td><td class="right nowrap"><?= esc(format_idr($proposal['subtotal'])) ?></td></tr>
                <?php if ((float) $proposal['discount_total'] > 0) : ?>
                    <tr><td class="muted small">termasuk diskon</td><td class="right nowrap muted small"><?= esc(format_idr($proposal['discount_total'])) ?></td></tr>
                <?php endif ?>
                <tr><td>PPN</td><td class="right nowrap"><?= esc(format_idr($proposal['tax_total'])) ?></td></tr>
                <tr class="grand"><td>Total penawaran</td><td class="right nowrap"><?= esc(format_idr($proposal['grand_total'])) ?></td></tr>
            </table>
        </td>
    </tr>
</table>

<?php if (! empty($proposal['cpl_note'])) : ?>
    <div class="section-title">Catatan</div>
    <div><?= nl2br(esc($proposal['cpl_note'])) ?></div>
<?php endif ?>

<p style="margin: 8pt 0 0">Demikian penawaran ini kami sampaikan. Atas perhatian dan kerja samanya kami ucapkan terima kasih.</p>

<div class="keep">
    <?= pdf_part('signatures', ['qr' => $qr, 'verifyCode' => $verifyCode ?? null, 'verifyPage' => $verifyPage ?? null, 'place' => $place, 'date' => substr((string) $proposal['created_at'], 0, 10), 'blocks' => [
        ['title' => 'Disetujui oleh', 'name' => $contactName, 'position' => trim(($contact['role_label'] ?? '') . ' ' . $customer['cl_organization_name'])],
        ['title' => 'Hormat kami,', 'name' => $company['signatory_name'] ?? '', 'position' => trim(($company['signatory_position'] ?? '') . ' ' . $company['company_name'])],
    ]]) ?>
</div>

<?= $this->endSection() ?>
