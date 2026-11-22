<?php
/**
 * Settings → Backups.
 *
 * @var list<array<string, mixed>> $backups    BackupService::list().
 * @var array<int, string>         $users      user id => name.
 * @var string                     $folder     Backup folder.
 * @var bool                       $canRestore User has backups.restore.
 */
$last    = $backups[0] ?? null;
$lastAge = $last !== null ? (time() - strtotime((string) $last['created_at'])) : null;
$size    = static fn (int $bytes): string => $bytes >= 1048576 ? number_format($bytes / 1048576, 1, ',', '.') . ' MB' : number_format(max(1, $bytes / 1024), 0, ',', '.') . ' KB';
?>
<?= $this->extend('layouts/app_layout') ?>

<?= $this->section('title') ?><?= lang('Backups.backups') ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('components/page_header', [
    'title'      => lang('Backups.backups'),
    'subtitle'   => lang('Backups.subtitle'),
    'breadcrumb' => [[lang('Settings.settings'), site_url('settings')], [lang('Backups.backups'), '']],
]) ?>

<?php if ($last === null) : ?>
    <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-1"></i><?= lang('Backups.noneWarning') ?></div>
<?php elseif ($lastAge > 2 * 86400) : ?>
    <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-1"></i><?= lang('Backups.lastBackupOld') ?></div>
<?php endif ?>

<div class="card shadow-sm mb-3">
    <div class="card-body">
        <form action="<?= site_url('settings/backups') ?>" method="post" class="row g-2 align-items-end">
            <?= csrf_field() ?>
            <div class="col-12 col-md-5 col-lg-4">
                <label for="label" class="form-label"><?= lang('Backups.labelOptional') ?></label>
                <input type="text" class="form-control" id="label" name="label" maxlength="40" placeholder="<?= esc(lang('Backups.labelHint'), 'attr') ?>">
            </div>
            <div class="col-12 col-md-auto">
                <button type="submit" class="btn btn-primary"><i class="bi bi-database-down me-1"></i><?= lang('Backups.createNow') ?></button>
            </div>
            <div class="col-12 col-md text-md-end small text-body-secondary">
                <?php if ($last !== null) : ?><?= esc(lang('Backups.lastBackup', [format_date(substr((string) $last['created_at'], 0, 10)) . ' ' . substr((string) $last['created_at'], 11, 5)])) ?><?php endif ?>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm mb-3">
    <?php if ($backups === []) : ?>
        <div class="card-body text-body-secondary"><?= lang('Backups.none') ?></div>
    <?php else : ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th><?= lang('Backups.date') ?></th>
                        <th><?= lang('Backups.type') ?></th>
                        <th><?= lang('Backups.createdBy') ?></th>
                        <th><?= lang('Backups.contents') ?></th>
                        <th class="text-end"><?= lang('Backups.size') ?></th>
                        <th class="text-end"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($backups as $b) : ?>
                        <tr>
                            <td class="text-nowrap">
                                <?= esc(format_date(substr((string) $b['created_at'], 0, 10))) ?> <?= esc(substr((string) $b['created_at'], 11, 5)) ?>
                                <div class="small text-body-secondary font-monospace"><?= esc($b['file']) ?></div>
                            </td>
                            <td>
                                <?php if (! empty($b['error'])) : ?>
                                    <span class="badge bg-danger-subtle text-danger-emphasis"><?= lang('Backups.invalid') ?></span>
                                <?php else : ?>
                                    <span class="badge <?= $b['trigger'] === 'pre-restore' ? 'bg-warning text-dark' : ($b['trigger'] === 'scheduled' ? 'bg-secondary' : 'bg-primary') ?>"><?= esc(code_label('backupTrigger', (string) $b['trigger'])) ?></span>
                                    <?php if (! empty($b['label']) && $b['label'] !== $b['trigger']) : ?><span class="small ms-1"><?= esc($b['label']) ?></span><?php endif ?>
                                <?php endif ?>
                            </td>
                            <td class="small"><?= esc(up($users[(int) ($b['user_id'] ?? 0)] ?? '-')) ?></td>
                            <td class="small text-nowrap">
                                <?php if (empty($b['error'])) : ?>
                                    <?= esc(lang('Backups.contentsValue', [count($b['tables'] ?? []), number_format((int) ($b['rows'] ?? 0), 0, ',', '.'), (int) ($b['uploads'] ?? 0)])) ?>
                                    <div class="text-body-secondary"><?= lang('Backups.appVersion') ?> <?= esc((string) ($b['app_version'] ?? '')) ?></div>
                                <?php endif ?>
                            </td>
                            <td class="text-end text-nowrap small"><?= esc($size((int) $b['size'])) ?></td>
                            <td class="text-end text-nowrap">
                                <a href="<?= site_url('settings/backups/' . $b['file'] . '/download') ?>" class="btn btn-sm btn-outline-secondary" hx-boost="false" title="<?= esc(lang('Backups.download'), 'attr') ?>"><i class="bi bi-download"></i><span class="d-none d-xl-inline ms-1"><?= lang('Backups.download') ?></span></a>
                                <?php if ($canRestore && $b['restorable']) : ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#restore-modal"
                                            data-restore-file="<?= esc($b['file'], 'attr') ?>" data-restore-date="<?= esc(format_date(substr((string) $b['created_at'], 0, 10)) . ' ' . substr((string) $b['created_at'], 11, 5), 'attr') ?>"
                                            data-restore-url="<?= site_url('settings/backups/' . $b['file'] . '/restore') ?>"><i class="bi bi-arrow-counterclockwise"></i><span class="d-none d-xl-inline ms-1"><?= lang('Backups.restore') ?></span></button>
                                <?php elseif (! $b['restorable']) : ?>
                                    <i class="bi bi-slash-circle text-body-secondary ms-1" title="<?= esc(lang('Backups.notRestorable'), 'attr') ?>"></i>
                                <?php endif ?>
                                <?= view('components/delete_button', [
                                    'action'  => site_url('settings/backups/' . $b['file'] . '/delete'),
                                    'confirm' => lang('Backups.deleteConfirm', [$b['file']]),
                                    'label'   => '',
                                    'icon'    => 'trash',
                                ]) ?>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif ?>
