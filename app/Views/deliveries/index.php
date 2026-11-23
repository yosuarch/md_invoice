<?php
/**
 * @var list<array<string, mixed>> $notes
 * @var \CodeIgniter\Pager\Pager   $pager
 * @var array<string, string>      $filters
 * @var array<string, mixed>|null  $customer
 */

use App\Models\DeliveryNoteModel;

$filtered = array_filter($filters) !== [];
?>
<?= $this->extend('layouts/app_layout') ?>

<?= $this->section('title') ?><?= lang('Deliveries.deliveries') ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('components/page_header', [
    'title'    => lang('Deliveries.deliveries'),
    'subtitle' => $customer !== null ? lang('Deliveries.listTo', [up($customer['cl_organization_name'])]) : lang('Deliveries.listSubtitle'),
    'actions'  => user_can('invoices.view') ? '<a href="' . site_url('invoices?status=to_ship') . '" class="btn btn-outline-primary"><i class="bi bi-box-seam me-1"></i>' . esc(lang('Deliveries.invoicesToShip')) . '</a>' : '',
]) ?>

<form method="get" class="card shadow-sm mb-3" data-live-filter>
    <?php if ($customer !== null) : ?><input type="hidden" name="customer" value="<?= (int) $customer['id'] ?>"><?php endif ?>
    <div class="card-body row g-2 align-items-end">
        <div class="col-12 col-lg-6">
            <label for="q" class="form-label small mb-1"><?= lang('App.search') ?></label>
            <input type="search" class="form-control" id="q" name="q" value="<?= esc($filters['q']) ?>" placeholder="<?= esc(lang('Deliveries.searchHint'), 'attr') ?>">
        </div>
        <div class="col-6 col-lg-3">
            <label for="status" class="form-label small mb-1"><?= lang('App.status') ?></label>
            <select class="form-select" id="status" name="status">
                <option value=""><?= lang('App.all') ?></option>
                <option value="open"<?= $filters['status'] === 'open' ? ' selected' : '' ?>><?= lang('Deliveries.fOpen') ?></option>
                <?php foreach (array_keys(DeliveryNoteModel::STATUSES) as $code) : ?>
                    <option value="<?= $code ?>"<?= $filters['status'] === $code ? ' selected' : '' ?>><?= esc(code_label('deliveryStatus', $code)) ?></option>
                <?php endforeach ?>
            </select>
        </div>
        <div class="col-6 col-lg-3 d-flex gap-2">
            <button type="submit" class="btn btn-outline-primary flex-grow-1"><i class="bi bi-search me-1"></i><?= lang('App.filter') ?></button>
            <?php if ($filtered) : ?>
                <a href="<?= site_url('deliveries') ?>" class="btn btn-outline-secondary" title="<?= esc(lang('App.clearFilters'), 'attr') ?>"><i class="bi bi-x-lg"></i></a>
            <?php endif ?>
        </div>
    </div>
</form>

<div class="card shadow-sm" id="list-results">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 table-stack">
            <thead class="table-dark">
                <tr>
                    <th><?= lang('App.number') ?></th>
                    <th><?= lang('App.customer') ?></th>
                    <th><?= lang('Payments.invoice') ?></th>
                    <th><?= lang('Deliveries.shipDate') ?></th>
                    <th><?= lang('Deliveries.shipmentTracking') ?></th>
                    <th><?= lang('App.status') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($notes as $n) : ?>
                    <tr class="position-relative">
                        <td class="text-nowrap"><a href="<?= site_url('deliveries/' . $n['id']) ?>" class="font-monospace fw-semibold text-decoration-none stretched-link"><?= esc($n['delivery_number']) ?></a></td>
                        <td><?= esc(up($n['customer_name'])) ?></td>
                        <td class="small font-monospace text-nowrap"><?= esc($n['invoice_number']) ?></td>
                        <td class="small text-nowrap"><?= esc(format_date($n['ship_date'])) ?></td>
                        <td class="small"><?= esc($n['courier_name'] ? up($n['courier_name']) : $n['shipment_name']) ?><?= $n['tracking_number'] ? '<div class="font-monospace text-body-secondary">' . esc($n['tracking_number']) . '</div>' : '' ?></td>
                        <td><?= DeliveryNoteModel::badge($n) ?><?= $n['delivered_at'] ? '<div class="small text-body-secondary">' . esc(format_date($n['delivered_at'])) . '</div>' : '' ?></td>
                    </tr>
                <?php endforeach ?>
                <?php if ($notes === []) : ?>
                    <tr><td colspan="6" class="text-center text-body-secondary py-4">
                        <i class="bi bi-truck fs-3 d-block mb-1"></i>
                        <?= $filtered ? lang('Deliveries.noMatch') : lang('Deliveries.noneYet') ?>
                    </td></tr>
                <?php endif ?>
            </tbody>
        </table>
    </div>
    <?php if ($pager->getPageCount() > 1) : ?>
        <div class="card-footer bg-white d-flex justify-content-between align-items-center">
            <span class="small text-body-secondary"><?= lang('Deliveries.count', [$pager->getTotal()]) ?></span>
            <?= $pager->links('default', 'bootstrap') ?>
        </div>
    <?php endif ?>
</div>

<?= $this->endSection() ?>
