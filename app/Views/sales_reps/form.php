<?php
/**
 * @var array<string, mixed> $rep   Empty on create.
 * @var array<int, string>   $users Linkable staff accounts.
 * @var bool|null            $inUse
 */
$isEdit = $rep !== [];
$title  = $isEdit ? lang('SalesReps.edit') : lang('SalesReps.add');
$action = $isEdit ? site_url('sales-reps/' . $rep['id']) : site_url('sales-reps');
?>
<?= $this->extend('layouts/app_layout') ?>

<?= $this->section('title') ?><?= esc($title) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('components/page_header', [
    'title'      => $title,
    'breadcrumb' => [[lang('SalesReps.title'), site_url('sales-reps')], [$isEdit ? lang('App.edit') : lang('App.add'), '']],
]) ?>

<div class="card shadow-sm" style="max-width: 760px">
    <div class="card-body">
        <form action="<?= $action ?>" method="post" class="row g-3" novalidate>
            <?= csrf_field() ?>
            <div class="col-12">
                <label for="rep_name" class="form-label"><?= lang('Customers.name') ?> <span class="text-danger">*</span></label>
                <input type="text" class="form-control text-uppercase<?= invalid('rep_name') ?>" id="rep_name" name="rep_name" maxlength="128"
                       required autofocus value="<?= esc(form_value('rep_name', $rep)) ?>">
                <?= field_error('rep_name') ?>
            </div>
            <div class="col-12 col-md-6">
                <label for="rep_phone" class="form-label"><?= lang('Customers.phone') ?></label>
                <input type="tel" class="form-control<?= invalid('rep_phone') ?>" id="rep_phone" name="rep_phone" maxlength="32"
                       value="<?= esc(form_value('rep_phone', $rep)) ?>">
                <?= field_error('rep_phone') ?>
            </div>
            <div class="col-12 col-md-6">
                <label for="rep_email" class="form-label"><?= lang('Customers.email') ?></label>
                <input type="email" class="form-control<?= invalid('rep_email') ?>" id="rep_email" name="rep_email" maxlength="255"
                       value="<?= esc(form_value('rep_email', $rep)) ?>">
                <?= field_error('rep_email') ?>
            </div>
            <div class="col-12">
                <label for="user_id" class="form-label"><?= lang('SalesReps.loginAccount') ?></label>
                <select class="form-select<?= invalid('user_id') ?>" id="user_id" name="user_id">
                    <option value=""><?= lang('SalesReps.noLogin') ?></option>
                    <?php foreach ($users as $id => $label) : ?>
                        <option value="<?= $id ?>"<?= form_value('user_id', $rep) === (string) $id ? ' selected' : '' ?>><?= esc($label) ?></option>
                    <?php endforeach ?>
                </select>
                <?= field_error('user_id') ?>
                <div class="form-text"><?= lang('SalesReps.loginHelp') ?></div>
            </div>
            <div class="col-12">
                <input type="hidden" name="is_active" value="0">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1"<?= form_checked('is_active', $rep, true) ?>>
                    <label class="form-check-label" for="is_active"><?= lang('SalesReps.activeHelp') ?></label>
                </div>
            </div>
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i><?= lang('App.save') ?></button>
                <a href="<?= site_url('sales-reps') ?>" class="btn btn-outline-secondary"><?= lang('App.cancel') ?></a>
            </div>
        </form>
        <?php if ($isEdit && empty($inUse)) : ?>
            <hr>
            <?= view('components/delete_button', [
                'action'  => site_url('sales-reps/' . $rep['id'] . '/delete'),
                'label'   => lang('SalesReps.delete'),
                'icon'    => 'trash',
                'confirm' => lang('SalesReps.deleteConfirm', [up($rep['rep_name'])]),
            ]) ?>
        <?php endif ?>
    </div>
</div>

<?= $this->endSection() ?>
