<?php
/**
 * Picker for the combined order report (OrderBundles::form).
 *
 * @var array<string, mixed>       $proposal Proposal row.
 * @var list<array<string, mixed>> $items    OrderBundle::options() items: key, group, label, type, checked, disabled, icon.
 */
$pid    = (int) $proposal['id'];
$groups = [];

foreach ($items as $item) {
    $groups[$item['group']][] = $item;
}
?>
<?= $this->extend('layouts/app_layout') ?>

<?= $this->section('title') ?><?= esc(lang('Bundle.title', [$proposal['cpl_id']])) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('components/page_header', [
    'title'      => lang('Bundle.title', [$proposal['cpl_id']]),
    'subtitle'   => up($proposal['cpl_proposal_title']),
    'breadcrumb' => [[lang('Proposals.proposals'), site_url('proposals')], [$proposal['cpl_id'], site_url('proposals/' . $pid)], [lang('Bundle.button'), '']],
]) ?>

<form action="<?= site_url("proposals/{$pid}/bundle/pdf") ?>" method="get" target="_blank" hx-boost="false" id="bundle-form">
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span class="small text-body-secondary"><?= lang('Bundle.intro') ?></span>
            <span class="d-flex gap-1">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bundle-select="all"><?= lang('Bundle.selectAll') ?></button>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bundle-select="none"><?= lang('Bundle.selectNone') ?></button>
            </span>
        </div>
        <div class="card-body">
            <?php foreach ($groups as $group => $rows) : ?>
                <div class="small fw-semibold mt-2 mb-1"><?= esc($group) ?></div>
                <?php foreach ($rows as $item) : ?>
                    <?php $id = 'part-' . preg_replace('/[^a-z0-9]+/', '-', $item['key']) ?>
                    <div class="form-check<?= $item['type'] === 'attachment' ? ' ms-4' : '' ?>">
                        <input class="form-check-input" type="checkbox" name="parts[]" value="<?= esc($item['key'], 'attr') ?>" id="<?= esc($id, 'attr') ?>"
                            <?= $item['checked'] ? 'checked' : '' ?> <?= $item['disabled'] ? 'disabled' : '' ?>>
                        <label class="form-check-label small<?= $item['disabled'] ? ' text-body-secondary' : '' ?>" for="<?= esc($id, 'attr') ?>">
                            <i class="bi bi-<?= esc($item['icon'], 'attr') ?> me-1"></i>
                            <?php if ($item['type'] === 'attachment') : ?><span class="text-body-secondary"><?= lang('Bundle.attachment') ?>:</span><?php endif ?>
                            <?= esc($item['label']) ?>
                            <?php if ($item['disabled']) : ?><div class="text-body-secondary"><?= lang('Bundle.notMergeable') ?></div><?php endif ?>
                        </label>
                    </div>
                <?php endforeach ?>
            <?php endforeach ?>
            <p class="small text-body-secondary mt-3 mb-0"><i class="bi bi-info-circle me-1"></i><?= lang('Bundle.cancelledHint') ?></p>
        </div>
        <div class="card-footer bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="form-check m-0">
                <input type="hidden" name="download" value="0">
                <input class="form-check-input" type="checkbox" name="download" value="1" id="bundle-download">
                <label class="form-check-label small" for="bundle-download"><?= lang('Bundle.download') ?></label>
            </div>
            <span class="d-flex gap-2">
                <a href="<?= site_url('proposals/' . $pid) ?>" class="btn btn-outline-secondary"><?= lang('App.back') ?></a>
                <button type="submit" class="btn btn-primary"><i class="bi bi-files me-1"></i><?= lang('Bundle.build') ?></button>
            </span>
        </div>
    </div>
</form>

<?= $this->endSection() ?>

<?= $this->section('pageScripts') ?>
<script>
(() => {
    const form = document.getElementById('bundle-form');
    if (!form) return;
    form.querySelectorAll('[data-bundle-select]').forEach((btn) => btn.addEventListener('click', () => {
        const on = btn.dataset.bundleSelect === 'all';
        form.querySelectorAll('input[name="parts[]"]:not(:disabled)').forEach((box) => { box.checked = on; });
    }));
})();
</script>
<?= $this->endSection() ?>
