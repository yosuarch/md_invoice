<?php
/**
 * Activities & follow-ups across the schools the user may see.
 *
 * @var list<array<string, mixed>> $activities
 * @var \CodeIgniter\Pager\Pager   $pager
 * @var array<string, string>      $filters view, who, type, q
 * @var array<int, string>         $users
 */

use App\Controllers\Activities;
use App\Models\ActivityModel;

$returnTo = 'activities?' . http_build_query(array_filter($filters));
?>
<?= $this->extend('layouts/app_layout') ?>

<?= $this->section('title') ?><?= lang('Activities.activities') ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('components/page_header', [
    'title'    => lang('Activities.activities'),
    'subtitle' => lang('Activities.listSubtitle'),
]) ?>

<form method="get" class="card shadow-sm mb-3" data-live-filter>
    <div class="card-body row g-2 align-items-end">
        <div class="col-12 col-lg-4">
            <label for="q" class="form-label small mb-1"><?= lang('App.search') ?></label>
            <input type="search" class="form-control" id="q" name="q" value="<?= esc($filters['q']) ?>">
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <label for="view" class="form-label small mb-1"><?= lang('Activities.show') ?></label>
            <select class="form-select" id="view" name="view">
                <?php foreach (Activities::VIEWS as $key => $label) : ?>
                    <option value="<?= $key ?>"<?= $filters['view'] === $key ? ' selected' : '' ?>><?= esc(lang($label)) ?></option>
                <?php endforeach ?>
            </select>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <label for="who" class="form-label small mb-1"><?= lang('Activities.who') ?></label>
            <select class="form-select" id="who" name="who">
                <option value="mine"<?= $filters['who'] === 'mine' ? ' selected' : '' ?>><?= lang('Activities.mine') ?></option>
                <option value="all"<?= $filters['who'] === 'all' ? ' selected' : '' ?>><?= lang('Activities.everyone') ?></option>
            </select>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <label for="type" class="form-label small mb-1"><?= lang('Activities.type') ?></label>
            <select class="form-select" id="type" name="type">
                <option value=""><?= lang('App.all') ?></option>
                <?php foreach (ActivityModel::TYPES as $t) : ?>
                    <option value="<?= $t ?>"<?= $filters['type'] === $t ? ' selected' : '' ?>><?= esc(lang('Activities.type_' . $t)) ?></option>
                <?php endforeach ?>
            </select>
        </div>
        <div class="col-6 col-md-3 col-lg-2 d-flex gap-2">
            <button type="submit" class="btn btn-outline-primary flex-grow-1"><i class="bi bi-search me-1"></i><?= lang('App.filter') ?></button>
        </div>
    </div>
</form>

<div class="card shadow-sm" id="list-results">
    <div class="list-group list-group-flush">
        <?php foreach ($activities as $a) : ?>
            <?= view('components/activity_item', ['a' => $a, 'showSchool' => true, 'returnTo' => $returnTo, 'users' => $users], ['saveData' => false]) ?>
        <?php endforeach ?>
        <?php if ($activities === []) : ?>
            <div class="list-group-item text-center text-body-secondary py-4">
                <i class="bi bi-calendar-check fs-3 d-block mb-1"></i><?= lang('Activities.noneFilter') ?>
            </div>
        <?php endif ?>
    </div>
    <?php if ($pager->getPageCount() > 1) : ?>
        <div class="card-footer bg-white d-flex justify-content-between align-items-center">
            <span class="small text-body-secondary"><?= lang('Activities.count', [$pager->getTotal()]) ?></span>
            <?= $pager->links('default', 'bootstrap') ?>
        </div>
    <?php endif ?>
</div>

<?= $this->endSection() ?>
