<?php
/**
 * "Documents & files" of an order (App\Services\OrderFiles): every printable PDF of the order and all attachments,
 * with an upload form for the documents the user may add files to.
 *
 * @var array{prints: list<array<string, mixed>>, attachments: list<array<string, mixed>>, targets: list<array<string, mixed>>} $files
 * @var string $default  Pre-selected upload target "type:id" (the document of the current page).
 * @var string $returnTo Path to return to after upload/removal (e.g. "proposals/5").
 * @var string|null $bundleUrl Combined report page of a finished order (OrderBundle), or null.
 */

use App\Services\AttachmentService;

$groups = [];

foreach ($files['prints'] as $p) {
    $groups[$p['group']][] = $p;
}

$count    = count($files['prints']) + count($files['attachments']);
$maxBytes = AttachmentService::maxBytes();
?>
<div class="card shadow-sm mb-3" id="files">
    <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
        <span><i class="bi bi-folder2-open me-1"></i><?= lang('Orders.filesTitle') ?></span>
        <span class="d-flex align-items-center gap-2">
            <?php if (! empty($bundleUrl)) : ?>
                <a href="<?= esc($bundleUrl, 'attr') ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-files me-1"></i><?= lang('Bundle.button') ?></a>
            <?php endif ?>
            <span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis"><?= $count ?></span>
        </span>
    </div>
    <div class="row g-0">
        <div class="col-12 col-lg-6 border-end-lg">
            <div class="px-3 pt-3 pb-1 small text-body-secondary text-uppercase fw-semibold"><?= lang('Orders.printable') ?></div>
            <?php foreach ($groups as $group => $items) : ?>
                <div class="px-3 pt-2 small fw-semibold"><?= esc($group) ?></div>
                <ul class="list-unstyled mb-2">
                    <?php foreach ($items as $p) : ?>
                        <li class="d-flex align-items-center justify-content-between gap-2 px-3 py-1 file-row">
                            <a href="<?= esc($p['url'], 'attr') ?>" target="_blank" rel="noopener" class="text-decoration-none small text-truncate<?= $p['muted'] ? ' text-body-secondary' : '' ?>">
                                <i class="bi bi-<?= esc($p['icon'], 'attr') ?> me-2"></i><?= esc($p['label']) ?>
                            </a>
                            <a href="<?= esc(download_url($p['url']), 'attr') ?>" hx-boost="false" class="btn btn-link btn-sm py-0" title="<?= esc(lang('App.download'), 'attr') ?>" aria-label="<?= esc(lang('App.download') . ' ' . $p['label'], 'attr') ?>"><i class="bi bi-download"></i></a>
                        </li>
                    <?php endforeach ?>
                </ul>
            <?php endforeach ?>
        </div>
        <div class="col-12 col-lg-6 border-top border-lg-top-0">
            <div class="px-3 pt-3 pb-1 small text-body-secondary text-uppercase fw-semibold"><?= lang('Orders.attachments') ?></div>
            <?php if ($files['attachments'] === []) : ?>
                <p class="px-3 small text-body-secondary mb-2"><?= lang('Orders.noFiles') ?></p>
            <?php endif ?>
            <ul class="list-unstyled mb-2">
                <?php foreach ($files['attachments'] as $a) : ?>
                    <li class="d-flex align-items-start justify-content-between gap-2 px-3 py-1 file-row">
                        <div class="small text-truncate">
                            <a href="<?= site_url('attachments/' . (int) $a['id']) ?>" target="_blank" rel="noopener" class="text-decoration-none">
                                <i class="bi bi-<?= esc(AttachmentService::icon($a['file_name']), 'attr') ?> me-2"></i><?= esc($a['file_name']) ?>
                            </a>
                            <div class="text-body-secondary ms-4">
                                <?= esc($a['entity_label']) ?> · <?= esc($a['uploader_name'] ?: ($a['uploader_username'] ?? '-')) ?> · <?= esc(format_date($a['created_at'], true)) ?>
                            </div>
                        </div>
                        <div class="d-flex text-nowrap">
                            <a href="<?= site_url('attachments/' . (int) $a['id']) ?>?download=1" hx-boost="false" class="btn btn-link btn-sm py-0" title="<?= esc(lang('App.download'), 'attr') ?>" aria-label="<?= esc(lang('App.download') . ' ' . $a['file_name'], 'attr') ?>"><i class="bi bi-download"></i></a>
                            <?php if ($a['can_delete']) : ?>
                                <form action="<?= site_url('attachments/' . (int) $a['id'] . '/delete') ?>" method="post" class="m-0" data-confirm="<?= esc(lang('Orders.removeFileConfirm', [$a['file_name']]), 'attr') ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="return_to" value="<?= esc($returnTo, 'attr') ?>">
                                    <button type="submit" class="btn btn-link btn-sm py-0 text-danger" title="<?= esc(lang('App.remove'), 'attr') ?>" aria-label="<?= esc(lang('App.remove') . ' ' . $a['file_name'], 'attr') ?>"><i class="bi bi-trash"></i></button>
                                </form>
                            <?php endif ?>
                        </div>
                    </li>
                <?php endforeach ?>
            </ul>
            <?php if ($files['targets'] !== []) : ?>
                <form action="<?= site_url('attachments') ?>" method="post" enctype="multipart/form-data" class="px-3 pb-3 pt-2 border-top" data-max-bytes="<?= $maxBytes ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="return_to" value="<?= esc($returnTo, 'attr') ?>">
                    <div class="row g-2 align-items-end">
                        <div class="col-12 col-sm-5">
                            <label for="attach-entity" class="form-label small mb-1"><?= lang('Orders.belongsTo') ?></label>
                            <select class="form-select form-select-sm" id="attach-entity" name="entity" required>
                                <?php foreach ($files['targets'] as $t) : ?>
                                    <?php $key = $t['type'] . ':' . $t['id'] ?>
                                    <option value="<?= esc($key, 'attr') ?>"<?= $key === $default ? ' selected' : '' ?>><?= esc($t['label']) ?></option>
                                <?php endforeach ?>
                            </select>
                        </div>
                        <div class="col-12 col-sm-7">
                            <label for="attach-file" class="form-label small mb-1"><?= lang('Orders.file') ?> <span class="text-body-secondary">(<?= lang('Orders.maxSize', [number_format($maxBytes / 1048576, 1)]) ?>)</span></label>
                            <div class="input-group input-group-sm">
                                <input type="file" class="form-control" id="attach-file" name="file" required accept="<?= esc(AttachmentService::acceptList(), 'attr') ?>">
                                <button type="submit" class="btn btn-primary"><i class="bi bi-upload me-1"></i><?= lang('Orders.upload') ?></button>
                            </div>
                            <div class="invalid-feedback d-block small" data-size-error hidden></div>
                        </div>
                    </div>
                </form>
            <?php endif ?>
        </div>
    </div>
</div>
