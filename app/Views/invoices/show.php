<?php
/**
 * @var array<string, mixed>                  $invoice      With customer_name, status_code, payment_term_code, rep_name, funding_source_name, proposal_number.
 * @var list<array<string, mixed>>            $items
 * @var list<array<string, mixed>>            $withholding  Stored rows (issued, with 'withheld') or a preview (draft).
 * @var list<array<string, mixed>>            $payments
 * @var array<int, list<array<string, mixed>>> $paymentTaxes Withheld taxes per payment id.
 * @var list<array<string, mixed>>            $creditNotes
 * @var list<array<string, mixed>>            $refunds
 * @var array<string, mixed>|null             $bank
 * @var array<string, mixed>|null             $method
 * @var array<string, mixed>|null             $shipment
 * @var \CodeIgniter\Shield\Entities\User|null $creator
 * @var \CodeIgniter\Shield\Entities\User|null $voidedBy
 * @var list<array<string, mixed>>            $deliveries   Delivery notes of this invoice.
 * @var array<string, mixed>                  $flow         Order tracker (App\Services\OrderFlow).
 * @var list<array<string, mixed>>            $goodsLines   Goods lines with shipped / delivered / remaining.
 * @var array{manage: bool, void: bool, pay: bool, credit: bool, reverse: bool, deliver: bool} $can
 */

use App\Models\CreditNoteModel;
use App\Models\CustomerModel;
use App\Models\InvoiceModel;
use App\Models\InvoiceWithholdingModel;
use App\Services\InvoiceWorkflow;
use App\Services\WithholdingCalculator;

$iid        = (int) $invoice['id'];
$status     = $invoice['status_code'];
$isDraft    = $status === InvoiceWorkflow::DRAFT;
$isVoid     = $status === InvoiceWorkflow::VOID;
$payable    = in_array($status, InvoiceWorkflow::PAYABLE, true);
$creditable = in_array($status, InvoiceWorkflow::CREDITABLE, true);
$number     = InvoiceModel::displayNumber($invoice);
$balance    = InvoiceModel::balance($invoice);
$refundable = InvoiceModel::refundable($invoice);
$toReclaim  = InvoiceModel::taxToReclaim($invoice);
$pastDue    = InvoiceModel::isPastDue($invoice);
$whTotal  = $isDraft ? WithholdingCalculator::total($withholding) : (string) $invoice['withholding_total'];
$netTotal = bcsub((string) $invoice['grand_total'], $whTotal, 2);
$settled  = bccomp(bcadd(bcadd((string) $invoice['amount_paid'], (string) $invoice['withheld_total'], 2), (string) $invoice['credit_total'], 2), '0', 2) > 0;
$userName = static fn ($u): string => $u !== null ? (string) ($u->full_name ? up($u->full_name) : $u->username) : '-';
$rowUser  = static fn (array $r): string => (string) (($r['full_name'] ?? '') ? up($r['full_name']) : (($r['username'] ?? '') ?: '-'));
$revUser  = static fn (array $r): string => (string) (($r['reversed_full_name'] ?? '') ? up($r['reversed_full_name']) : ($r['reversed_username'] ?? '-'));

$buttons = '';

if ($isDraft && $can['manage']) {
    $buttons .= '<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#issue-modal"><i class="bi bi-send-check me-1"></i>' . esc(lang('Invoices.issueInvoice')) . '</button>';
    $buttons .= '<a href="' . site_url("invoices/{$iid}/edit") . '" class="btn btn-outline-primary"><i class="bi bi-pencil me-1"></i>' . esc(lang('App.edit')) . '</a>';
    $buttons .= view('components/delete_button', [
        'action'  => site_url("invoices/{$iid}/delete"),
        'label'   => lang('Invoices.deleteDraft'),
        'icon'    => 'trash',
        'class'   => 'btn btn-outline-danger',
        'confirm' => lang('Invoices.deleteDraftConfirm'),
    ]);
}

if ($payable && $can['pay']) {
    $buttons .= '<a href="' . site_url("invoices/{$iid}/payments/new") . '" class="btn btn-success"><i class="bi bi-cash-coin me-1"></i>' . esc(lang('Invoices.recordPayment')) . '</a>';
}

if (bccomp($refundable, '0', 2) > 0 && $can['pay']) {
    $buttons .= '<a href="' . site_url("invoices/{$iid}/refunds/new") . '" class="btn btn-warning"><i class="bi bi-arrow-counterclockwise me-1"></i>' . esc(lang('Invoices.recordRefund')) . '</a>';
}

if ($creditable && $can['credit']) {
    $buttons .= '<a href="' . site_url("invoices/{$iid}/credit-notes/new") . '" class="btn btn-outline-primary"><i class="bi bi-arrow-return-left me-1"></i>' . esc(lang('Invoices.creditCancel')) . '</a>';
}

