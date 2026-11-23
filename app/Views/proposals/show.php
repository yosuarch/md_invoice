<?php
/**
 * @var array<string, mixed>        $proposal  With customer_name, status_code, rep_name, funding_source_name.
 * @var list<array<string, mixed>>  $items
 * @var list<array<string, mixed>>  $history
 * @var array<string, mixed>|null   $contact
 * @var \CodeIgniter\Shield\Entities\User|null $creator
 * @var array<string, array<string, mixed>>   $actions  Allowed status transitions: to => definition.
 * @var bool                        $canEdit
 * @var list<array<string, mixed>>  $invoices   Invoices of this proposal that are not void.
 * @var bool                        $canInvoice User may create invoices.
 * @var array<string, mixed>        $flow       Order tracker (App\Services\OrderFlow).
 * @var array<string, mixed>|null   $agreement  Surat Perintah Kerja of this proposal, if any.
 * @var array<string, mixed>        $negotiation Revisions and unsent changes (App\Services\ProposalRevisions::history).
 */

use App\Models\CustomerModel;
use App\Models\InvoiceModel;
use App\Services\ProposalWorkflow;

$pid     = (int) $proposal['id'];
$expired = $proposal['valid_until'] !== null && $proposal['valid_until'] < date('Y-m-d')
    && in_array($proposal['status_code'], ProposalWorkflow::OPEN, true);

$buttons = '';

foreach ($actions as $to => $a) {
    $buttons .= '<button type="button" class="btn ' . esc($a['class'], 'attr') . '" data-bs-toggle="modal" data-bs-target="#status-' . esc($to, 'attr') . '">'
        . '<i class="bi bi-' . esc($a['icon'], 'attr') . ' me-1"></i>' . esc($a['label']) . '</button>';
}

if ($canEdit) {
    $buttons .= '<a href="' . site_url("proposals/{$pid}/edit") . '" class="btn btn-outline-primary"><i class="bi bi-pencil me-1"></i>' . esc(lang('App.edit')) . '</a>';
}

if ($proposal['status_code'] === ProposalWorkflow::STORE_APPROVE && user_can('proposals.manage')) {
    $buttons .= '<a href="' . site_url("proposals/{$pid}/agreement") . '" class="btn btn-outline-primary"><i class="bi bi-file-earmark-check me-1"></i>'
        . esc($agreement === null ? lang('Proposals.createSpk') : lang('Proposals.editSpk')) . '</a>';
}

$printItems = [['label' => 'Surat Penawaran Harga', 'url' => print_url("proposals/{$pid}/pdf")]];

if ($agreement !== null) {
    $printItems[] = ['label' => 'Surat Perintah Kerja ' . $agreement['agreement_number'], 'url' => print_url("proposals/{$pid}/agreement/pdf"), 'icon' => 'file-earmark-check'];
}

$buttons .= view('components/print_menu', ['items' => $printItems]);

// Finished order: one PDF with all documents and attachments (OrderBundle).
$bundleUrl = \App\Services\OrderFlow::isComplete($flow) ? site_url("proposals/{$pid}/bundle") : null;

if ($bundleUrl !== null) {
    $buttons .= '<a href="' . $bundleUrl . '" class="btn btn-outline-primary"><i class="bi bi-files me-1"></i>' . esc(lang('Bundle.button')) . '</a>';
}

/**
 * Display name of a user row from the history.
 */
$userName = static fn (array $row): string => (string) ($row['full_name'] ? up($row['full_name']) : ($row['username'] ?: lang('Proposals.unknownUser')));
?>
<?= $this->extend('layouts/app_layout') ?>