</div>

<div class="small text-body-secondary">
    <div><i class="bi bi-folder me-1"></i><?= esc(lang('Backups.folder', [$folder])) ?></div>
    <div class="mt-1"><i class="bi bi-terminal me-1"></i><?= esc(lang('Backups.cliHelp')) ?></div>
</div>

<?php if ($canRestore) : ?>
    <div class="modal fade" id="restore-modal" tabindex="-1" aria-labelledby="restore-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <!-- full page load: the restore logs everybody out -->
            <form class="modal-content" method="post" action="" hx-boost="false" data-restore-form>
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="restore-modal-title" data-restore-title data-template="<?= esc(lang('Backups.restoreTitle', ['{file}']), 'attr') ?>"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= esc(lang('App.close'), 'attr') ?>"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger small" data-restore-warning data-template="<?= esc(lang('Backups.restoreWarning', ['{date}']), 'attr') ?>"></div>
                    <p class="small"><?= esc(lang('Backups.restoreSteps')) ?></p>
                    <label for="restore-confirm" class="form-label"><?= esc(lang('Backups.restoreType', [\App\Controllers\Settings\Backups::CONFIRM_WORD])) ?></label>
                    <input type="text" class="form-control font-monospace" id="restore-confirm" name="confirm" autocomplete="off" required data-confirm-word="<?= esc(\App\Controllers\Settings\Backups::CONFIRM_WORD, 'attr') ?>">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= lang('App.close') ?></button>
                    <button type="submit" class="btn btn-danger" disabled data-restore-submit><i class="bi bi-arrow-counterclockwise me-1"></i><?= lang('Backups.restore') ?></button>
                </div>
            </form>
        </div>
    </div>
<?php endif ?>

<?= $this->endSection() ?>

<?= $this->section('pageScripts') ?>
<script src="<?= base_url('assets/js/backups.js') ?>"></script>
<?= $this->endSection() ?>