$toShip = array_filter($goodsLines, static fn (array $l): bool => bccomp((string) $l['remaining'], '0', 3) > 0) !== [];

if ($can['deliver'] && $toShip && in_array($status, \App\Services\DeliveryService::DELIVERABLE, true)) {
    $buttons .= '<a href="' . site_url("invoices/{$iid}/deliveries/new") . '" class="btn btn-outline-primary"><i class="bi bi-truck me-1"></i>' . esc(lang('Invoices.deliveryNote')) . '</a>';
}

$buttons .= view('components/print_menu', ['items' => [['label' => $isDraft ? lang('Orders.invoiceDraftPreview') : lang('Invoices.invoiceNo', [$number]), 'url' => site_url("invoices/{$iid}/pdf")]]]);

if ($payable && $can['void'] && ! $settled) {
    $buttons .= '<button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#void-modal"><i class="bi bi-slash-circle me-1"></i>' . esc(lang('Invoices.void')) . '</button>';
}
?>
<?= $this->extend('layouts/app_layout') ?>

<?= $this->section('title') ?><?= esc($number) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('components/page_header', [
    'title'      => ($isDraft ? lang('Invoices.draftInvoiceNo', [$iid]) : lang('Invoices.invoiceNo', [$number])),
    'subtitle'   => up($invoice['customer_name']),
    'breadcrumb' => [[lang('Invoices.invoices'), site_url('invoices')], [$number, '']],
    'actions'    => $buttons,
]) ?>

<?php if ($isDraft) : ?>
    <div class="alert alert-secondary small"><i class="bi bi-info-circle me-1"></i><?= lang('Invoices.draftInfo') ?></div>
<?php endif ?>
<?php if ($isVoid) : ?>
    <div class="alert alert-dark small"><i class="bi bi-slash-circle me-1"></i><?= esc(lang('Invoices.voidedInfo', [format_date($invoice['voided_at'], true), $userName($voidedBy)])) ?><?= $invoice['void_reason'] ? '<div class="mt-1">' . nl2br(esc($invoice['void_reason'])) . '</div>' : '' ?></div>
<?php endif ?>
<?php if ($pastDue) : ?>
    <div class="alert alert-danger small"><i class="bi bi-exclamation-octagon me-1"></i><?= lang('Invoices.pastDueInfo', [esc(format_date($invoice['due_date'])), esc(format_idr($balance))]) ?></div>
<?php endif ?>
<?php if ($payable && $can['void'] && $settled) : ?>
    <div class="small text-body-secondary mb-2"><i class="bi bi-info-circle me-1"></i><?= lang('Invoices.settledNoVoid') ?></div>
<?php endif ?>

<?= view('components/order_flow', ['flow' => $flow, 'current' => site_url("invoices/{$iid}")]) ?>

