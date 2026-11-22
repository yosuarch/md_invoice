<?php
/**
 * @var list<array<string, mixed>> $entries
 * @var \CodeIgniter\Pager\Pager   $pager
 * @var array<string, string>      $filters
 * @var list<string>               $tables
 * @var array<int, string>         $users
 */
$actionClass = ['CREATE' => 'bg-success', 'UPDATE' => 'bg-info text-dark', 'DELETE' => 'bg-danger'];
$hasFilter   = array_filter($filters) !== [];

/**
 * Render a JSON value list as "key: value" lines.
 */
$renderValues = static function (?string $json): string {
    if ($json === null) {
        return '<span class="text-body-secondary">-</span>';
    }

    $values = json_decode($json, true);

    if (! is_array($values) || $values === []) {
        return '<span class="text-body-secondary">-</span>';
    }

    $html = '<dl class="mb-0 audit-values">';

    foreach ($values as $key => $value) {
        $display = is_scalar($value) || $value === null ? (string) ($value ?? 'NULL') : json_encode($value);
        $html .= '<dt>' . esc((string) $key) . '</dt><dd>' . esc($display) . '</dd>';
    }

    return $html . '</dl>';
};
?>
<?= $this->extend('layouts/app_layout') ?>

<?= $this->section('title') ?><?= lang('Users.auditLog') ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('components/page_header', [
    'title'    => lang('Users.auditLog'),
    'subtitle' => lang('Users.auditSubtitle'),
]) ?>

<form method="get" class="card shadow-sm mb-3" data-live-filter>
    <div class="card-body row g-2 align-items-end">
        <div class="col-6 col-md-3">
            <label for="table" class="form-label small mb-1"><?= lang('Users.table') ?></label>
            <select class="form-select" id="table" name="table">
                <option value=""><?= lang('App.all') ?></option>
                <?php foreach ($tables as $t) : ?>
                    <option value="<?= esc($t, 'attr') ?>"<?= $filters['table'] === $t ? ' selected' : '' ?>><?= esc($t) ?></option>
                <?php endforeach ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label for="action" class="form-label small mb-1"><?= lang('Users.action') ?></label>
            <select class="form-select" id="action" name="action">
                <option value=""><?= lang('App.all') ?></option>
                <?php foreach (array_keys($actionClass) as $a) : ?>
                    <option value="<?= $a ?>"<?= $filters['action'] === $a ? ' selected' : '' ?>><?= $a ?></option>
                <?php endforeach ?>
            </select>
        </div>
        <div class="col-6 col-md-3">
            <label for="user_id" class="form-label small mb-1"><?= lang('Users.user') ?></label>
            <select class="form-select" id="user_id" name="user_id">
                <option value=""><?= lang('App.all') ?></option>
                <?php foreach ($users as $id => $name) : ?>
                    <option value="<?= $id ?>"<?= $filters['user_id'] === (string) $id ? ' selected' : '' ?>><?= esc($name) ?></option>
                <?php endforeach ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label for="record_id" class="form-label small mb-1"><?= lang('Users.recordId') ?></label>
            <input type="number" class="form-control" id="record_id" name="record_id" min="1" value="<?= esc($filters['record_id']) ?>">
        </div>
        <div class="col-12 col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-outline-primary flex-grow-1"><i class="bi bi-funnel me-1"></i><?= lang('App.filter') ?></button>
            <?php if ($hasFilter) : ?>
                <a href="<?= site_url('audit-log') ?>" class="btn btn-outline-secondary" title="<?= esc(lang('App.clearFilters'), 'attr') ?>"><i class="bi bi-x-lg"></i></a>
            <?php endif ?>
        </div>
    </div>
</form>

<div class="card shadow-sm" id="list-results">
    <div class="table-responsive">
        <table class="table align-middle mb-0 small">
            <thead class="table-dark">
                <tr>
                    <th><?= lang('Users.when') ?></th>
                    <th><?= lang('Users.user') ?></th>
                    <th><?= lang('Users.action') ?></th>
                    <th><?= lang('Users.record') ?></th>
                    <th><?= lang('Users.before') ?></th>
                    <th><?= lang('Users.after') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($entries as $e) : ?>
                    <tr class="align-top">
                        <td class="text-nowrap"><?= esc(format_date($e['created_at'], true)) ?><div class="text-body-secondary"><?= esc($e['ip_address'] ?? '') ?></div></td>
                        <td><?= esc($e['username'] ?? lang('Users.system')) ?></td>
                        <td><span class="badge <?= $actionClass[$e['action']] ?? 'bg-secondary' ?>"><?= esc($e['action']) ?></span></td>
                        <td class="text-nowrap"><span class="font-monospace"><?= esc($e['table_name']) ?></span> #<?= esc($e['record_id']) ?></td>
                        <td><?= $renderValues($e['old_values']) ?></td>
                        <td><?= $renderValues($e['new_values']) ?></td>
                    </tr>
                <?php endforeach ?>
                <?php if ($entries === []) : ?>
                    <tr><td colspan="6" class="text-center text-body-secondary py-4"><i class="bi bi-journal-text fs-3 d-block mb-1"></i><?= $hasFilter ? lang('Users.noChangesFilter') : lang('Users.noChanges') ?></td></tr>
                <?php endif ?>
            </tbody>
        </table>
    </div>
    <?php if ($pager->getPageCount() > 1) : ?>
        <div class="card-footer bg-white d-flex justify-content-between align-items-center">
            <span class="small text-body-secondary"><?= lang('Users.entries', [$pager->getTotal()]) ?></span>
            <?= $pager->links('default', 'bootstrap') ?>
        </div>
    <?php endif ?>
</div>

<?= $this->endSection() ?>
