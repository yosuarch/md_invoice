<?php
/**
 * @var list<\CodeIgniter\Shield\Entities\User> $users
 * @var array<string, string>                   $groups
 */
$me = (int) auth()->id();
?>
<?= $this->extend('layouts/app_layout') ?>

<?= $this->section('title') ?><?= lang('Users.users') ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('components/page_header', [
    'title'    => lang('Users.users'),
    'subtitle' => lang('Users.subtitle'),
    'actions'  => '<a href="' . site_url('users/new') . '" class="btn btn-primary"><i class="bi bi-person-plus me-1"></i>' . esc(lang('Users.addUser')) . '</a>',
]) ?>

<div class="card shadow-sm" id="list-results">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 table-stack">
            <thead class="table-dark">
                <tr>
                    <th><?= lang('Users.user') ?></th>
                    <th><?= lang('Users.email') ?></th>
                    <th><?= lang('Users.roles') ?></th>
                    <th><?= lang('App.status') ?></th>
                    <th><?= lang('Users.lastActive') ?></th>
                    <th class="text-end"><?= lang('App.actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u) : ?>
                    <?php $userGroups = $u->getGroups() ?? [] ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?= esc($u->full_name ? up($u->full_name) : $u->username) ?><?= (int) $u->id === $me ? ' <span class="badge bg-info-subtle text-info-emphasis">' . esc(lang('Users.you')) . '</span>' : '' ?></div>
                            <div class="small text-body-secondary">@<?= esc($u->username) ?></div>
                        </td>
                        <td><?= esc($u->email) ?></td>
                        <td>
                            <?php foreach ($userGroups as $g) : ?>
                                <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle"><?= esc($groups[$g] ?? $g) ?></span>
                            <?php endforeach ?>
                            <?php if ($userGroups === []) : ?><span class="badge bg-warning-subtle text-warning-emphasis"><?= lang('Users.noRole') ?></span><?php endif ?>
                        </td>
                        <td><?= active_badge(! $u->isBanned()) ?></td>
                        <td class="small"><?= $u->last_active ? esc(format_date((string) $u->last_active, true)) : '<span class="text-body-secondary">' . esc(lang('Users.never')) . '</span>' ?></td>
                        <td class="text-end text-nowrap">
                            <a href="<?= site_url('users/' . $u->id . '/edit') ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil"></i> <?= lang('App.edit') ?></a>
                            <?php if ((int) $u->id !== $me) : ?>
                                <?php if ($u->isBanned()) : ?>
                                    <?= view('components/delete_button', [
                                        'action'  => site_url('users/' . $u->id . '/activate'),
                                        'label'   => lang('Users.activate'),
                                        'icon'    => 'person-check',
                                        'class'   => 'btn btn-outline-success btn-sm',
                                        'confirm' => lang('Users.reactivateConfirm', [$u->username]),
                                    ]) ?>
                                <?php else : ?>
                                    <?= view('components/delete_button', [
                                        'action'  => site_url('users/' . $u->id . '/deactivate'),
                                        'label'   => lang('Users.deactivate'),
                                        'icon'    => 'person-slash',
                                        'confirm' => lang('Users.deactivateConfirm', [$u->username]),
                                    ]) ?>
                                <?php endif ?>
                            <?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>

<?= $this->endSection() ?>