<?= $this->section('title') ?><?= esc($proposal['cpl_id']) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('components/page_header', [
    'title'      => lang('Proposals.proposalNo', [$proposal['cpl_id']]) . ((int) $proposal['revision_no'] > 1 ? ' · ' . lang('Proposals.revShort', [(int) $proposal['revision_no']]) : ''),
    'subtitle'   => up($proposal['cpl_proposal_title']),
    'breadcrumb' => [[lang('Proposals.proposals'), site_url('proposals')], [$proposal['cpl_id'], '']],
    'actions'    => $buttons,
]) ?>

<?php if ($expired) : ?>
    <div class="alert alert-warning small"><i class="bi bi-exclamation-circle me-1"></i><?= lang('Proposals.expiredOn', [format_date($proposal['valid_until'])]) ?></div>
<?php endif ?>
<?php if ($proposal['status_code'] === ProposalWorkflow::PROPOSE && user_can('proposals.manage')) : ?>
    <div class="alert alert-info small"><i class="bi bi-info-circle me-1"></i><?= lang('Proposals.sentLocked', [esc(code_label('proposalAction', 'PROPOSE_UNDER_REVIEW'))]) ?></div>
<?php endif ?>
<?php if ($proposal['status_code'] === ProposalWorkflow::STORE_APPROVE) : ?>
    <?php if ($invoices !== []) : ?>
        <div class="alert alert-success small d-flex flex-wrap align-items-center gap-2">
            <span><i class="bi bi-check2-circle me-1"></i><?= lang('Proposals.approvedInvoiced') ?></span>
            <?php foreach ($invoices as $inv) : ?>
                <a href="<?= site_url('invoices/' . $inv['id']) ?>" class="font-monospace fw-semibold"><?= esc(InvoiceModel::displayNumber($inv)) ?></a><?= invoice_status_badge($inv['status_code']) ?>
            <?php endforeach ?>
        </div>
    <?php else : ?>
        <div class="alert alert-success small d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span><i class="bi bi-check2-circle me-1"></i><?= lang('Proposals.approvedCreateInvoice') ?></span>
            <?php if ($canInvoice) : ?>
                <form action="<?= site_url("proposals/{$pid}/invoice") ?>" method="post" class="m-0">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-success btn-sm"><i class="bi bi-receipt me-1"></i><?= lang('Proposals.createInvoice') ?></button>
                </form>
            <?php endif ?>
        </div>
    <?php endif ?>
<?php endif ?>

<?= view('components/order_flow', ['flow' => $flow, 'current' => site_url("proposals/{$pid}")]) ?>

