<?php
/**
 * @var list<array<string, mixed>> $notes
 * @var \CodeIgniter\Pager\Pager   $pager
 * @var array<string, string>      $filters
 */

$filtered = array_filter($filters) !== [];
?>
<?= $this->extend('layouts/app_layout') ?>

<?= $this->section('title') ?><?= lang('CreditNotes.creditNotes') ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('components/page_header', [
    'title'    => lang('CreditNotes.creditNotes'),
    'subtitle' => lang('CreditNotes.listSubtitle'),
]) ?>

<form method="get" class="card shadow-sm mb-3" data-live-filter>
    <div class="card-body row g-2 align-items-end">
        <div class="col-12 col-lg-8">
            <label for="q" class="form-label small mb-1"><?= lang('App.search') ?></label>
            <input type="search" class="form-control" id="q" name="q" value="<?= esc($filters['q']) ?>" placeholder="<?= esc(lang('CreditNotes.searchHint'), 'attr') ?>">
        </div>
        <div class="col-12 col-lg-4 d-flex gap-2">
            <button type="submit" class="btn btn-outline-primary flex-grow-1"><i class="bi bi-search me-1"></i><?= lang('App.filter') ?></button>
            <?php if ($filtered) : ?>
                <a href="<?= site_url('credit-notes') ?>" class="btn btn-outline-secondary" title="<?= esc(lang('App.clearFilters'), 'attr') ?>"><i class="bi bi-x-lg"></i></a>
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
                    <th><?= lang('App.date') ?></th>
                    <th><?= lang('Payments.invoice') ?></th>
                    <th><?= lang('CreditNotes.customerReason') ?></th>
                    <th class="text-end"><?= lang('App.total') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($notes as $n) : ?>
                    <tr class="position-relative">
                        <td class="text-nowrap"><a href="<?= site_url('credit-notes/' . $n['id']) ?>" class="font-monospace fw-semibold text-decoration-none stretched-link"><?= esc($n['credit_note_number']) ?></a></td>
                        <td class="small text-nowrap"><?= esc(format_date($n['issue_date'])) ?></td>
                        <td class="small font-monospace text-nowrap"><?= esc($n['invoice_number']) ?></td>
                        <td><div class="fw-semibold"><?= esc(up($n['customer_name'])) ?></div><div class="small text-body-secondary"><?= esc(mb_strimwidth($n['reason'], 0, 100, '…')) ?></div></td>
                        <td class="text-end text-nowrap"><?= esc(format_idr($n['grand_total'])) ?></td>
                    </tr>
                <?php endforeach ?>
                <?php if ($notes === []) : ?>
                    <tr><td colspan="5" class="text-center text-body-secondary py-4">
                        <i class="bi bi-arrow-return-left fs-3 d-block mb-1"></i>
                        <?= $filtered ? lang('CreditNotes.noMatch') : lang('CreditNotes.noneYet') ?>
                    </td></tr>
                <?php endif ?>
            </tbody>
        </table>
    </div>
    <?php if ($pager->getPageCount() > 1) : ?>
        <div class="card-footer bg-white d-flex justify-content-between align-items-center">
            <span class="small text-body-secondary"><?= lang('CreditNotes.count', [$pager->getTotal()]) ?></span>
            <?= $pager->links('default', 'bootstrap') ?>
        </div>
    <?php endif ?>
</div>

<?= $this->endSection() ?>
