<?php
/**
 * Invoice PDF (main document for the school's SPJ).
 *
 * @var array<string, mixed>       $invoice     Invoice with details.
 * @var list<array<string, mixed>> $items
 * @var list<array<string, mixed>> $withholding Expected withholding rows (stored, or preview for drafts).
 * @var array<string, mixed>       $customer
 * @var array<string, mixed>|null  $bank
 * @var array<string, mixed>|null  $agreement
 * @var array<string, mixed>       $company
 * @var string                     $number
 * @var string                     $place
 */

use App\Models\InvoiceModel;
use App\Services\InvoiceWorkflow;
use App\Services\WithholdingCalculator;

$isDraft  = $invoice['status_code'] === InvoiceWorkflow::DRAFT;
$whTotal  = $isDraft ? WithholdingCalculator::total($withholding) : (string) $invoice['withholding_total'];
$net      = bcsub((string) $invoice['grand_total'], $whTotal, 2);
$balance  = InvoiceModel::balance($invoice);
$received = bcadd(bcadd((string) $invoice['amount_paid'], (string) $invoice['withheld_total'], 2), (string) $invoice['credit_total'], 2);
$kinds    = ['PPN' => 'PPN dipungut bendahara', 'PPH22' => 'PPh Pasal 22', 'PPH23' => 'PPh Pasal 23', 'PPH_FINAL' => 'PPh Final UMKM (PP 55/2022)'];
$notes    = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) ($company['invoice_terms'] ?? '')))));
?>
<?= $this->extend('pdf/layout') ?>

<?= $this->section('title') ?>Invoice <?= esc($number) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= pdf_part('header', ['logo' => $logo, 'title' => 'INVOICE', 'meta' => [
    'No. Invoice'   => $number,
    'Tanggal'       => $isDraft ? '-' : format_date_id($invoice['issue_date']),
    'Jatuh tempo'   => $isDraft ? '-' : format_date_id($invoice['due_date']),
    'Ref. Penawaran' => (string) ($invoice['proposal_number'] ?? ''),
    'No. SPK'       => (string) ($agreement['agreement_number'] ?? ''),
    'Sumber dana'   => (string) ($invoice['funding_source_name'] ?? ''),
]]) ?>

<?= pdf_part('parties', [
    'company'   => $company,
    'customer'  => array_merge($customer, ['cl_tax_id' => $invoice['customer_tax_id']]),
    'toTitle'   => 'Tagihan kepada',
    'toAddress' => (string) $invoice['billing_address'],
]) ?>

<?= pdf_part('items', ['items' => $items]) ?>

<table style="margin-top: 8pt">
    <tr>
        <td style="width: 52%; padding-right: 12pt">
            <div class="section-title" style="margin-top: 0">Pembayaran</div>
            <div class="box">
                <?php if ($bank !== null) : ?>
                    Transfer ke <span class="bold"><?= esc($bank['bank_name']) ?></span><br>
                    No. rekening <span class="bold mono"><?= esc($bank['bank_account_number']) ?></span><br>
                    a.n. <?= esc($bank['bank_account_name']) ?>
                <?php else : ?>
                    Tunai / sesuai kesepakatan
                <?php endif ?>
                <div class="small muted" style="margin-top: 3pt">Mohon cantumkan nomor invoice pada keterangan pembayaran.</div>
            </div>
            <div class="section-title">Terbilang</div>
            <div class="bold" style="font-style: italic"><?= esc(terbilang($invoice['grand_total'])) ?></div>
        </td>
        <td style="width: 48%">
            <table class="totals">
                <tr><td>Subtotal (DPP)</td><td class="right nowrap"><?= esc(format_idr($invoice['subtotal'])) ?></td></tr>
                <tr><td>PPN</td><td class="right nowrap"><?= esc(format_idr($invoice['tax_total'])) ?></td></tr>
                <?php if ((float) $invoice['shipping_cost'] > 0) : ?>
                    <tr><td>Ongkos kirim</td><td class="right nowrap"><?= esc(format_idr($invoice['shipping_cost'])) ?></td></tr>
                <?php endif ?>
                <tr class="grand"><td>Total tagihan</td><td class="right nowrap"><?= esc(format_idr($invoice['grand_total'])) ?></td></tr>
                <?php if (bccomp($whTotal, '0', 2) > 0) : ?>
                    <tr><td colspan="2" class="small muted" style="padding-top: 6pt">Perkiraan pajak yang dipungut/dipotong bendahara:</td></tr>
                    <?php foreach ($withholding as $w) : ?>
                        <tr><td class="small"><?= esc($kinds[$w['tax_kind']] ?? $w['tax_kind']) ?> (<?= esc(rtrim(rtrim((string) $w['rate'], '0'), '.')) ?>%)</td><td class="right nowrap small">−<?= esc(format_idr($w['amount'])) ?></td></tr>
                    <?php endforeach ?>
                    <tr><td class="bold">Perkiraan diterima (netto)</td><td class="right nowrap bold"><?= esc(format_idr($net)) ?></td></tr>
                <?php endif ?>
                <?php if (! $isDraft && bccomp($received, '0', 2) > 0) : ?>
                    <tr><td class="small" style="padding-top: 6pt">Sudah dibayar / dipotong</td><td class="right nowrap small" style="padding-top: 6pt">−<?= esc(format_idr($received)) ?></td></tr>
                    <tr><td class="bold">Sisa tagihan</td><td class="right nowrap bold"><?= esc(format_idr(bccomp($balance, '0', 2) > 0 ? $balance : '0')) ?></td></tr>
                <?php endif ?>
            </table>
        </td>
    </tr>
</table>

<?php if ($notes !== [] || ! empty($invoice['notes'])) : ?>
    <div class="section-title">Catatan</div>
    <?php if ($notes !== []) : ?>
        <ol class="terms">
            <?php foreach ($notes as $line) : ?><li><?= esc($line) ?></li><?php endforeach ?>
        </ol>
    <?php endif ?>
    <?php if (! empty($invoice['notes'])) : ?><div style="margin-top: 3pt"><?= nl2br(esc($invoice['notes'])) ?></div><?php endif ?>
<?php endif ?>

<?= pdf_part('signatures', ['qr' => $qr, 'verifyCode' => $verifyCode ?? null, 'verifyPage' => $verifyPage ?? null, 'place' => $place, 'date' => $isDraft ? date('Y-m-d') : $invoice['issue_date'], 'blocks' => [
    ['spacer' => true],
    ['title' => $company['company_name'], 'name' => $company['signatory_name'] ?? '', 'position' => $company['signatory_position'] ?? ''],
]]) ?>

<?= $this->endSection() ?>
