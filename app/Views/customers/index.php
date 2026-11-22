<?php
/**
 * @var list<array<string, mixed>>  $customers
 * @var array<int, list<string>>    $repNames
 * @var \CodeIgniter\Pager\Pager    $pager
 * @var array<string, string>       $filters
 * @var array<int, string>          $levels
 * @var array<int, string>          $reps
 * @var bool                        $hasMyRep
 */

use App\Models\CustomerModel;

$canManage = user_can('customers.manage');
$filtered  = array_filter($filters) !== [];
?>
<?= $this->extend('layouts/app_layout') ?>

<?= $this->section('title') ?><?= lang('Customers.customers') ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('components/page_header', [
    'title'    => lang('Customers.customers'),
    'subtitle' => lang('Customers.listSubtitle'),
    'actions'  => $canManage ? '<a href="' . site_url('customers/new') . '" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>' . esc(lang('Customers.newCustomer')) . '</a>' : '',
]) ?>

<form method="get" class="card shadow-sm mb-3" data-live-filter>
    <div class="card-body row g-2 align-items-end">
        <div class="col-12 col-lg-4">
            <label for="q" class="form-label small mb-1"><?= lang('App.search') ?></label>
            <input type="search" class="form-control" id="q" name="q" value="<?= esc($filters['q']) ?>" placeholder="<?= esc(lang('Customers.searchHint'), 'attr') ?>">
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <label for="rep" class="form-label small mb-1"><?= lang('Customers.salesRep') ?></label>
            <select class="form-select" id="rep" name="rep">
                <option value=""><?= lang('App.all') ?></option>
                <?php if ($hasMyRep) : ?>
                    <option value="mine"<?= $filters['rep'] === 'mine' ? ' selected' : '' ?>><?= lang('Customers.myCustomers') ?></option>
                <?php endif ?>
                <?php foreach ($reps as $id => $name) : ?>
                    <option value="<?= $id ?>"<?= $filters['rep'] === (string) $id ? ' selected' : '' ?>><?= esc(up($name)) ?></option>
                <?php endforeach ?>
            </select>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <label for="level" class="form-label small mb-1"><?= lang('Customers.schoolLevel') ?></label>
            <select class="form-select" id="level" name="level">
                <option value=""><?= lang('App.all') ?></option>
                <?php foreach ($levels as $id => $name) : ?>
                    <option value="<?= $id ?>"<?= $filters['level'] === (string) $id ? ' selected' : '' ?>><?= esc($name) ?></option>
                <?php endforeach ?>
            </select>
        </div>
        <div class="col-6 col-md-2 col-lg-1">
            <label for="school_status" class="form-label small mb-1"><?= lang('App.type') ?></label>
            <select class="form-select" id="school_status" name="school_status">
                <option value=""><?= lang('App.all') ?></option>
                <?php foreach (array_keys(CustomerModel::SCHOOL_STATUSES) as $key) : ?>
                    <option value="<?= $key ?>"<?= $filters['school_status'] === $key ? ' selected' : '' ?>><?= esc(code_label('schoolStatusShort', $key)) ?></option>
                <?php endforeach ?>
            </select>
        </div>
        <div class="col-6 col-md-2 col-lg-1">
            <label for="status" class="form-label small mb-1"><?= lang('App.status') ?></label>
            <select class="form-select" id="status" name="status">
                <option value=""><?= lang('App.all') ?></option>
                <option value="active"<?= $filters['status'] === 'active' ? ' selected' : '' ?>><?= lang('App.active') ?></option>
                <option value="inactive"<?= $filters['status'] === 'inactive' ? ' selected' : '' ?>><?= lang('App.inactive') ?></option>
            </select>
        </div>
        <div class="col-12 col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-outline-primary flex-grow-1"><i class="bi bi-search me-1"></i><?= lang('App.filter') ?></button>
            <?php if ($filtered) : ?>
                <a href="<?= site_url('customers') ?>" class="btn btn-outline-secondary" title="<?= esc(lang('App.clearFilters'), 'attr') ?>"><i class="bi bi-x-lg"></i></a>
            <?php endif ?>
        </div>
    </div>
</form>

<div class="card shadow-sm" id="list-results">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-dark">
                <tr>
                    <th><?= lang('App.customer') ?></th>
                    <th>NPSN</th>
                    <th><?= lang('Customers.level') ?></th>
                    <th><?= lang('App.type') ?></th>
                    <th><?= lang('Customers.salesReps') ?></th>
                    <th><?= lang('Customers.contact') ?></th>
                    <th><?= lang('App.status') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($customers as $c) : ?>
                    <tr class="position-relative">
                        <td>
                            <a href="<?= site_url('customers/' . $c['id']) ?>" class="fw-semibold text-decoration-none stretched-link"><?= esc(up($c['cl_organization_name'])) ?></a>
                            <?php if (! empty($c['cl_tax_id'])) : ?><div class="small text-body-secondary">NPWP <?= esc($c['cl_tax_id']) ?></div><?php endif ?>
                        </td>
                        <td class="font-monospace small"><?= esc($c['npsn'] ?? '') ?></td>
                        <td class="small"><?= esc($c['school_level_name'] ?? '') ?></td>
                        <td class="small"><?= $c['school_status'] ? esc(code_label('schoolStatusShort', $c['school_status'])) : '' ?></td>
                        <td class="small"><?= esc(up(implode(', ', $repNames[(int) $c['id']] ?? []))) ?></td>
                        <td class="small">
                            <?= esc($c['cl_phone'] ?? '') ?>
                            <?php if (! empty($c['cl_email'])) : ?><div class="text-body-secondary text-break"><?= esc($c['cl_email']) ?></div><?php endif ?>
                        </td>
                        <td><?= active_badge($c['is_active']) ?></td>
                    </tr>
                <?php endforeach ?>
                <?php if ($customers === []) : ?>
                    <tr><td colspan="7" class="text-center text-body-secondary py-4">
                        <i class="bi bi-building fs-3 d-block mb-1"></i>
                        <?= $filtered ? lang('Customers.noMatch') : lang('Customers.noneYet') ?>
                    </td></tr>
                <?php endif ?>
            </tbody>
        </table>
    </div>
    <?php if ($pager->getPageCount() > 1) : ?>
        <div class="card-footer bg-white d-flex justify-content-between align-items-center">
            <span class="small text-body-secondary"><?= lang('Customers.count', [$pager->getTotal()]) ?></span>
            <?= $pager->links('default', 'bootstrap') ?>
        </div>
    <?php endif ?>
</div>

<?= $this->endSection() ?>
