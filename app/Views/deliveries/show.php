<?php
/**
 * @var array<string, mixed>       $note      Delivery note with invoice_number, customer_name, shipment_name, ...
 * @var list<array<string, mixed>> $items
 * @var list<array<string, mixed>> $events    Tracking timeline, newest first.
 * @var array<string, mixed>       $flow      Order tracker (App\Services\OrderFlow).
 * @var bool                       $canManage
 */

use App\Models\DeliveryEventModel;
use App\Models\DeliveryNoteModel;

$did       = (int) $note['id'];
$status    = DeliveryNoteModel::status($note);
$isOpen    = DeliveryNoteModel::isOpen($note);
$preparing = $status === DeliveryNoteModel::PREPARING;
$today     = date('Y-m-d');

$buttons = '';

$buttons = view('components/print_menu', ['items' => [
    ['label' => 'Surat Jalan ' . $note['delivery_number'], 'url' => print_url("deliveries/{$did}/pdf"), 'icon' => 'truck'],
    ['label' => 'Berita Acara Serah Terima', 'url' => print_url("deliveries/{$did}/handover"), 'icon' => 'clipboard-check'],
]]);

if ($isOpen && $canManage) {
    if ($preparing) {
        $buttons .= '<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#shipped-modal"><i class="bi bi-truck me-1"></i>' . esc(lang('Deliveries.markShipped')) . '</button>';
    }

    $buttons .= '<button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#track-modal"><i class="bi bi-geo-alt me-1"></i>' . esc(lang('Deliveries.addTracking')) . '</button>';

    $buttons .= '<button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#delivered-modal"><i class="bi bi-box-seam me-1"></i>' . esc(lang('Deliveries.markDelivered')) . '</button>';
    $buttons .= '<a href="' . site_url("deliveries/{$did}/edit") . '" class="btn btn-outline-primary"><i class="bi bi-pencil me-1"></i>' . esc(lang('App.edit')) . '</a>';
    $buttons .= '<button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancel-modal"><i class="bi bi-x-circle me-1"></i>' . esc(lang('App.cancel')) . '</button>';
}

$eventIcons = [
    'CREATED'   => ['file-earmark-plus', 'text-body-secondary'],
    'SHIPPED'   => ['truck', 'text-primary'],
    'UPDATE'    => ['geo-alt', 'text-info-emphasis'],
    'DELIVERED' => ['box-seam', 'text-success'],
    'CANCELLED' => ['x-circle', 'text-danger'],
];
?>
<?= $this->extend('layouts/app_layout') ?>

<?= $this->section('title') ?><?= esc($note['delivery_number']) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('components/page_header', [
    'title'      => lang('Deliveries.deliveryNoteNo', [$note['delivery_number']]),
    'subtitle'   => up($note['customer_name']),
    'breadcrumb' => [[lang('Deliveries.deliveries'), site_url('deliveries')], [$note['delivery_number'], '']],
    'actions'    => $buttons,
]) ?>

<?php if ($status === DeliveryNoteModel::CANCELLED) : ?>
    <div class="alert alert-dark small"><i class="bi bi-x-circle me-1"></i><?= esc(lang('Deliveries.cancelledInfo', [format_date($note['cancelled_at'], true), ($note['cancelled_full_name'] ?? '') ? up($note['cancelled_full_name']) : ($note['cancelled_username'] ?? '-'), $note['cancel_reason']])) ?></div>
<?php elseif ($status === DeliveryNoteModel::DELIVERED) : ?>
    <div class="alert alert-success small"><i class="bi bi-check2-circle me-1"></i><?= lang('Deliveries.deliveredInfo', [esc(format_date($note['delivered_at'])), esc(up($note['received_by_name']))]) ?></div>
<?php elseif ($preparing) : ?>
    <div class="alert alert-secondary small"><i class="bi bi-box me-1"></i><?= lang('Deliveries.preparingInfo') ?></div>
<?php endif ?>

<?= view('components/order_flow', ['flow' => $flow, 'current' => site_url("deliveries/{$did}")]) ?>

