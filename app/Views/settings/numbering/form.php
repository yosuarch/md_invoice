<?php
/**
 * @var string                                                       $type
 * @var array{label: string, pattern: string, period: string}        $def
 * @var string                                                       $next Next number with the current format.
 */
$pattern = form_value('pattern', $def);
$period  = form_value('period', $def);
$periods = ['monthly' => lang('Settings.periodMonthly'), 'yearly' => lang('Settings.periodYearly'), 'never' => lang('Settings.periodNever')];
?>
<?= $this->extend('layouts/app_layout') ?>

<?= $this->section('title') ?><?= esc(lang('Settings.numberingTitle', [$def['label']])) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('components/page_header', [
    'title'      => $def['label'],
    'subtitle'   => lang('Settings.nextNumber', [$next]),
    'breadcrumb' => [[lang('Settings.settings'), site_url('settings')], [lang('Settings.numberingCrumb'), site_url('settings/numbering')], [$def['label'], '']],
]) ?>

<div class="row g-3">
    <div class="col-12 col-lg-7">
        <div class="card shadow-sm">
            <div class="card-body">
                <form action="<?= site_url('settings/numbering/' . $type) ?>" method="post" class="row g-3" novalidate id="numbering-form"
                      data-preview-url="<?= site_url('settings/numbering/preview') ?>">
                    <?= csrf_field() ?>
                    <div class="col-12">
                        <label for="pattern" class="form-label"><?= lang('Settings.format') ?> <span class="text-danger">*</span></label>
                        <input type="text" class="form-control font-monospace<?= invalid('pattern') ?>" id="pattern" name="pattern" maxlength="64" required
                               value="<?= esc($pattern) ?>" autocomplete="off">
                        <?= field_error('pattern') ?>
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="period" class="form-label"><?= lang('Settings.counterRestarts') ?></label>
                        <select class="form-select" id="period" name="period">
                            <?php foreach ($periods as $key => $label) : ?>
                                <option value="<?= $key ?>"<?= $period === $key ? ' selected' : '' ?>><?= esc($label) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="form-label"><?= lang('Settings.example') ?></div>
                        <div class="form-control-plaintext font-monospace fw-semibold" data-example>-</div>
                    </div>
                    <div class="col-12"><div class="small text-danger" data-problems></div></div>
                    <div class="col-12 d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i><?= lang('App.save') ?></button>
                        <a href="<?= site_url('settings/numbering') ?>" class="btn btn-outline-secondary"><?= lang('App.cancel') ?></a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-5">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold"><?= lang('Settings.tokens') ?></div>
            <div class="card-body small">
                <table class="table table-sm mb-2">
                    <tr><td class="font-monospace">{Y}</td><td><?= lang('Settings.tokY') ?></td></tr>
                    <tr><td class="font-monospace">{y}</td><td><?= lang('Settings.toky') ?></td></tr>
                    <tr><td class="font-monospace">{m}</td><td><?= lang('Settings.tokm') ?></td></tr>
                    <tr><td class="font-monospace">{d}</td><td><?= lang('Settings.tokd') ?></td></tr>
                    <tr><td class="font-monospace">{seq:4}</td><td><?= lang('Settings.tokseq') ?></td></tr>
                </table>
                <div class="text-body-secondary">
                    <?= lang('Settings.examples') ?> <span class="font-monospace">INV/{Y}/{m}/{seq:4}</span> → INV/2026/11/0001,
                    <span class="font-monospace">WS-{d}{m}{Y}-{seq:4}</span> → WS-22112026-0001,
                    <span class="font-monospace">ws{y}{m}{seq:4}</span> → ws26110001.
                    A counter that restarts every month needs year and month in the number, so numbers never repeat.
                </div>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('pageScripts') ?>
<script src="<?= asset_url('assets/js/numbering-form.js') ?>"></script>
<?= $this->endSection() ?>
