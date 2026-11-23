<?php
/**
 * @var list<array<string, mixed>>  $invoices
 * @var \CodeIgniter\Pager\Pager    $pager
 * @var array<string, string>       $filters
 * @var array<string, mixed>|null   $customer  Customer filter, if any.
 * @var array<int, string>          $reps
 * @var bool                        $hasMyRep
 */

use App\Models\InvoiceModel;
use App\Services\InvoiceWorkflow;

$filtered = array_filter($filters) !== [];
?>
<?= $this->extend('layouts/app_layout') ?>

<?= $this->section('title') ?><?= lang('Invoices.invoices') ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('components/page_header', [
    'title'    => lang('Invoices.invoices'),
    'subtitle' => $customer !== null ? lang('Invoices.listOf', [up($customer['cl_organization_name'])]) : lang('Invoices.listSubtitle'),
    'actions'  => user_can('proposals.view') ? '<a href="' . site_url('proposals?status=to_invoice') . '" class="btn btn-outline-primary"><i class="bi bi-check2-circle me-1"></i>' . esc(lang('Invoices.proposalsToInvoice')) . '</a>' : '',
]) ?>

<form method="get" class="card shadow-sm mb-3" data-live-filter>
    <?php if ($customer !== null) : ?><input type="hidden" name="customer" value="<?= (int) $customer['id'] ?>"><?php endif ?>
    <div class="card-body row g-2 align-items-end">
        <div class="col-12 col-lg-5">
            <label for="q" class="form-label small mb-1"><?= lang('App.search') ?></label>
            <input type="search" class="form-control" id="q" name="q" value="<?= esc($filters['q']) ?>" placeholder="<?= esc(lang('Invoices.searchHint'), 'attr') ?>">
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <label for="status" class="form-label small mb-1"><?= lang('App.status') ?></label>
            <select class="form-select" id="status" name="status">
                <option value=""><?= lang('App.all') ?></option>
                <option value="unpaid"<?= $filters['status'] === 'unpaid' ? ' selected' : '' ?>><?= lang('Invoices.fUnpaid') ?></option>
                <option value="past_due"<?= $filters['status'] === 'past_due' ? ' selected' : '' ?>><?= lang('Invoices.fPastDue') ?></option>
                <option value="to_ship"<?= $filters['status'] === 'to_ship' ? ' selected' : '' ?>><?= lang('Invoices.fToShip') ?></option>
                <option value="refund_due"<?= $filters['status'] === 'refund_due' ? ' selected' : '' ?>><?= lang('Invoices.fRefundDue') ?></option>
                <?php foreach (array_keys(InvoiceWorkflow::LABELS) as $code) : ?>
                    <option value="<?= $code ?>"<?= $filters['status'] === $code ? ' selected' : '' ?>><?= esc(InvoiceWorkflow::label($code)) ?></option>
                <?php endforeach ?>
            </select>
        </div>
        <div class="col-6 col-md-4 col-lg-3">
            <label for="rep" class="form-label small mb-1"><?= lang('Proposals.salesRep') ?></label>
            <select class="form-select" id="rep" name="rep">
                <option value=""><?= lang('App.all') ?></option>
                <?php if ($hasMyRep) : ?>
                    <option value="mine"<?= $filters['rep'] === 'mine' ? ' selected' : '' ?>><?= lang('Invoices.myInvoices') ?></option>
                <?php endif ?>
                <?php foreach ($reps as $id => $name) : ?>
                    <option value="<?= $id ?>"<?= $filters['rep'] === (string) $id ? ' selected' : '' ?>><?= esc(up($name)) ?></option>
                <?php endforeach ?>
            </select>
        </div>
        <div class="col-12 col-md-4 col-lg-2 d-flex gap-2">
            <button type="submit" class="btn btn-outline-primary flex-grow-1"><i class="bi bi-search me-1"></i><?= lang('App.filter') ?></button>
            <?php if ($filtered) : ?>
                <a href="<?= site_url('invoices') ?>" class="btn btn-outline-secondary" title="<?= esc(lang('App.clearFilters'), 'attr') ?>"><i class="bi bi-x-lg"></i></a>
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
                    <th><?= lang('App.date') ?></th>
                    <th><?= lang('Invoices.dueDate') ?></th>
                    <th class="text-end"><?= lang('App.total') ?></th>
                    <th class="text-end"><?= lang('Invoices.balance') ?></th>
                    <th><?= lang('App.status') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invoices as $inv) : ?>
                    <?php
                    $isDraft = $inv['status_code'] === InvoiceWorkflow::DRAFT;
                    $pastDue = InvoiceModel::isPastDue($inv);
                    $balance = InvoiceModel::balance($inv);
                    ?>
                    <tr class="position-relative">
                        <td class="text-nowrap">
                            <a href="<?= site_url('invoices/' . $inv['id']) ?>" class="fw-semibold font-monospace text-decoration-none stretched-link<?= $isDraft ? ' text-body-secondary' : '' ?>"><?= esc(InvoiceModel::displayNumber($inv)) ?></a>
                            <?php if ($inv['proposal_number']) : ?><div class="small text-body-secondary font-monospace"><?= esc($inv['proposal_number']) ?></div><?php endif ?>
                        </td>
                        <td>
                            <div class="fw-semibold"><?= esc(up($inv['customer_name'])) ?></div>
                            <?php if ($inv['funding_source_name']) : ?><div class="small text-body-secondary"><?= esc($inv['funding_source_name']) ?></div><?php endif ?>
                        </td>
                        <td class="small text-nowrap"><?= $isDraft ? '<span class="text-body-secondary">-</span>' : esc(format_date($inv['issue_date'])) ?></td>
                        <td class="small text-nowrap<?= $pastDue ? ' text-danger fw-semibold' : '' ?>">
                            <?= $isDraft ? '<span class="text-body-secondary">-</span>' : esc(format_date($inv['due_date'])) ?>
                            <?php if ($pastDue) : ?><i class="bi bi-exclamation-circle ms-1" title="<?= esc(lang('Invoices.pastDue'), 'attr') ?>"></i><?php endif ?>
                        </td>
                        <td class="text-end text-nowrap"><?= esc(format_idr($inv['grand_total'])) ?></td>
                        <td class="text-end text-nowrap<?= in_array($inv['status_code'], InvoiceWorkflow::PAYABLE, true) ? ' fw-semibold' : ' text-body-secondary' ?>">
                            <?php if (in_array($inv['status_code'], [InvoiceWorkflow::DRAFT, InvoiceWorkflow::VOID], true)) : ?>-
                            <?php elseif (bccomp($balance, '0', 2) < 0) : ?><span class="text-warning-emphasis" title="<?= esc(lang('Invoices.creditBalanceRefund'), 'attr') ?>"><?= esc(format_idr($balance)) ?></span>
                            <?php else : ?><?= esc(format_idr($balance)) ?><?php endif ?>
                        </td>
                        <td><?= invoice_status_badge($inv['status_code']) ?></td>
                    </tr>
                <?php endforeach ?>
                <?php if ($invoices === []) : ?>
                    <tr><td colspan="7" class="text-center text-body-secondary py-4">
                        <i class="bi bi-receipt fs-3 d-block mb-1"></i>
                        <?= $filtered ? lang('Invoices.noMatch') : lang('Invoices.noneYetLong') ?>
                    </td></tr>
                <?php endif ?>
            </tbody>
        </table>
    </div>
    <?php if ($pager->getPageCount() > 1) : ?>
        <div class="card-footer bg-white d-flex justify-content-between align-items-center">
            <span class="small text-body-secondary"><?= lang('Invoices.count', [$pager->getTotal()]) ?></span>
            <?= $pager->links('default', 'bootstrap') ?>
        </div>
    <?php endif ?>
</div>

<?= $this->endSection() ?>