<div class="row g-3">
    <div class="col-12 col-xl-4">
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
                <span><?= lang('App.details') ?></span><?= DeliveryNoteModel::badge($note) ?>
            </div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-5 text-body-secondary fw-normal"><?= lang('App.customer') ?></dt>
                    <dd class="col-7"><?= customer_link($note['customer_id'], $note['customer_name']) ?></dd>
                    <dt class="col-5 text-body-secondary fw-normal"><?= lang('Payments.invoice') ?></dt>
                    <dd class="col-7"><a href="<?= site_url('invoices/' . $note['invoice_id']) ?>" class="font-monospace"><?= esc($note['invoice_number']) ?></a></dd>
                    <dt class="col-5 text-body-secondary fw-normal"><?= lang('Deliveries.shipDate') ?></dt>
                    <dd class="col-7"><?= esc(format_date($note['ship_date']) ?: '-') ?><?= $preparing ? ' <span class="text-body-secondary">' . esc(lang('Deliveries.planned')) . '</span>' : '' ?></dd>
                    <dt class="col-5 text-body-secondary fw-normal"><?= lang('Deliveries.shipment') ?></dt>
                    <dd class="col-7"><?= esc($note['shipment_name']) ?><?= $note['courier_name'] ? '<div class="text-body-secondary">' . esc(up($note['courier_name'])) . '</div>' : '' ?></dd>
                    <dt class="col-5 text-body-secondary fw-normal"><?= lang('Deliveries.tracking') ?></dt>
                    <dd class="col-7 font-monospace"><?= esc($note['tracking_number'] ?: '-') ?></dd>
                    <dt class="col-5 text-body-secondary fw-normal"><?= lang('Deliveries.shipTo') ?></dt>
                    <dd class="col-7"><?= nl2br(esc(up($note['shipping_address']))) ?></dd>
                    <?php if ($note['note']) : ?>
                        <dt class="col-5 text-body-secondary fw-normal"><?= lang('App.note') ?></dt>
                        <dd class="col-7"><?= esc($note['note']) ?></dd>
                    <?php endif ?>
                    <dt class="col-5 text-body-secondary fw-normal"><?= lang('App.createdBy') ?></dt>
                    <dd class="col-7 mb-0"><?= esc(($note['full_name'] ?? '') ? up($note['full_name']) : ($note['username'] ?? '-')) ?><div class="text-body-secondary"><?= esc(format_date($note['created_at'], true)) ?></div></dd>
                </dl>
            </div>
        </div>

        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold"><?= lang('Deliveries.tracking') ?></div>
            <div class="card-body">
                <ul class="status-timeline small">
                    <?php foreach ($events as $e) : ?>
                        <?php [$icon, $color] = $eventIcons[$e['event_type']] ?? ['dot', ''] ?>
                        <li>
                            <div class="fw-semibold <?= $color ?>"><i class="bi bi-<?= $icon ?> me-1"></i><?= esc(code_label('deliveryEvent', $e['event_type'])) ?><?= $e['location'] ? ' · ' . esc(up($e['location'])) : '' ?></div>
                            <div class="text-body-secondary mt-1"><?= esc(format_date($e['event_at'], true)) ?> · <?= esc(($e['full_name'] ?? '') ? up($e['full_name']) : ($e['username'] ?? '-')) ?></div>
                            <?php if (! empty($e['note'])) : ?><div class="mt-1 text-break"><?= nl2br(esc($e['note'])) ?></div><?php endif ?>
                        </li>
                    <?php endforeach ?>
                    <?php if ($events === []) : ?><li class="text-body-secondary"><?= lang('Deliveries.noEvents') ?></li><?php endif ?>
                </ul>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-8">
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold"><?= lang('Deliveries.goods') ?></div>
            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead class="table-light small">
                        <tr><th class="ps-3">#</th><th><?= lang('App.description') ?></th><th class="text-end"><?= lang('Invoices.invoiced') ?></th><th class="text-end pe-3"><?= lang('Deliveries.inThisDelivery') ?></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $i => $item) : ?>
                            <tr>
                                <td class="ps-3 small text-body-secondary"><?= $i + 1 ?></td>
                                <td><?= esc(up($item['item_title'])) ?><div class="small text-body-secondary"><?= esc($item['pl_sku'] ?? '') ?></div></td>
                                <td class="text-end small text-nowrap"><?= esc(format_qty($item['invoiced_quantity'])) ?> <?= esc($item['unit_code']) ?></td>
                                <td class="text-end text-nowrap pe-3 fw-semibold"><?= esc(format_qty($item['quantity'])) ?> <?= esc($item['unit_code']) ?></td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?= view('components/order_files', ['files' => $flow['files'], 'default' => "delivery_note:{$did}", 'returnTo' => "deliveries/{$did}"]) ?>