<div class="row g-3">
    <div class="col-12 col-xl-4">
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
                <span><?= lang('App.details') ?></span><?= proposal_status_badge($proposal['status_code']) ?>
            </div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-5 text-body-secondary fw-normal"><?= lang('App.customer') ?></dt>
                    <dd class="col-7">
                        <?= customer_link($proposal['customer_id'], $proposal['customer_name']) ?>
                        <?php if ($proposal['school_status']) : ?><div class="text-body-secondary"><?= esc(code_label('schoolStatus', $proposal['school_status'])) ?><?= $proposal['npsn'] ? ' · NPSN ' . esc($proposal['npsn']) : '' ?></div><?php endif ?>
                    </dd>
                    <dt class="col-5 text-body-secondary fw-normal"><?= lang('Proposals.contactPerson') ?></dt>
                    <dd class="col-7"><?= $contact !== null ? '<a href="' . site_url('contacts/' . $contact['contact_id']) . '">' . esc(up(trim($contact['ccp_first_name'] . ' ' . ($contact['ccp_last_name'] ?? '')))) . '</a>' . (\App\Models\CustomerContactModel::roleLabel($contact) !== '' ? '<div class="text-body-secondary">' . esc(\App\Models\CustomerContactModel::roleLabel($contact)) . '</div>' : '') . ($contact['ccp_phone_number'] ? '<div class="text-body-secondary">' . esc($contact['ccp_phone_number']) . '</div>' : '') : '-' ?></dd>
                    <dt class="col-5 text-body-secondary fw-normal"><?= lang('Proposals.salesRep') ?></dt>
                    <dd class="col-7"><?= esc($proposal['rep_name'] ? up($proposal['rep_name']) : '-') ?></dd>
                    <dt class="col-5 text-body-secondary fw-normal"><?= lang('Proposals.fundingSource') ?></dt>
                    <dd class="col-7"><?= esc($proposal['funding_source_name'] ?? lang('Proposals.notKnownYetText')) ?></dd>
                    <dt class="col-5 text-body-secondary fw-normal"><?= lang('App.date') ?></dt>
                    <dd class="col-7"><?= esc(format_date($proposal['created_at'])) ?></dd>
                    <dt class="col-5 text-body-secondary fw-normal"><?= lang('Proposals.validUntil') ?></dt>
                    <dd class="col-7<?= $expired ? ' text-danger' : '' ?>"><?= esc(format_date($proposal['valid_until']) ?: '-') ?></dd>
                    <dt class="col-5 text-body-secondary fw-normal"><?= lang('Proposals.paymentTerms') ?></dt>
                    <dd class="col-7"><?= $proposal['payment_term_code'] ? esc($proposal['payment_term_code']) . ' (' . esc(lang('Customers.days', [(int) $proposal['due_days']])) . ')' : '<span class="text-body-secondary">' . esc(lang('Proposals.customerDefaultText')) . '</span>' ?></dd>
                    <dt class="col-5 text-body-secondary fw-normal"><?= lang('Proposals.delivery') ?></dt>
                    <dd class="col-7"><?= esc($proposal['shipment_name'] ?? '-') ?><?= $proposal['delivery_date'] ? '<div class="text-body-secondary">' . esc(lang('Proposals.planned', [format_date($proposal['delivery_date'])])) . '</div>' : '' ?></dd>
                    <dt class="col-5 text-body-secondary fw-normal"><?= lang('App.createdBy') ?></dt>
                    <dd class="col-7 mb-0"><?= esc($creator !== null ? ($creator->full_name ? up($creator->full_name) : $creator->username) : '-') ?></dd>
                </dl>
            </div>
        </div>

        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold"><?= lang('Proposals.statusHistory') ?></div>
            <div class="card-body">
                <ul class="status-timeline small">
                    <?php foreach ($history as $h) : ?>
                        <li>
                            <div><?= proposal_status_badge($h['to_code']) ?></div>
                            <div class="text-body-secondary mt-1"><?= esc(format_date($h['created_at'], true)) ?> · <?= esc($userName($h)) ?></div>
                            <?php if (! empty($h['note'])) : ?><div class="mt-1 text-break"><?= nl2br(esc($h['note'])) ?></div><?php endif ?>
                        </li>
                    <?php endforeach ?>
                </ul>
            </div>
        </div>
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
                                <td class="text-end text-nowrap"><?= esc(format_qty($item['cpi_quantity'])) ?> <span class="small text-body-secondary"><?= esc($item['unit_code']) ?></span></td>
                                <td class="text-end text-nowrap"><?= esc(format_price($item['unit_price'])) ?></td>
                                <td class="text-end text-nowrap"><?= (float) $item['discount_amount'] > 0 ? esc(format_idr($item['discount_amount'])) : '<span class="text-body-secondary">-</span>' ?></td>
                                <td class="text-end text-nowrap small"><?= esc(rtrim(rtrim((string) $item['tax_rate'], '0'), '.')) ?>%</td>
                                <td class="text-end text-nowrap pe-3"><?= esc(format_idr($item['line_total'])) ?></td>
                            </tr>
                        <?php endforeach ?>
                        <?php if ($items === []) : ?>
                            <tr><td colspan="7" class="text-center text-body-secondary py-4 small"><?= lang('Proposals.noItemsYet') ?><?= $canEdit ? ' <a href="' . site_url("proposals/{$pid}/edit") . '">' . esc(lang('Proposals.addItems')) . '</a>.' : '' ?></td></tr>
                        <?php endif ?>
                    </tbody>
                    <tfoot>
                        <tr><td colspan="6" class="text-end text-body-secondary small border-0 pb-0"><?= lang('App.subtotal') ?></td><td class="text-end text-nowrap pe-3 border-0 pb-0"><?= esc(format_idr($proposal['subtotal'])) ?></td></tr>
                        <?php if ((float) $proposal['discount_total'] > 0) : ?>
                            <tr><td colspan="6" class="text-end text-body-secondary small border-0 py-0"><?= lang('Proposals.inclDiscounts') ?></td><td class="text-end text-nowrap small text-body-secondary pe-3 border-0 py-0"><?= esc(format_idr($proposal['discount_total'])) ?></td></tr>
                        <?php endif ?>
                        <tr><td colspan="6" class="text-end text-body-secondary small border-0 pt-0"><?= lang('Proposals.taxPpn') ?></td><td class="text-end text-nowrap pe-3 border-0 pt-0"><?= esc(format_idr($proposal['tax_total'])) ?></td></tr>
                        <tr class="table-light"><td colspan="6" class="text-end fw-semibold"><?= lang('App.grandTotal') ?></td><td class="text-end text-nowrap fw-bold pe-3"><?= esc(format_idr($proposal['grand_total'])) ?></td></tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <?= view('proposals/_negotiation', ['negotiation' => $negotiation, 'pid' => $pid]) ?>

        <?php if (! empty($activityPanel)) : ?><?= view('components/activity_panel', ['panel' => $activityPanel], ['saveData' => false]) ?><?php endif ?>

        <?php if (! empty($proposal['cpl_note'])) : ?>
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold"><?= lang('Proposals.note') ?></div>
                <div class="card-body small"><?= nl2br(esc($proposal['cpl_note'])) ?></div>
            </div>
        <?php endif ?>
    </div>
