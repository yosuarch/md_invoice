<?php
/**
 * Delivery note form. Create: header + quantity per goods line. Edit (open notes): header only, lines are shown read-only.
 *
 * @var array<string, mixed>       $invoice   Invoice with details.
 * @var array<string, mixed>       $note      Stored note (with id) or defaults on create.
 * @var list<array<string, mixed>> $lines     Create: deliverable lines (remaining); edit: the note's lines.
 * @var list<array<string, mixed>> $shipments Shipment rows (id, code, name).
 */

use App\Models\InvoiceModel;

$isEdit   = isset($note['id']);
$iid      = (int) $invoice['id'];
$number   = InvoiceModel::displayNumber($invoice);
$title    = $isEdit ? lang('Deliveries.editDeliveryNote', [$note['delivery_number']]) : lang('Deliveries.newDeliveryNote');
$action   = $isEdit ? site_url('deliveries/' . $note['id']) : site_url("invoices/{$iid}/deliveries");
$back     = $isEdit ? site_url('deliveries/' . $note['id']) : site_url("invoices/{$iid}");
$old      = old('lines', null, false);
$old      = is_array($old) ? $old : [];
$errors   = form_errors();
$lineErrs = array_filter($errors, static fn ($k) => str_starts_with((string) $k, 'lines'), ARRAY_FILTER_USE_KEY);
$current  = form_value('shipment_id', $note);
?>
<?= $this->extend('layouts/app_layout') ?>

<?= $this->section('title') ?><?= esc($title) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('components/page_header', [
    'title'      => $title,
    'subtitle'   => lang('Payments.invoiceCustomer', [$number, up($invoice['customer_name'])]),
    'breadcrumb' => $isEdit
        ? [[lang('Deliveries.deliveries'), site_url('deliveries')], [$note['delivery_number'], $back], [lang('App.edit'), '']]
        : [[lang('Invoices.invoices'), site_url('invoices')], [$number, $back], [lang('Deliveries.deliveryNote'), '']],
]) ?>