<?php if ($isOpen && $canManage) : ?>
    <?php if ($preparing) : ?>
        <div class="modal fade" id="shipped-modal" tabindex="-1" aria-labelledby="shipped-modal-title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form class="modal-content" action="<?= site_url("deliveries/{$did}/shipped") ?>" method="post">
                    <?= csrf_field() ?>
                    <div class="modal-header">
                        <h5 class="modal-title" id="shipped-modal-title"><i class="bi bi-truck me-1"></i><?= lang('Deliveries.markShipped') ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= esc(lang('App.close'), 'attr') ?>"></button>
                    </div>
                    <div class="modal-body row g-3">
                        <div class="col-12 col-sm-5">
                            <label for="shipped_date" class="form-label"><?= lang('Deliveries.shipDate') ?> <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="shipped_date" name="shipped_date" required value="<?= $today ?>" max="<?= $today ?>">
                        </div>
                        <div class="col-12 col-sm-7">
                            <label for="ship_location" class="form-label"><?= lang('Deliveries.from') ?></label>
                            <input type="text" class="form-control text-uppercase" id="ship_location" name="location" maxlength="128" placeholder="<?= esc(lang('Deliveries.fromHint'), 'attr') ?>">
                        </div>
                        <div class="col-12 col-sm-5">
                            <label for="ship_courier" class="form-label"><?= lang('Deliveries.courier') ?></label>
                            <input type="text" class="form-control text-uppercase" id="ship_courier" name="courier_name" maxlength="64" value="<?= esc((string) $note['courier_name']) ?>" placeholder="<?= esc(lang('Deliveries.courierHintShort'), 'attr') ?>">
                        </div>
                        <div class="col-12 col-sm-7">
                            <label for="ship_tracking" class="form-label"><?= lang('Deliveries.trackingNumber') ?></label>
                            <input type="text" class="form-control" id="ship_tracking" name="tracking_number" maxlength="64" value="<?= esc((string) $note['tracking_number']) ?>">
                        </div>
                        <div class="col-12">
                            <label for="ship_note" class="form-label"><?= lang('App.note') ?></label>
                            <input type="text" class="form-control" id="ship_note" name="note" maxlength="2000" placeholder="<?= esc(lang('Deliveries.shipNoteHint'), 'attr') ?>">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= lang('App.close') ?></button>
                        <button type="submit" class="btn btn-primary"><?= lang('Deliveries.markShipped') ?></button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif ?>
        <div class="modal fade" id="track-modal" tabindex="-1" aria-labelledby="track-modal-title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form class="modal-content" action="<?= site_url("deliveries/{$did}/track") ?>" method="post">
                    <?= csrf_field() ?>
                    <div class="modal-header">
                        <h5 class="modal-title" id="track-modal-title"><i class="bi bi-geo-alt me-1"></i><?= lang('Deliveries.addTracking') ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= esc(lang('App.close'), 'attr') ?>"></button>
                    </div>
                    <div class="modal-body row g-3">
                        <div class="col-12 col-sm-5">
                            <label for="event_date" class="form-label"><?= lang('App.date') ?> <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="event_date" name="event_date" required value="<?= $today ?>" max="<?= $today ?>"
                                   <?= $note['ship_date'] && ! $preparing ? 'min="' . esc($note['ship_date'], 'attr') . '"' : '' ?>>
                        </div>
                        <div class="col-12 col-sm-7">
                            <label for="event_location" class="form-label"><?= lang('Deliveries.location') ?></label>
                            <input type="text" class="form-control text-uppercase" id="event_location" name="location" maxlength="128" placeholder="<?= esc(lang('Deliveries.locationHint'), 'attr') ?>">
                        </div>
                        <div class="col-12">
                            <label for="event_note" class="form-label"><?= lang('App.note') ?></label>
                            <input type="text" class="form-control" id="event_note" name="note" maxlength="2000" placeholder="<?= esc(lang('Deliveries.eventNoteHint'), 'attr') ?>">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= lang('App.close') ?></button>
                        <button type="submit" class="btn btn-primary"><?= lang('Deliveries.addUpdate') ?></button>
                    </div>
                </form>
            </div>
        </div>

    <div class="modal fade" id="delivered-modal" tabindex="-1" aria-labelledby="delivered-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" action="<?= site_url("deliveries/{$did}/delivered") ?>" method="post">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="delivered-modal-title"><i class="bi bi-box-seam me-1"></i><?= lang('Deliveries.markDelivered') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= esc(lang('App.close'), 'attr') ?>"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-12 col-sm-5">
                        <label for="delivered_date" class="form-label"><?= lang('Deliveries.deliveryDate') ?> <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="delivered_date" name="delivered_date" required value="<?= $today ?>" max="<?= $today ?>"
                               <?= $note['ship_date'] && ! $preparing ? 'min="' . esc($note['ship_date'], 'attr') . '"' : '' ?>>
                    </div>
                    <div class="col-12 col-sm-7">
                        <label for="received_by_name" class="form-label"><?= lang('Deliveries.receivedBy') ?> <span class="text-danger">*</span></label>
                        <input type="text" class="form-control text-uppercase" id="received_by_name" name="received_by_name" maxlength="128" required placeholder="<?= esc(lang('Deliveries.receivedByHint'), 'attr') ?>">
                    </div>
                    <div class="col-12 small text-body-secondary"><?= lang('Deliveries.lockedAfter') ?></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= lang('App.close') ?></button>
                    <button type="submit" class="btn btn-success"><?= lang('Deliveries.confirmDelivery') ?></button>
                </div>
            </form>
        </div>
    </div>
    <?= view('components/reason_modal', [
        'id'     => 'cancel-modal',
        'action' => site_url("deliveries/{$did}/cancel"),
        'title'  => lang('Deliveries.cancelTitle', [$note['delivery_number']]),
        'help'   => lang('Deliveries.cancelHelp'),
        'button' => lang('Deliveries.cancelBtn'),
        'field'  => 'cancel_reason',
    ]) ?>
<?php endif ?>

<?= $this->endSection() ?>