<div class="row g-3">
    <div class="col-12 col-xl-4">
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
                <span><?= lang('App.details') ?></span><?= invoice_status_badge($status) ?>
            </div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-5 text-body-secondary fw-normal"><?= lang('App.customer') ?></dt>
                    <dd class="col-7">
                        <?= customer_link($invoice['customer_id'], $invoice['customer_name']) ?>
                        <?php if ($invoice['school_status']) : ?><div class="text-body-secondary"><?= esc(code_label('schoolStatus', $invoice['school_status'])) ?><?= $invoice['npsn'] ? ' · NPSN ' . esc($invoice['npsn']) : '' ?></div><?php endif ?>
                    </dd>
                    <dt class="col-5 text-body-secondary fw-normal">NPWP</dt>
                    <dd class="col-7"><?= esc($invoice['customer_tax_id'] ?: '-') ?></dd>
                    <dt class="col-5 text-body-secondary fw-normal"><?= lang('Invoices.billingAddress') ?></dt>
                    <dd class="col-7"><?= $invoice['billing_address'] !== '' ? nl2br(esc(up($invoice['billing_address']))) : '<span class="text-danger">' . esc(lang('Invoices.missingAddress')) . '</span>' ?></dd>
                    <dt class="col-5 text-body-secondary fw-normal"><?= lang('Proposals.proposal') ?></dt>
                    <dd class="col-7"><?= $invoice['proposal_id'] ? '<a href="' . site_url('proposals/' . $invoice['proposal_id']) . '" class="font-monospace">' . esc($invoice['proposal_number']) . '</a>' : '-' ?></dd>
                    <dt class="col-5 text-body-secondary fw-normal"><?= lang('Proposals.fundingSource') ?></dt>
                    <dd class="col-7"><?= esc($invoice['funding_source_name'] ?? lang('Invoices.notKnownText')) ?><?= ! empty($invoice['is_bos']) ? ' <span class="badge bg-info-subtle text-info-emphasis border">BOS</span>' : '' ?></dd>
                    <dt class="col-5 text-body-secondary fw-normal"><?= lang('Proposals.salesRep') ?></dt>
                    <dd class="col-7"><?= esc($invoice['rep_name'] ? up($invoice['rep_name']) : '-') ?></dd>
                    <dt class="col-5 text-body-secondary fw-normal"><?= lang('Invoices.invoiceDate') ?></dt>
                    <dd class="col-7"><?= $isDraft ? '<span class="text-body-secondary">' . esc(lang('Invoices.setWhenIssued')) . '</span>' : esc(format_date($invoice['issue_date'])) ?></dd>
                    <dt class="col-5 text-body-secondary fw-normal"><?= lang('Proposals.paymentTerms') ?></dt>
                    <dd class="col-7"><?= esc($invoice['payment_term_code']) ?> (<?= lang('Customers.days', [(int) $invoice['due_days']]) ?>)</dd>
                    <dt class="col-5 text-body-secondary fw-normal"><?= lang('Invoices.dueDate') ?></dt>
                    <dd class="col-7<?= $pastDue ? ' text-danger fw-semibold' : '' ?>"><?= $isDraft ? '<span class="text-body-secondary">' . esc(lang('Invoices.setWhenIssued')) . '</span>' : esc(format_date($invoice['due_date'])) ?></dd>
                    <dt class="col-5 text-body-secondary fw-normal"><?= lang('Invoices.paymentMethod') ?></dt>
                    <dd class="col-7"><?= esc($method['name'] ?? '-') ?></dd>
                    <dt class="col-5 text-body-secondary fw-normal"><?= lang('Settings.bankAccount') ?></dt>
                    <dd class="col-7"><?= $bank !== null ? esc(up($bank['bank_name'])) . '<div class="text-body-secondary">' . esc($bank['bank_account_number']) . ' · ' . esc(up($bank['bank_account_name'])) . '</div>' : '-' ?></dd>
                    <dt class="col-5 text-body-secondary fw-normal"><?= lang('Invoices.shipment') ?></dt>
                    <dd class="col-7"><?= esc($shipment['name'] ?? '-') ?></dd>
                    <dt class="col-5 text-body-secondary fw-normal"><?= lang('App.createdBy') ?></dt>
                    <dd class="col-7 mb-0"><?= esc($userName($creator)) ?><div class="text-body-secondary"><?= esc(format_date($invoice['created_at'], true)) ?><?= $invoice['issued_at'] ? ' · ' . esc(lang('Invoices.issuedAt', [format_date($invoice['issued_at'], true)])) : '' ?></div></dd>
                </dl>
            </div>
        </div>

        <?php if (! $isDraft && ! $isVoid) : ?>
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold"><?= lang('Invoices.balance') ?></div>
                <div class="card-body small">
                    <div class="d-flex justify-content-between"><span><?= lang('Invoices.invoiceTotal') ?></span><span><?= esc(format_idr($invoice['grand_total'])) ?></span></div>
                    <?php if ((float) $invoice['credit_total'] > 0) : ?>
                        <div class="d-flex justify-content-between text-body-secondary"><span><?= lang('Invoices.creditNotes') ?></span><span>−<?= esc(format_idr($invoice['credit_total'])) ?></span></div>
                    <?php endif ?>
                    <div class="d-flex justify-content-between text-body-secondary"><span><?= lang('Invoices.cashReceived') ?></span><span>−<?= esc(format_idr($invoice['amount_paid'])) ?></span></div>
                    <div class="d-flex justify-content-between text-body-secondary"><span><?= lang('Invoices.taxesWithheld') ?></span><span>−<?= esc(format_idr($invoice['withheld_total'])) ?></span></div>
                    <?php if ((float) $invoice['refunded_total'] > 0) : ?>
                        <div class="d-flex justify-content-between text-body-secondary"><span><?= lang('Invoices.refundedToCustomer') ?></span><span>+<?= esc(format_idr($invoice['refunded_total'])) ?></span></div>
                    <?php endif ?>
                    <hr class="my-2">
                    <?php if (bccomp($balance, '0', 2) < 0) : ?>
                        <div class="d-flex justify-content-between fw-semibold fs-6"><span><?= lang('Invoices.creditBalance') ?></span><span class="text-warning-emphasis"><?= esc(format_idr(bcmul($balance, '-1', 2))) ?></span></div>
                        <div class="small mt-2">
                            <?php if (bccomp($refundable, '0', 2) > 0) : ?>
                                <div class="text-warning-emphasis"><i class="bi bi-arrow-counterclockwise me-1"></i><?= lang('Invoices.refundDueTo') ?> <strong><?= esc(format_idr($refundable)) ?></strong></div>
                            <?php endif ?>
                            <?php if (bccomp($toReclaim, '0', 2) > 0) : ?>
                                <div class="text-body-secondary mt-1"><i class="bi bi-info-circle me-1"></i><?= esc(lang('Invoices.taxReclaimInfo', [format_idr($toReclaim)])) ?></div>
                            <?php endif ?>
                        </div>
                    <?php else : ?>
                        <div class="d-flex justify-content-between fw-semibold fs-6"><span><?= lang('Invoices.openBalance') ?></span><span class="<?= bccomp($balance, '0', 2) > 0 ? 'text-danger' : 'text-success' ?>"><?= esc(format_idr($balance)) ?></span></div>
                    <?php endif ?>
                </div>
            </div>
        <?php endif ?>
    </div>

    <div class="col-12 col-xl-8">
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold"><?= lang('Proposals.items') ?></div>
            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead class="table-light small">
                        <tr>
                            <th class="ps-3">#</th>
                            <th><?= lang('App.description') ?></th>
                            <th class="text-end"><?= lang('App.quantity') ?></th>
                            <th class="text-end"><?= lang('App.unitPrice') ?></th>
                            <th class="text-end"><?= lang('App.discount') ?></th>
                            <th class="text-end"><?= lang('App.tax') ?></th>
                            <th class="text-end pe-3"><?= lang('App.lineTotal') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $i => $item) : ?>
                            <tr>
                                <td class="ps-3 text-body-secondary small"><?= $i + 1 ?></td>
                                <td>
                                    <?= esc(up($item['item_title'])) ?>
                                    <?php if (! empty($item['item_description'])) : ?><div class="small"><?= nl2br(esc($item['item_description'])) ?></div><?php endif ?>
                                    <div class="small text-body-secondary"><?= esc(implode(' · ', array_filter([$item['pl_sku'] ?? null, item_type_label($item['item_type'])]))) ?></div>
                                </td>
                                <td class="text-end text-nowrap"><?= esc(format_qty($item['quantity'])) ?> <span class="small text-body-secondary"><?= esc($item['unit_code']) ?></span></td>
                                <td class="text-end text-nowrap"><?= esc(format_price($item['unit_price'])) ?></td>
                                <td class="text-end text-nowrap"><?= (float) $item['discount_amount'] > 0 ? esc(format_idr($item['discount_amount'])) : '<span class="text-body-secondary">-</span>' ?></td>
                                <td class="text-end text-nowrap small"><?= esc(rtrim(rtrim((string) $item['tax_rate'], '0'), '.')) ?>%</td>
                                <td class="text-end text-nowrap pe-3"><?= esc(format_idr($item['line_total'])) ?></td>
                            </tr>
                        <?php endforeach ?>
                        <?php if ($items === []) : ?>
                            <tr><td colspan="7" class="text-center text-body-secondary py-4 small"><?= lang('Invoices.noItemsShort') ?></td></tr>
                        <?php endif ?>
                    </tbody>
                    <tfoot>
                        <tr><td colspan="6" class="text-end text-body-secondary small border-0 pb-0"><?= lang('App.subtotal') ?></td><td class="text-end text-nowrap pe-3 border-0 pb-0"><?= esc(format_idr($invoice['subtotal'])) ?></td></tr>
                        <?php if ((float) $invoice['discount_total'] > 0) : ?>
                            <tr><td colspan="6" class="text-end text-body-secondary small border-0 py-0"><?= lang('Proposals.inclDiscounts') ?></td><td class="text-end text-nowrap small text-body-secondary pe-3 border-0 py-0"><?= esc(format_idr($invoice['discount_total'])) ?></td></tr>
                        <?php endif ?>
                        <tr><td colspan="6" class="text-end text-body-secondary small border-0 py-0"><?= lang('Proposals.taxPpn') ?></td><td class="text-end text-nowrap pe-3 border-0 py-0"><?= esc(format_idr($invoice['tax_total'])) ?></td></tr>
                        <?php if ((float) $invoice['shipping_cost'] > 0) : ?>
                            <tr><td colspan="6" class="text-end text-body-secondary small border-0 pt-0"><?= lang('Invoices.shipping') ?></td><td class="text-end text-nowrap pe-3 border-0 pt-0"><?= esc(format_idr($invoice['shipping_cost'])) ?></td></tr>
                        <?php endif ?>
                        <tr class="table-light"><td colspan="6" class="text-end fw-semibold"><?= lang('App.grandTotal') ?></td><td class="text-end text-nowrap fw-bold pe-3"><?= esc(format_idr($invoice['grand_total'])) ?></td></tr>
                        <?php if (bccomp($whTotal, '0', 2) > 0) : ?>
                            <tr><td colspan="6" class="text-end text-body-secondary small border-0 pb-0"><?= lang('Invoices.expectedWithholding') ?></td><td class="text-end text-nowrap pe-3 border-0 pb-0">−<?= esc(format_idr($whTotal)) ?></td></tr>
                            <tr><td colspan="6" class="text-end fw-semibold border-0"><?= lang('Invoices.expectedNet') ?></td><td class="text-end text-nowrap fw-semibold pe-3 border-0"><?= esc(format_idr($netTotal)) ?></td></tr>
                        <?php endif ?>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><?= lang('Invoices.whTitle') ?></span>
                <?php if ($isDraft) : ?><span class="badge bg-secondary-subtle text-secondary-emphasis border"><?= lang('Invoices.whPreview') ?></span><?php endif ?>
            </div>
            <?php if ($withholding === []) : ?>
                <div class="card-body small text-body-secondary"><?= $isDraft ? lang('Invoices.whNoneDraft') : lang('Invoices.whNone') ?></div>
            <?php else : ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th class="ps-3"><?= lang('App.tax') ?></th>
                                <th class="text-end"><?= lang('Invoices.base') ?></th>
                                <th class="text-end"><?= lang('Invoices.rate') ?></th>
                                <th class="text-end<?= $isDraft ? ' pe-3' : '' ?>"><?= lang('Invoices.expected') ?></th>
                                <?php if (! $isDraft) : ?><th class="text-end pe-3"><?= lang('Invoices.withheld') ?></th><?php endif ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($withholding as $w) : ?>
                                <tr>
                                    <td class="ps-3">
                                        <div class="small fw-semibold"><?= esc(code_label('taxKind', $w['tax_kind'])) ?></div>
                                        <div class="small text-body-secondary"><?= esc($w['description']) ?><?= $w['regulation'] ? ' · ' . esc($w['regulation']) : '' ?></div>
                                    </td>
                                    <td class="text-end text-nowrap small"><?= esc(format_idr($w['base_amount'])) ?></td>
                                    <td class="text-end text-nowrap small"><?= esc(rtrim(rtrim((string) $w['rate'], '0'), '.')) ?>%</td>
                                    <td class="text-end text-nowrap<?= $isDraft ? ' pe-3' : '' ?>"><?= esc(format_idr($w['amount'])) ?></td>
                                    <?php if (! $isDraft) : ?>
                                        <td class="text-end text-nowrap pe-3<?= bccomp((string) $w['withheld'], (string) $w['amount'], 2) >= 0 ? ' text-success' : '' ?>"><?= esc(format_idr($w['withheld'])) ?></td>
                                    <?php endif ?>
                                </tr>
                            <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-white small text-body-secondary"><?= lang('Invoices.whFooter') ?></div>
            <?php endif ?>
        </div>

        <?php if (! $isDraft) : ?>
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-semibold"><?= lang('Invoices.payments') ?></span>
                    <?php if ($payable && $can['pay']) : ?>
                        <a href="<?= site_url("invoices/{$iid}/payments/new") ?>" class="btn btn-outline-success btn-sm"><i class="bi bi-plus-lg me-1"></i><?= lang('Invoices.recordPayment') ?></a>
                    <?php endif ?>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th class="ps-3"><?= lang('App.date') ?></th>
                                <th><?= lang('Invoices.methodReference') ?></th>
                                <th class="text-end"><?= lang('Invoices.cash') ?></th>
                                <th class="text-end pe-3"><?= lang('Invoices.withheld') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $p) : ?>
                                <?php $reversed = $p['reversed_at'] !== null ?>
                                <tr class="<?= $reversed ? 'text-body-tertiary' : '' ?>">
                                    <td class="ps-3 small text-nowrap"><?= esc(format_date($p['payment_date'])) ?></td>
                                    <td class="small">
                                        <?= esc($p['method_name']) ?><?= $p['bank_name'] ? ' · ' . esc(up($p['bank_name'])) : '' ?><?= $p['reference_number'] ? ' · <span class="font-monospace">' . esc($p['reference_number']) . '</span>' : '' ?>
                                        <div class="text-body-secondary"><?= esc(lang('Invoices.recordedBy', [$rowUser($p)])) ?><?= $p['note'] ? ' · ' . esc($p['note']) : '' ?>
                                            · <a href="<?= site_url('payments/' . $p['id'] . '/receipt') ?>" target="_blank" rel="noopener"><i class="bi bi-receipt-cutoff me-1"></i>Kwitansi<?= $p['receipt_number'] ? ' ' . esc($p['receipt_number']) : '' ?></a></div>
                                        <?php if ($reversed) : ?>
                                            <div class="text-danger-emphasis"><span class="badge bg-danger-subtle text-danger-emphasis border border-danger-subtle"><?= lang('Invoices.reversed') ?></span>
                                                <?= esc(lang('Invoices.reversedBy', [format_date($p['reversed_at'], true), $revUser($p)])) ?> <?= esc($p['reversal_reason']) ?></div>
                                        <?php elseif ($can['reverse']) : ?>
                                            <button type="button" class="btn btn-link btn-sm p-0 text-danger" data-bs-toggle="modal" data-bs-target="#reverse-payment-<?= (int) $p['id'] ?>"><?= lang('Invoices.reversePayment') ?></button>
                                        <?php endif ?>
                                    </td>
                                    <td class="text-end text-nowrap<?= $reversed ? ' text-decoration-line-through' : '' ?>"><?= esc(format_idr($p['amount'])) ?></td>
                                    <td class="text-end text-nowrap pe-3<?= $reversed ? ' text-decoration-line-through' : '' ?>"><?= (float) $p['withheld'] > 0 ? esc(format_idr($p['withheld'])) : '<span class="text-body-secondary">-</span>' ?></td>
                                </tr>
                                <?php foreach ($paymentTaxes[(int) $p['id']] ?? [] as $t) : ?>
                                    <tr class="small<?= $reversed ? ' text-body-tertiary' : '' ?>">
                                        <td class="border-0"></td>
                                        <td class="border-0 text-body-secondary" colspan="2">
                                            <i class="bi bi-arrow-return-right me-1"></i><?= esc(code_label('taxKind', $t['tax_kind'])) ?>:
                                            <?php if ($t['document_number']) : ?>
                                                <?= lang('Invoices.proof') ?> <span class="font-monospace"><?= esc($t['document_number']) ?></span><?= $t['document_date'] ? ' (' . esc(format_date($t['document_date'])) . ')' : '' ?>
                                            <?php else : ?>
                                                <span class="text-warning-emphasis"><?= lang('Invoices.noProofYet') ?></span>
                                            <?php endif ?>
                                            <?php if ($can['pay']) : ?>
                                                <button type="button" class="btn btn-link btn-sm p-0 ms-1 align-baseline" data-bs-toggle="modal" data-bs-target="#proof-<?= (int) $t['id'] ?>"><?= lang('Invoices.editProof') ?></button>
                                            <?php endif ?>
                                        </td>
                                        <td class="border-0 text-end text-nowrap pe-3 text-body-secondary"><?= esc(format_idr($t['amount'])) ?></td>
                                    </tr>
                                <?php endforeach ?>
                            <?php endforeach ?>
                            <?php if ($payments === []) : ?>
                                <tr><td colspan="4" class="text-center text-body-secondary py-3 small"><?= lang('Invoices.noPayments') ?></td></tr>
                            <?php endif ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if ($creditNotes !== []) : ?>
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-white fw-semibold"><?= lang('Invoices.creditNotes') ?></div>
                    <div class="list-group list-group-flush">
                        <?php foreach ($creditNotes as $cn) : ?>
                            <a href="<?= site_url('credit-notes/' . $cn['id']) ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center gap-2">
                                <span class="text-truncate">
                                    <span class="font-monospace fw-semibold"><?= esc($cn['credit_note_number']) ?></span>
                                    <span class="badge bg-secondary-subtle text-secondary-emphasis border ms-1"><?= esc(code_label('reasonType', $cn['reason_type'])) ?></span>
                                    <span class="small text-body-secondary ms-1"><?= esc(format_date($cn['issue_date'])) ?> · <?= esc(mb_strimwidth($cn['reason'], 0, 80, '…')) ?></span>
                                </span>
                                <span class="text-nowrap">−<?= esc(format_idr($cn['grand_total'])) ?></span>
                            </a>
                        <?php endforeach ?>
                    </div>
                </div>
            <?php endif ?>

            <?php if ($goodsLines !== [] && ! $isVoid) : ?>
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <span class="fw-semibold"><?= lang('Invoices.delivery') ?></span>
                        <a href="<?= site_url('deliveries?q=' . rawurlencode($number)) ?>" class="btn btn-link btn-sm"><?= lang('Invoices.allDeliveryNotes') ?></a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light small">
                                <tr><th class="ps-3"><?= lang('Invoices.goods') ?></th><th class="text-end"><?= lang('Invoices.invoiced') ?></th><th class="text-end"><?= lang('Invoices.shipped') ?></th><th class="text-end pe-3"><?= lang('Invoices.delivered') ?></th></tr>
                            </thead>
                            <tbody class="small">
                                <?php foreach ($goodsLines as $g) : ?>
                                    <?php $complete = bccomp((string) $g['delivered'], (string) $g['quantity'], 3) >= 0 ?>
                                    <tr>
                                        <td class="ps-3"><?= esc(up($g['item_title'])) ?></td>
                                        <td class="text-end text-nowrap"><?= esc(format_qty($g['quantity'])) ?> <?= esc($g['unit_code']) ?></td>
                                        <td class="text-end text-nowrap"><?= esc(format_qty($g['shipped'])) ?></td>
                                        <td class="text-end text-nowrap pe-3<?= $complete ? ' text-success' : '' ?>"><?= esc(format_qty($g['delivered'])) ?><?= $complete ? ' <i class="bi bi-check2"></i>' : '' ?></td>
                                    </tr>
                                <?php endforeach ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($deliveries !== []) : ?>
                        <div class="list-group list-group-flush border-top">
                            <?php foreach ($deliveries as $d) : ?>
                                <a href="<?= site_url('deliveries/' . $d['id']) ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center gap-2 small">
                                    <span><span class="font-monospace fw-semibold"><?= esc($d['delivery_number']) ?></span>
                                        <span class="text-body-secondary ms-1"><?= esc(format_date($d['ship_date'])) ?> · <?= esc($d['courier_name'] ? up($d['courier_name']) : $d['shipment_name']) ?><?= $d['received_by_name'] ? ' · ' . esc(lang('Invoices.receivedBy', [up($d['received_by_name'])])) : '' ?></span></span>
                                    <?= \App\Models\DeliveryNoteModel::badge($d) ?>
                                </a>
                            <?php endforeach ?>
                        </div>
                    <?php endif ?>
                </div>
            <?php endif ?>

            <?php if ($refunds !== []) : ?>
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-white fw-semibold"><?= lang('Invoices.refunds') ?></div>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="table-light small">
                                <tr><th class="ps-3"><?= lang('App.date') ?></th><th><?= lang('Invoices.methodRefReason') ?></th><th class="text-end pe-3"><?= lang('App.amount') ?></th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($refunds as $r) : ?>
                                    <?php $reversed = $r['reversed_at'] !== null ?>
                                    <tr class="<?= $reversed ? 'text-body-tertiary' : '' ?>">
                                        <td class="ps-3 small text-nowrap"><?= esc(format_date($r['refund_date'])) ?></td>
                                        <td class="small">
                                            <?= esc($r['method_name']) ?><?= $r['bank_name'] ? ' · ' . esc(up($r['bank_name'])) : '' ?><?= $r['reference_number'] ? ' · <span class="font-monospace">' . esc($r['reference_number']) . '</span>' : '' ?>
                                            <div class="text-body-secondary"><?= esc($r['reason']) ?> · <?= esc(lang('Invoices.recordedByLower', [$rowUser($r)])) ?></div>
                                            <?php if ($reversed) : ?>
                                                <div class="text-danger-emphasis"><span class="badge bg-danger-subtle text-danger-emphasis border border-danger-subtle"><?= lang('Invoices.reversed') ?></span>
                                                    <?= esc(lang('Invoices.reversedBy', [format_date($r['reversed_at'], true), $revUser($r)])) ?> <?= esc($r['reversal_reason']) ?></div>
                                            <?php elseif ($can['reverse']) : ?>
                                                <button type="button" class="btn btn-link btn-sm p-0 text-danger" data-bs-toggle="modal" data-bs-target="#reverse-refund-<?= (int) $r['id'] ?>"><?= lang('Invoices.reverseRefund') ?></button>
                                            <?php endif ?>
                                        </td>
                                        <td class="text-end text-nowrap pe-3<?= $reversed ? ' text-decoration-line-through' : '' ?>"><?= esc(format_idr($r['amount'])) ?></td>
                                    </tr>
                                <?php endforeach ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif ?>
        <?php endif ?>

        <?php if (! empty($invoice['notes'])) : ?>
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold"><?= lang('Invoices.notes') ?></div>
                <div class="card-body small"><?= nl2br(esc($invoice['notes'])) ?></div>
            </div>
        <?php endif ?>
    </div>