<form action="<?= $action ?>" method="post" novalidate id="delivery-form">
    <?= csrf_field() ?>

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold"><?= lang('Deliveries.shipment') ?></div>
        <div class="card-body row g-3">
            <div class="col-12 col-md-4">
                <label for="shipment_id" class="form-label"><?= lang('Deliveries.shipmentMethod') ?> <span class="text-danger">*</span></label>
                <select class="form-select<?= invalid('shipment_id') ?>" id="shipment_id" name="shipment_id" required>
                    <option value=""><?= lang('App.select') ?></option>
                    <?php foreach ($shipments as $s) : ?>
                        <option value="<?= (int) $s['id'] ?>" data-code="<?= esc($s['code'], 'attr') ?>"<?= $current === (string) $s['id'] ? ' selected' : '' ?>><?= esc($s['name']) ?></option>
                    <?php endforeach ?>
                </select>
                <?= field_error('shipment_id') ?>
            </div>
            <div class="col-12 col-md-4">
                <label for="ship_date" class="form-label"><?= lang('Deliveries.plannedShipDate') ?> <span class="text-danger">*</span></label>
                <input type="date" class="form-control<?= invalid('ship_date') ?>" id="ship_date" name="ship_date" required value="<?= esc(form_value('ship_date', $note)) ?>">
                <?= field_error('ship_date') ?>
            </div>
            <div class="col-12 col-md-4" data-courier>
                <label for="courier_name" class="form-label"><?= lang('Deliveries.courier') ?> <span class="text-danger" data-courier-required>*</span></label>
                <input type="text" class="form-control text-uppercase<?= invalid('courier_name') ?>" id="courier_name" name="courier_name" maxlength="64" placeholder="<?= esc(lang('Deliveries.courierHint'), 'attr') ?>"
                       value="<?= esc(form_value('courier_name', $note)) ?>">
                <?= field_error('courier_name') ?>
            </div>
            <div class="col-12 col-md-8">
                <label for="shipping_address" class="form-label"><?= lang('Deliveries.shippingAddress') ?> <span class="text-danger">*</span></label>
                <textarea class="form-control text-uppercase<?= invalid('shipping_address') ?>" id="shipping_address" name="shipping_address" rows="2" maxlength="1000" required><?= esc(form_value('shipping_address', $note)) ?></textarea>
                <?= field_error('shipping_address') ?>
            </div>
            <div class="col-12 col-md-4" data-courier>
                <label for="tracking_number" class="form-label"><?= lang('Deliveries.trackingNumber') ?></label>
                <input type="text" class="form-control<?= invalid('tracking_number') ?>" id="tracking_number" name="tracking_number" maxlength="64"
                       value="<?= esc(form_value('tracking_number', $note)) ?>">
                <?= field_error('tracking_number') ?>
            </div>
            <div class="col-12">
                <label for="note" class="form-label"><?= lang('App.note') ?></label>
                <input type="text" class="form-control<?= invalid('note') ?>" id="note" name="note" maxlength="2000" placeholder="<?= esc(lang('Deliveries.noteHint'), 'attr') ?>"
                       value="<?= esc(form_value('note', $note)) ?>">
                <?= field_error('note') ?>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold"><?= $isEdit ? lang('Deliveries.goodsFixed') : lang('Deliveries.goodsToShip') ?></div>
        <?php if ($lineErrs !== []) : ?>
            <div class="alert alert-danger small rounded-0 mb-0 py-2">
                <?php foreach ($lineErrs as $message) : ?><div><?= esc($message) ?></div><?php endforeach ?>
            </div>
        <?php endif ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th class="ps-3"><?= lang('App.description') ?></th>
                        <th class="text-end"><?= lang('Invoices.invoiced') ?></th>
                        <?php if (! $isEdit) : ?>
                            <th class="text-end"><?= lang('Deliveries.alreadyShipped') ?></th>
                            <th class="text-end pe-3" style="width: 170px"><?= lang('Deliveries.shipNow') ?></th>
                        <?php else : ?>
                            <th class="text-end pe-3"><?= lang('Deliveries.shippedWithNote') ?></th>
                        <?php endif ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lines as $line) : ?>
                        <?php if ($isEdit) : ?>
                            <tr>
                                <td class="ps-3"><?= esc(up($line['item_title'])) ?><div class="small text-body-secondary"><?= esc($line['pl_sku'] ?? '') ?></div></td>
                                <td class="text-end small"><?= esc(format_qty($line['invoiced_quantity'])) ?> <?= esc($line['unit_code']) ?></td>
                                <td class="text-end pe-3"><?= esc(format_qty($line['quantity'])) ?> <?= esc($line['unit_code']) ?></td>
                            </tr>
                        <?php else : ?>
                            <?php
                            $lid  = (int) $line['id'];
                            $done = bccomp((string) $line['remaining'], '0', 3) <= 0;
                            $val  = $old[$lid]['quantity'] ?? ($done ? '' : rtrim(rtrim((string) $line['remaining'], '0'), '.'));
                            ?>
                            <tr class="<?= isset($errors['lines.' . $lid]) ? 'table-danger' : '' ?>">
                                <td class="ps-3"><?= esc(up($line['item_title'])) ?><div class="small text-body-secondary"><?= esc($line['pl_sku'] ?? '') ?></div></td>
                                <td class="text-end small text-nowrap"><?= esc(format_qty($line['quantity'])) ?> <?= esc($line['unit_code']) ?></td>
                                <td class="text-end small text-nowrap"><?= (float) $line['shipped'] > 0 ? esc(format_qty($line['shipped'])) . ' ' . esc($line['unit_code']) : '<span class="text-body-secondary">-</span>' ?></td>
                                <td class="pe-3">
                                    <?php if ($done) : ?>
                                        <div class="text-end small text-success"><i class="bi bi-check2 me-1"></i><?= lang('Deliveries.allShipped') ?></div>
                                    <?php else : ?>
                                        <div class="input-group input-group-sm">
                                            <input type="number" class="form-control text-end" name="lines[<?= $lid ?>][quantity]" min="0" max="<?= esc((string) $line['remaining'], 'attr') ?>" step="any"
                                                   value="<?= esc((string) $val) ?>" aria-label="<?= esc(lang('Deliveries.qtyToShip'), 'attr') ?>">
                                            <span class="input-group-text"><?= esc($line['unit_code']) ?></span>
                                        </div>
                                        <div class="small text-body-secondary text-end"><?= esc(lang('Deliveries.max', [format_qty($line['remaining'])])) ?></div>
                                    <?php endif ?>
                                </td>
                            </tr>
                        <?php endif ?>
                    <?php endforeach ?>
                    <?php if ($lines === []) : ?>
                        <tr><td colspan="4" class="text-center text-body-secondary py-3 small"><?= lang('Deliveries.noGoodsLines') ?></td></tr>
                    <?php endif ?>
                </tbody>
            </table>
        </div>
        <?php if (! $isEdit) : ?>
            <div class="card-footer bg-white small text-body-secondary"><?= lang('Deliveries.goodsFooter') ?></div>
        <?php endif ?>
    </div>

    <div class="d-flex gap-2 mb-4">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i><?= $isEdit ? lang('App.save') : lang('Deliveries.create') ?></button>
        <a href="<?= $back ?>" class="btn btn-outline-secondary"><?= lang('App.cancel') ?></a>
    </div>
</form>

<?= $this->endSection() ?>

<?= $this->section('pageScripts') ?>
<script src="<?= base_url('assets/js/delivery-form.js') ?>"></script>
<?= $this->endSection() ?>
