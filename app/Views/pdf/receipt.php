<?php
/**
 * Kwitansi (receipt) of one payment. The amount is the gross settled with this payment:
 * cash received plus the taxes the treasurer withheld/collected with it.
 *
 * @var array<string, mixed>       $payment  Payment with details (method_name, bank_name, withheld, ...).
 * @var array<string, mixed>       $invoice  Invoice with details.
 * @var list<array<string, mixed>> $taxes    payment_withholding rows.
 * @var string                     $gross    Cash + withheld.
 * @var array<string, mixed>       $customer
 * @var bool                       $stampDuty Meterai needed.
 * @var array<string, mixed>       $company
 * @var string                     $number
 * @var string                     $place
 */
$kinds = ['PPN' => 'PPN dipungut bendahara', 'PPH22' => 'PPh Pasal 22', 'PPH23' => 'PPh Pasal 23', 'PPH_FINAL' => 'PPh Final UMKM'];
$row   = static fn (string $label, string $value): string => '<tr><td class="muted" style="width: 26%; padding: 4pt 0">' . esc($label)
    . '</td><td style="width: 3%; padding: 4pt 0">:</td><td style="padding: 4pt 0">' . $value . '</td></tr>';
?>
<?= $this->extend('pdf/layout') ?>

<?= $this->section('title') ?>Kwitansi <?= esc($number) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= pdf_part('header', ['logo' => $logo, 'title' => 'KWITANSI', 'meta' => [
    'No. Kwitansi' => $number,
    'Tanggal'      => format_date_id($payment['payment_date']),
    'No. Invoice'  => (string) $invoice['invoice_number'],
]]) ?>

<div class="box" style="margin-top: 12pt; padding: 8pt 12pt">
    <table>
        <?= $row('Sudah terima dari', '<span class="bold">' . esc($customer['cl_organization_name']) . '</span>') ?>
        <?= $row('Uang sejumlah', '<span class="bold" style="font-style: italic; font-size: 9.6pt">' . esc(terbilang($gross)) . '</span>') ?>
        <?= $row('Untuk pembayaran', 'Invoice No. <span class="bold">' . esc($invoice['invoice_number']) . '</span>'
            . ($invoice['proposal_number'] ? ' (penawaran ' . esc($invoice['proposal_number']) . ')' : '')
            . (! empty($invoice['funding_source_name']) ? ', sumber dana ' . esc($invoice['funding_source_name']) : '')) ?>
    </table>
</div>

<table style="margin-top: 10pt">
    <tr>
        <td style="width: 58%; padding-right: 12pt">
            <div class="section-title" style="margin-top: 0">Rincian</div>
            <table class="totals">
                <tr><td><?= esc($payment['method_name']) ?><?= $payment['bank_name'] ? ' · ' . esc($payment['bank_name']) : '' ?><?= $payment['reference_number'] ? '<div class="small muted">Ref. ' . esc($payment['reference_number']) . '</div>' : '' ?></td>
                    <td class="right nowrap"><?= esc(format_idr($payment['amount'])) ?></td></tr>
                <?php foreach ($taxes as $t) : ?>
                    <tr><td><?= esc($kinds[$t['tax_kind']] ?? $t['tax_kind']) ?><?= $t['document_number'] ? '<div class="small muted">' . esc($t['document_number']) . ($t['document_date'] ? ' · ' . esc(format_date_id($t['document_date'])) : '') . '</div>' : '' ?></td>
                        <td class="right nowrap"><?= esc(format_idr($t['amount'])) ?></td></tr>
                <?php endforeach ?>
                <tr class="grand"><td>Jumlah</td><td class="right nowrap"><?= esc(format_idr($gross)) ?></td></tr>
            </table>
            <?php if ($taxes !== []) : ?>
                <div class="small muted" style="margin-top: 3pt">Pajak dipungut/dipotong dan disetor oleh bendahara (e-billing, NTPN/bukti potong di atas).</div>
            <?php endif ?>
        </td>
        <td style="width: 42%; vertical-align: middle">
            <div class="box center" style="padding: 10pt; font-size: 15pt; font-weight: bold; background: #eef1f2"><?= esc(format_idr($gross)) ?></div>
        </td>
    </tr>
</table>

<?= pdf_part('signatures', ['qr' => $qr, 'verifyCode' => $verifyCode ?? null, 'verifyPage' => $verifyPage ?? null, 'place' => $place, 'date' => $payment['payment_date'], 'blocks' => [
    ['spacer' => true],
    ['title' => 'Yang menerima,', 'position' => $company['company_name'], 'name' => $company['signatory_name'] ?? '', 'stamp' => $stampDuty],
]]) ?>

<?= $this->endSection() ?>