</div>

<?php if (! empty($activityPanel)) : ?><?= view('components/activity_panel', ['panel' => $activityPanel], ['saveData' => false]) ?><?php endif ?>

<?= view('components/order_files', ['files' => $flow['files'], 'default' => "invoice:{$iid}", 'returnTo' => "invoices/{$iid}"]) ?>

<?php if ($isDraft && $can['manage']) : ?>
    <div class="modal fade" id="issue-modal" tabindex="-1" aria-labelledby="issue-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" action="<?= site_url("invoices/{$iid}/issue") ?>" method="post">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="issue-modal-title"><i class="bi bi-send-check me-1"></i><?= lang('Invoices.issueInvoice') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= esc(lang('App.close'), 'attr') ?>"></button>
                </div>
                <div class="modal-body small">
                    <p><?= lang('Invoices.issueText', [esc($invoice['payment_term_code'])]) ?></p>
                    <div class="d-flex justify-content-between"><span><?= lang('App.grandTotal') ?></span><strong><?= esc(format_idr($invoice['grand_total'])) ?></strong></div>
                    <?php if (bccomp($whTotal, '0', 2) > 0) : ?>
                        <div class="d-flex justify-content-between text-body-secondary"><span><?= lang('Invoices.expectedWithholdingShort') ?></span><span>−<?= esc(format_idr($whTotal)) ?></span></div>
                        <div class="d-flex justify-content-between"><span><?= lang('Invoices.expectedNet') ?></span><strong><?= esc(format_idr($netTotal)) ?></strong></div>
                    <?php endif ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= lang('App.close') ?></button>
                    <button type="submit" class="btn btn-primary"><?= lang('Invoices.issueInvoice') ?></button>
                </div>
            </form>
        </div>
    </div>
