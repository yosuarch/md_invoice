<?php
/**
 * @var array<string, mixed>       $note  Credit note with invoice_number, customer_name, username/full_name.
 * @var list<array<string, mixed>> $items
 */
?>
<?= $this->extend('layouts/app_layout') ?>

<?= $this->section('title') ?><?= esc($note['credit_note_number']) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('components/page_header', [
    'title'      => lang('CreditNotes.creditNoteNo', [$note['credit_note_number']]),
    'subtitle'   => up($note['customer_name']),
    'breadcrumb' => array_merge(
        user_can('credit_notes.manage') ? [[lang('CreditNotes.creditNotes'), site_url('credit-notes')]] : [[lang('Invoices.invoices'), site_url('invoices')]],
        [[$note['credit_note_number'], '']],
    ),
    'actions'    => '<a href="' . site_url('invoices/' . $note['invoice_id']) . '" class="btn btn-outline-primary"><i class="bi bi-receipt me-1"></i>' . esc(lang('CreditNotes.invoiceBtn', [$note['invoice_number']])) . '</a>',
]) ?>

<div class="row g-3">
    <div class="col-12 col-xl-4">
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold"><?= lang('App.details') ?></div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-5 text-body-secondary fw-normal"><?= lang('App.customer') ?></dt>
                    <dd class="col-7"><?= customer_link($note['customer_id'], $note['customer_name']) ?></dd>
                    <dt class="col-5 text-body-secondary fw-normal"><?= lang('Payments.invoice') ?></dt>
                    <dd class="col-7"><a href="<?= site_url('invoices/' . $note['invoice_id']) ?>" class="font-monospace"><?= esc($note['invoice_number']) ?></a></dd>
                    <dt class="col-5 text-body-secondary fw-normal"><?= lang('App.type') ?></dt>
                    <dd class="col-7"><?= esc(code_label('reasonType', $note['reason_type'])) ?></dd>
                    <dt class="col-5 text-body-secondary fw-normal"><?= lang('App.date') ?></dt>
                    <dd class="col-7"><?= esc(format_date($note['issue_date'])) ?></dd>
                    <dt class="col-5 text-body-secondary fw-normal"><?= lang('App.createdBy') ?></dt>
                    <dd class="col-7"><?= esc(($note['full_name'] ?? '') ? up($note['full_name']) : ($note['username'] ?? '-')) ?></dd>
                    <dt class="col-5 text-body-secondary fw-normal"><?= lang('App.reason') ?></dt>
                    <dd class="col-7 mb-0 text-break"><?= nl2br(esc($note['reason'])) ?></dd>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-8">
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold"><?= lang('CreditNotes.creditedItems') ?></div>
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
                                <td><?= esc(up($item['item_title'])) ?><?php if (! empty($item['item_description'])) : ?><div class="small text-body-secondary"><?= nl2br(esc($item['item_description'])) ?></div><?php endif ?></td>
                                <td class="text-end text-nowrap"><?= esc(format_qty($item['quantity'])) ?> <span class="small text-body-secondary"><?= esc($item['unit_code']) ?></span></td>
                                <td class="text-end text-nowrap"><?= esc(format_price($item['unit_price'])) ?></td>
                                <td class="text-end text-nowrap"><?= (float) $item['discount_amount'] > 0 ? esc(format_idr($item['discount_amount'])) : '<span class="text-body-secondary">-</span>' ?></td>
                                <td class="text-end text-nowrap small"><?= esc(rtrim(rtrim((string) $item['tax_rate'], '0'), '.')) ?>%</td>
                                <td class="text-end text-nowrap pe-3"><?= esc(format_idr($item['line_total'])) ?></td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                    <tfoot>
                        <tr><td colspan="6" class="text-end text-body-secondary small border-0 pb-0"><?= lang('App.subtotal') ?></td><td class="text-end text-nowrap pe-3 border-0 pb-0"><?= esc(format_idr($note['subtotal'])) ?></td></tr>
                        <tr><td colspan="6" class="text-end text-body-secondary small border-0 py-0"><?= lang('Proposals.taxPpn') ?></td><td class="text-end text-nowrap pe-3 border-0 py-0"><?= esc(format_idr($note['tax_total'])) ?></td></tr>
                        <?php if ((float) $note['shipping_amount'] > 0) : ?>
                            <tr><td colspan="6" class="text-end text-body-secondary small border-0 pt-0"><?= lang('Invoices.shipping') ?></td><td class="text-end text-nowrap pe-3 border-0 pt-0"><?= esc(format_idr($note['shipping_amount'])) ?></td></tr>
                        <?php endif ?>
                        <tr class="table-light"><td colspan="6" class="text-end fw-semibold"><?= lang('CreditNotes.total') ?></td><td class="text-end text-nowrap fw-bold pe-3"><?= esc(format_idr($note['grand_total'])) ?></td></tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
