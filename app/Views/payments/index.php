<?php
/**
 * @var list<array<string, mixed>> $payments
 * @var \CodeIgniter\Pager\Pager   $pager
 * @var array<string, string>      $filters  q, month (Y-m)
 * @var string|null                $monthSum Cash received in the filtered month.
 */

$filtered = array_filter($filters) !== [];
?>
<?= $this->extend('layouts/app_layout') ?>

<?= $this->section('title') ?><?= lang('Payments.payments') ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('components/page_header', [
    'title'    => lang('Payments.payments'),
    'subtitle' => lang('Payments.listSubtitle'),
]) ?>

<form method="get" class="card shadow-sm mb-3" data-live-filter>
    <div class="card-body row g-2 align-items-end">
        <div class="col-12 col-lg-6">
            <label for="q" class="form-label small mb-1"><?= lang('App.search') ?></label>
            <input type="search" class="form-control" id="q" name="q" value="<?= esc($filters['q']) ?>" placeholder="<?= esc(lang('Payments.searchHint'), 'attr') ?>">
        </div>
        <div class="col-6 col-md-4 col-lg-3">
            <label for="month" class="form-label small mb-1"><?= lang('Payments.month') ?></label>
            <input type="month" class="form-control" id="month" name="month" value="<?= esc($filters['month']) ?>">
        </div>
        <div class="col-6 col-md-4 col-lg-3 d-flex gap-2">
            <button type="submit" class="btn btn-outline-primary flex-grow-1"><i class="bi bi-search me-1"></i><?= lang('App.filter') ?></button>
            <?php if ($filtered) : ?>
                <a href="<?= site_url('payments') ?>" class="btn btn-outline-secondary" title="<?= esc(lang('App.clearFilters'), 'attr') ?>"><i class="bi bi-x-lg"></i></a>
            <?php endif ?>
        </div>
    </div>
</form>

<div id="list-results">
<?php if ($monthSum !== null) : ?>
    <div class="alert alert-light border small"><i class="bi bi-cash-coin me-1"></i><?= esc(lang('Payments.cashInMonth', [month_label($filters['month'])])) ?> <strong><?= esc(format_idr($monthSum)) ?></strong></div>
<?php endif ?>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 table-stack">
            <thead class="table-dark">
                <tr>
                    <th><?= lang('App.date') ?></th>
                    <th><?= lang('Payments.invoice') ?></th>
                    <th><?= lang('App.customer') ?></th>
                    <th><?= lang('Invoices.methodReference') ?></th>
                    <th class="text-end"><?= lang('Invoices.cash') ?></th>
                    <th class="text-end"><?= lang('Invoices.withheld') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $p) : ?>
                    <tr class="position-relative">
                        <td class="small text-nowrap"><?= esc(format_date($p['payment_date'])) ?></td>
                        <td class="text-nowrap"><a href="<?= site_url('invoices/' . $p['invoice_id']) ?>" class="font-monospace fw-semibold text-decoration-none stretched-link"><?= esc($p['invoice_number']) ?></a></td>
                        <td><?= esc(up($p['customer_name'])) ?></td>
                        <td class="small"><?= esc($p['method_name']) ?><?= $p['bank_name'] ? ' · ' . esc(up($p['bank_name'])) : '' ?><?= $p['reference_number'] ? '<div class="font-monospace text-body-secondary">' . esc($p['reference_number']) . '</div>' : '' ?></td>
                        <td class="text-end text-nowrap<?= $p['reversed_at'] !== null ? ' text-decoration-line-through text-body-tertiary' : '' ?>"><?= esc(format_idr($p['amount'])) ?><?= $p['reversed_at'] !== null ? '<div class="small"><span class="badge bg-danger-subtle text-danger-emphasis border border-danger-subtle">' . esc(lang('Invoices.reversed')) . '</span></div>' : '' ?></td>
                        <td class="text-end text-nowrap"><?= (float) $p['withheld'] > 0 ? esc(format_idr($p['withheld'])) : '<span class="text-body-secondary">-</span>' ?></td>
                    </tr>
                <?php endforeach ?>
                <?php if ($payments === []) : ?>
                    <tr><td colspan="6" class="text-center text-body-secondary py-4">
                        <i class="bi bi-cash-coin fs-3 d-block mb-1"></i>
                        <?= $filtered ? lang('Payments.noMatch') : lang('Payments.noneYet') ?>
                    </td></tr>
                <?php endif ?>
            </tbody>
        </table>
    </div>
    <?php if ($pager->getPageCount() > 1) : ?>
        <div class="card-footer bg-white d-flex justify-content-between align-items-center">
            <span class="small text-body-secondary"><?= lang('Payments.count', [$pager->getTotal()]) ?></span>
            <?= $pager->links('default', 'bootstrap') ?>
        </div>
    <?php endif ?>
</div>
</div>

<?= $this->endSection() ?>