<?php endif ?>

<?php if ($payable && $can['void'] && ! $settled) : ?>
    <div class="modal fade" id="void-modal" tabindex="-1" aria-labelledby="void-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" action="<?= site_url("invoices/{$iid}/void") ?>" method="post">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="void-modal-title"><i class="bi bi-slash-circle me-1"></i><?= esc(lang('Invoices.voidTitle', [$number])) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= esc(lang('App.close'), 'attr') ?>"></button>
                </div>
                <div class="modal-body">
                    <p class="small"><?= lang('Invoices.voidText') ?></p>
                    <label for="void_reason" class="form-label"><?= lang('App.reason') ?> <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="void_reason" name="void_reason" rows="3" maxlength="2000" required></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= lang('App.close') ?></button>
                    <button type="submit" class="btn btn-danger"><?= lang('Invoices.voidInvoice') ?></button>
                </div>
            </form>
        </div>
    </div>
<?php endif ?>

<?php if ($can['pay']) : ?>
    <?php foreach ($paymentTaxes as $taxes) : ?>
        <?php foreach ($taxes as $t) : ?>
            <div class="modal fade" id="proof-<?= (int) $t['id'] ?>" tabindex="-1" aria-labelledby="proof-<?= (int) $t['id'] ?>-title" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <form class="modal-content" action="<?= site_url('payments/withholdings/' . $t['id']) ?>" method="post">
                        <?= csrf_field() ?>
                        <div class="modal-header">
                            <h5 class="modal-title" id="proof-<?= (int) $t['id'] ?>-title"><?= esc(lang('Invoices.proofTitle', [code_label('taxKind', $t['tax_kind']), format_idr($t['amount'])])) ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= esc(lang('App.close'), 'attr') ?>"></button>
                        </div>
                        <div class="modal-body row g-3">
                            <div class="col-12 col-sm-7">
                                <label for="proof-no-<?= (int) $t['id'] ?>" class="form-label"><?= lang('Invoices.proofNumber') ?></label>
                                <input type="text" class="form-control" id="proof-no-<?= (int) $t['id'] ?>" name="document_number" maxlength="64" value="<?= esc($t['document_number'] ?? '') ?>">
                            </div>
                            <div class="col-12 col-sm-5">
                                <label for="proof-date-<?= (int) $t['id'] ?>" class="form-label"><?= lang('App.date') ?></label>
                                <input type="date" class="form-control" id="proof-date-<?= (int) $t['id'] ?>" name="document_date" value="<?= esc($t['document_date'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= lang('App.close') ?></button>
                            <button type="submit" class="btn btn-primary"><?= lang('Invoices.saveProof') ?></button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endforeach ?>
    <?php endforeach ?>