</div>

<?= view('components/order_files', ['files' => $flow['files'], 'default' => "proposal:{$pid}", 'returnTo' => "proposals/{$pid}", 'bundleUrl' => $bundleUrl]) ?>

<?php foreach ($actions as $to => $a) : ?>
    <div class="modal fade" id="status-<?= esc($to, 'attr') ?>" tabindex="-1" aria-labelledby="status-<?= esc($to, 'attr') ?>-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" action="<?= site_url("proposals/{$pid}/status") ?>" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="to" value="<?= esc($to, 'attr') ?>">
                <div class="modal-header">
                    <h5 class="modal-title" id="status-<?= esc($to, 'attr') ?>-title"><i class="bi bi-<?= esc($a['icon'], 'attr') ?> me-1"></i><?= esc($a['label']) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= esc(lang('App.close'), 'attr') ?>"></button>
                </div>
                <div class="modal-body">
                    <p class="small mb-2"><?= esc($a['help']) ?></p>
                    <p class="small text-body-secondary mb-3"><?= lang('Proposals.newStatus') ?> <?= proposal_status_badge($to) ?></p>
                    <label for="note-<?= esc($to, 'attr') ?>" class="form-label"><?= esc($a['noteLabel'] ?? ($a['note'] === 'required' ? lang('App.reason') : lang('Proposals.noteOptional'))) ?><?= $a['note'] === 'required' ? ' <span class="text-danger">*</span>' : '' ?></label>
                    <textarea class="form-control" id="note-<?= esc($to, 'attr') ?>" name="note" rows="3" maxlength="2000"<?= $a['note'] === 'required' ? ' required' : '' ?>></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= lang('App.close') ?></button>
                    <button type="submit" class="btn <?= str_contains($a['class'], 'danger') ? 'btn-danger' : (str_contains($a['class'], 'success') ? 'btn-success' : 'btn-primary') ?>"><?= esc($a['label']) ?></button>
                </div>
            </form>
        </div>
    </div>
<?php endforeach ?>

<?= $this->endSection() ?>
