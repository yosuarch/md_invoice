<?php
/** @var list<array<string, mixed>> $reps */
$canManage = user_can('customers.manage');
?>
<?= $this->extend('layouts/app_layout') ?>

<?= $this->section('title') ?><?= lang('SalesReps.title') ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('components/page_header', [
    'title'    => lang('SalesReps.title'),
    'subtitle' => lang('SalesReps.subtitle'),
    'actions'  => $canManage ? '<a href="' . site_url('sales-reps/new') . '" class="btn btn-primary"><i class="bi bi-person-plus me-1"></i>' . esc(lang('SalesReps.add')) . '</a>' : '',
]) ?>

<div class="card shadow-sm" id="list-results">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 table-stack">
            <thead class="table-dark">
                <tr>
                    <th><?= lang('Customers.name') ?></th>
                    <th><?= lang('Customers.phone') ?></th>
                    <th><?= lang('Customers.email') ?></th>
                    <th><?= lang('SalesReps.loginAccount') ?></th>
                    <th class="text-end"><?= lang('Customers.customers') ?></th>
                    <th><?= lang('App.status') ?></th>
                    <?php if ($canManage) : ?><th class="text-end"><?= lang('App.actions') ?></th><?php endif ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reps as $r) : ?>
                    <tr>
                        <td class="fw-semibold"><?= esc(up($r['rep_name'])) ?></td>
                        <td class="small"><?= esc($r['rep_phone'] ?? '') ?></td>
                        <td class="small text-break"><?= esc($r['rep_email'] ?? '') ?></td>
                        <td class="small"><?= $r['username'] ? '@' . esc($r['username']) : '<span class="text-body-secondary">' . esc(lang('App.none')) . '</span>' ?></td>
                        <td class="text-end">
                            <a href="<?= site_url('customers?rep=' . $r['id']) ?>" class="text-decoration-none"><?= (int) $r['customer_count'] ?></a>
                        </td>
                        <td><?= active_badge($r['is_active']) ?></td>
                        <?php if ($canManage) : ?>
                            <td class="text-end text-nowrap">
                                <a href="<?= site_url('sales-reps/' . $r['id'] . '/edit') ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil"></i> <?= lang('App.edit') ?></a>
                            </td>
                        <?php endif ?>
                    </tr>
                <?php endforeach ?>
                <?php if ($reps === []) : ?>
                    <tr><td colspan="7" class="text-center text-body-secondary py-4"><i class="bi bi-person-badge fs-3 d-block mb-1"></i><?= lang('Customers.noRepsYet') ?></td></tr>
                <?php endif ?>
            </tbody>
        </table>
    </div>
</div>

<?= $this->endSection() ?>
