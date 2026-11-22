<?php
/** @var array<string, mixed> $account Empty on create. */
$isEdit = $account !== [];
$title  = $isEdit ? lang('Settings.editBank') : lang('Settings.addBank');
$action = $isEdit ? site_url('settings/bank-accounts/' . $account['id']) : site_url('settings/bank-accounts');
?>
<?= $this->extend('layouts/app_layout') ?>

<?= $this->section('title') ?><?= esc($title) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('components/page_header', [
    'title'      => $title,
    'breadcrumb' => [[lang('Settings.settings'), site_url('settings')], [lang('Settings.banks'), site_url('settings/bank-accounts')], [$title, '']],
]) ?>

<div class="card shadow-sm" style="max-width: 720px">
    <div class="card-body">
        <form action="<?= $action ?>" method="post" class="row g-3" novalidate>
            <?= csrf_field() ?>
            <div class="col-12 col-md-6">
                <label for="bank_name" class="form-label"><?= lang('Settings.bankName') ?> <span class="text-danger">*</span></label>
                <input type="text" class="form-control text-uppercase<?= invalid('bank_name') ?>" id="bank_name" name="bank_name" maxlength="64"
                       placeholder="<?= esc(lang('Settings.bankNameHint'), 'attr') ?>" required value="<?= esc(form_value('bank_name', $account)) ?>">
                <?= field_error('bank_name') ?>
            </div>
            <div class="col-12 col-md-6">
                <label for="bank_account_number" class="form-label"><?= lang('Settings.accountNumber') ?> <span class="text-danger">*</span></label>
                <input type="text" class="form-control font-monospace<?= invalid('bank_account_number') ?>" id="bank_account_number"
                       name="bank_account_number" maxlength="32" inputmode="numeric" required
                       value="<?= esc(form_value('bank_account_number', $account)) ?>">
                <?= field_error('bank_account_number') ?>
            </div>
            <div class="col-12">
                <label for="bank_account_name" class="form-label"><?= lang('Settings.accountHolder') ?> <span class="text-danger">*</span></label>
                <input type="text" class="form-control text-uppercase<?= invalid('bank_account_name') ?>" id="bank_account_name" name="bank_account_name"
                       maxlength="128" required value="<?= esc(form_value('bank_account_name', $account)) ?>">
                <?= field_error('bank_account_name') ?>
            </div>
            <div class="col-12">
                <input type="hidden" name="is_active" value="0">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1"<?= form_checked('is_active', $account, true) ?>>
                    <label class="form-check-label" for="is_active"><?= lang('Settings.bankActive') ?></label>
                </div>
            </div>
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i><?= lang('App.save') ?></button>
                <a href="<?= site_url('settings/bank-accounts') ?>" class="btn btn-outline-secondary"><?= lang('App.cancel') ?></a>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>
