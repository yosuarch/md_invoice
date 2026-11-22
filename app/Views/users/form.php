<?php
/**
 * @var \CodeIgniter\Shield\Entities\User|null $user   Null on create.
 * @var array<string, string>                  $groups key => title
 */
$isEdit  = $user !== null;
$title   = $isEdit ? lang('Users.editUser') : lang('Users.addUser');
$action  = $isEdit ? site_url('users/' . $user->id) : site_url('users');
$record  = $isEdit ? ['username' => $user->username, 'full_name' => $user->full_name, 'email' => $user->email] : [];
$current = old('groups') ?? ($isEdit ? ($user->getGroups() ?? []) : []);
$descriptions = [];

foreach (config('AuthGroups')->groups as $gKey => $g) {
    $descriptions[$gKey] = code_label('groupDesc', $gKey, $g['description']);
}
?>
<?= $this->extend('layouts/app_layout') ?>

<?= $this->section('title') ?><?= esc($title) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('components/page_header', [
    'title'      => $title,
    'breadcrumb' => [[lang('Users.users'), site_url('users')], [$title, '']],
]) ?>

<div class="card shadow-sm" style="max-width: 820px">
    <div class="card-body">
        <form action="<?= $action ?>" method="post" class="row g-3" novalidate autocomplete="off">
            <?= csrf_field() ?>
            <div class="col-12 col-md-6">
                <label for="full_name" class="form-label"><?= lang('Users.fullName') ?> <span class="text-danger">*</span></label>
                <input type="text" class="form-control text-uppercase<?= invalid('full_name') ?>" id="full_name" name="full_name" maxlength="128"
                       required value="<?= esc(form_value('full_name', $record)) ?>">
                <?= field_error('full_name') ?>
            </div>
            <div class="col-12 col-md-6">
                <label for="username" class="form-label"><?= lang('Users.username') ?> <span class="text-danger">*</span></label>
                <input type="text" class="form-control<?= invalid('username') ?>" id="username" name="username" maxlength="30"
                       required value="<?= esc(form_value('username', $record)) ?>">
                <?= field_error('username') ?>
                <div class="form-text"><?= lang('Users.usernameHelp') ?></div>
            </div>
            <div class="col-12">
                <label for="email" class="form-label"><?= lang('Users.emailLogin') ?> <span class="text-danger">*</span></label>
                <input type="email" class="form-control<?= invalid('email') ?>" id="email" name="email" maxlength="254"
                       required value="<?= esc(form_value('email', $record)) ?>">
                <?= field_error('email') ?>
            </div>
            <div class="col-12 col-md-6">
                <label for="password" class="form-label"><?= lang('Users.password') ?><?= $isEdit ? '' : ' <span class="text-danger">*</span>' ?></label>
                <input type="password" class="form-control<?= invalid('password') ?>" id="password" name="password" autocomplete="new-password">
                <?= field_error('password') ?>
                <div class="form-text"><?= $isEdit ? lang('Users.passwordKeep') : lang('Users.passwordRules') ?></div>
            </div>
            <div class="col-12 col-md-6">
                <label for="password_confirm" class="form-label"><?= lang('Users.confirmPassword') ?></label>
                <input type="password" class="form-control<?= invalid('password_confirm') ?>" id="password_confirm" name="password_confirm" autocomplete="new-password">
                <?= field_error('password_confirm') ?>
            </div>
            <div class="col-12">
                <div class="form-label"><?= lang('Users.roles') ?> <span class="text-danger">*</span></div>
                <div class="row g-2">
                    <?php foreach ($groups as $key => $label) : ?>
                        <div class="col-12 col-md-4">
                            <label class="border rounded p-2 d-flex gap-2 h-100<?= isset(form_errors()['groups']) ? ' border-danger' : '' ?>">
                                <input class="form-check-input mt-1" type="checkbox" name="groups[]" value="<?= esc($key, 'attr') ?>"
                                    <?= in_array($key, (array) $current, true) ? 'checked' : '' ?>>
                                <span>
                                    <span class="fw-semibold d-block"><?= esc($label) ?></span>
                                    <span class="small text-body-secondary"><?= esc($descriptions[$key] ?? '') ?></span>
                                </span>
                            </label>
                        </div>
                    <?php endforeach ?>
                </div>
                <?php if (isset(form_errors()['groups'])) : ?>
                    <div class="text-danger small mt-1"><?= esc(form_errors()['groups']) ?></div>
                <?php endif ?>
            </div>
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i><?= lang('App.save') ?></button>
                <a href="<?= site_url('users') ?>" class="btn btn-outline-secondary"><?= lang('App.cancel') ?></a>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>
