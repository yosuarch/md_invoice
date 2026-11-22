<?php
/**
 * @var list<array<string, mixed>>  $proposals
 * @var \CodeIgniter\Pager\Pager    $pager
 * @var array<string, string>       $filters
 * @var array<string, mixed>|null   $customer  Customer filter, if any.
 * @var array<int, string>          $reps
 * @var bool                        $hasMyRep
 */

use App\Services\ProposalWorkflow;

$canManage = user_can('proposals.manage');
$filtered  = array_filter($filters) !== [];
$today     = date('Y-m-d');
$newUrl    = site_url('proposals/new' . ($customer !== null ? '?customer=' . $customer['id'] : ''));
?>
<?= $this->extend('layouts/app_layout') ?>

<?= $this->section('title') ?><?= lang('Proposals.proposals') ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('components/page_header', [
    'title'    => lang('Proposals.proposals'),
    'subtitle' => $customer !== null ? lang('Proposals.listFor', [up($customer['cl_organization_name'])]) : lang('Proposals.listSubtitle'),
    'actions'  => $canManage ? '<a href="' . $newUrl . '" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>' . esc(lang('Proposals.newProposal')) . '</a>' : '',
]) ?>

<form method="get" class="card shadow-sm mb-3" data-live-filter>
    <?php if ($customer !== null) : ?><input type="hidden" name="customer" value="<?= (int) $customer['id'] ?>"><?php endif ?>
    <div class="card-body row g-2 align-items-end">
        <div class="col-12 col-lg-5">
            <label for="q" class="form-label small mb-1"><?= lang('App.search') ?></label>
            <input type="search" class="form-control" id="q" name="q" value="<?= esc($filters['q']) ?>" placeholder="<?= esc(lang('Proposals.searchHint'), 'attr') ?>">
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <label for="status" class="form-label small mb-1"><?= lang('App.status') ?></label>
            <select class="form-select" id="status" name="status">
                <option value=""><?= lang('App.all') ?></option>
                <option value="open"<?= $filters['status'] === 'open' ? ' selected' : '' ?>><?= lang('Proposals.statusOpen') ?></option>
                <option value="to_invoice"<?= $filters['status'] === 'to_invoice' ? ' selected' : '' ?>><?= lang('Proposals.statusToInvoice') ?></option>
                <?php foreach (array_keys(ProposalWorkflow::LABELS) as $code) : ?>
                    <option value="<?= $code ?>"<?= $filters['status'] === $code ? ' selected' : '' ?>><?= esc(ProposalWorkflow::label($code)) ?></option>
                <?php endforeach ?>
            </select>
        </div>
        <div class="col-6 col-md-4 col-lg-3">
            <label for="rep" class="form-label small mb-1"><?= lang('Proposals.salesRep') ?></label>
            <select class="form-select" id="rep" name="rep">
                <option value=""><?= lang('App.all') ?></option>
                <?php if ($hasMyRep) : ?>
                    <option value="mine"<?= $filters['rep'] === 'mine' ? ' selected' : '' ?>><?= lang('Proposals.myProposals') ?></option>
                <?php endif ?>
                <?php foreach ($reps as $id => $name) : ?>
                    <option value="<?= $id ?>"<?= $filters['rep'] === (string) $id ? ' selected' : '' ?>><?= esc(up($name)) ?></option>
                <?php endforeach ?>
            </select>
        </div>
        <div class="col-12 col-md-4 col-lg-2 d-flex gap-2">
            <button type="submit" class="btn btn-outline-primary flex-grow-1"><i class="bi bi-search me-1"></i><?= lang('App.filter') ?></button>
            <?php if ($filtered) : ?>
                <a href="<?= site_url('proposals') ?>" class="btn btn-outline-secondary" title="<?= esc(lang('App.clearFilters'), 'attr') ?>"><i class="bi bi-x-lg"></i></a>
            <?php endif ?>
        </div>
    </div>
</form>

<div class="card shadow-sm" id="list-results">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-dark">
                <tr>
                    <th><?= lang('App.number') ?></th>
                    <th><?= lang('Proposals.customerTitle') ?></th>
                    <th><?= lang('App.date') ?></th>
                    <th><?= lang('Proposals.validUntil') ?></th>
                    <th><?= lang('Proposals.salesRep') ?></th>
                    <th class="text-end"><?= lang('App.total') ?></th>
                    <th><?= lang('App.status') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($proposals as $p) : ?>
                    <?php $expired = $p['valid_until'] !== null && $p['valid_until'] < $today && in_array($p['status_code'], ProposalWorkflow::OPEN, true) ?>
                    <tr class="position-relative">
                        <td class="text-nowrap">
                            <a href="<?= site_url('proposals/' . $p['id']) ?>" class="fw-semibold font-monospace text-decoration-none stretched-link"><?= esc($p['cpl_id']) ?></a>
                        </td>
                        <td>
                            <div class="fw-semibold"><?= esc(up($p['customer_name'])) ?></div>
                            <div class="small text-body-secondary"><?= esc(up($p['cpl_proposal_title'])) ?></div>
                        </td>
                        <td class="small text-nowrap"><?= esc(format_date($p['created_at'])) ?></td>
                        <td class="small text-nowrap<?= $expired ? ' text-danger' : '' ?>">
                            <?= esc(format_date($p['valid_until'])) ?>
                            <?php if ($expired) : ?><i class="bi bi-exclamation-circle ms-1" title="<?= esc(lang('Proposals.expired'), 'attr') ?>"></i><?php endif ?>
                        </td>
                        <td class="small"><?= esc(up($p['rep_name'] ?? '')) ?></td>
                        <td class="text-end text-nowrap"><?= esc(format_idr($p['grand_total'])) ?></td>
                        <td><?= proposal_status_badge($p['status_code']) ?></td>
                    </tr>
                <?php endforeach ?>
                <?php if ($proposals === []) : ?>
                    <tr><td colspan="7" class="text-center text-body-secondary py-4">
                        <i class="bi bi-file-earmark-text fs-3 d-block mb-1"></i>
                        <?= $filtered ? lang('Proposals.noMatch') : lang('Proposals.noneYet') ?>
                    </td></tr>
                <?php endif ?>
            </tbody>
        </table>
    </div>
    <?php if ($pager->getPageCount() > 1) : ?>
        <div class="card-footer bg-white d-flex justify-content-between align-items-center">
            <span class="small text-body-secondary"><?= lang('Proposals.count', [$pager->getTotal()]) ?></span>
            <?= $pager->links('default', 'bootstrap') ?>
        </div>
    <?php endif ?>
</div>

<?= $this->endSection() ?>