<?php endif ?>

<?php if ($can['reverse']) : ?>
    <?php foreach ($payments as $p) : ?>
        <?php if ($p['reversed_at'] === null) : ?>
            <?= view('components/reason_modal', [
                'id'     => 'reverse-payment-' . $p['id'],
                'action' => site_url('payments/' . $p['id'] . '/reverse'),
                'title'  => lang('Invoices.reversePaymentTitle', [format_idr($p['amount'])]),
                'help'   => lang('Invoices.reversePaymentHelp'),
                'button' => lang('Invoices.reversePaymentBtn'),
                'field'  => 'reversal_reason',
            ]) ?>
        <?php endif ?>
    <?php endforeach ?>
    <?php foreach ($refunds as $r) : ?>
        <?php if ($r['reversed_at'] === null) : ?>
            <?= view('components/reason_modal', [
                'id'     => 'reverse-refund-' . $r['id'],
                'action' => site_url('refunds/' . $r['id'] . '/reverse'),
                'title'  => lang('Invoices.reverseRefundTitle', [format_idr($r['amount'])]),
                'help'   => lang('Invoices.reverseRefundHelp'),
                'button' => lang('Invoices.reverseRefundBtn'),
                'field'  => 'reversal_reason',
            ]) ?>
        <?php endif ?>
    <?php endforeach ?>
<?php endif ?>

<?= $this->endSection() ?>
