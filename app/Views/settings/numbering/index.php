<?php
/**
 * @var array<string, array{label: string, pattern: string, period: string, next: string}> $rows
 */
$periods = ['monthly' => lang('Settings.periodMonthly'), 'yearly' => lang('Settings.periodYearly'), 'never' => lang('Settings.periodNeverShort')];
?>
<?= $this->extend('layouts/app_layout') ?>

<?= $this->section('title') ?><?= lang('Settings.numbering') ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('components/page_header', [
    'title'      => lang('Settings.numbering'),
    'subtitle'   => lang('Settings.numberingSubtitle'),
    'breadcrumb' => [[lang('Settings.settings'), site_url('settings')], [lang('Settings.numberingCrumb'), '']],
]) ?>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-dark">
                <tr><th><?= lang('Settings.document') ?></th><th><?= lang('Settings.format') ?></th><th><?= lang('Settings.counterRestarts') ?></th><th><?= lang('Settings.nextNumberCol') ?></th><th></th></tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $type => $row) : ?>
                    <tr class="position-relative">
                        <td class="fw-semibold"><?= esc($row['label']) ?></td>
                        <td class="font-monospace small"><?= esc($row['pattern']) ?></td>
                        <td class="small"><?= esc($periods[$row['period']] ?? $row['period']) ?></td>
                        <td class="font-monospace"><?= esc($row['next']) ?></td>
                        <td class="text-end"><a href="<?= site_url('settings/numbering/' . $type) ?>" class="btn btn-outline-primary btn-sm stretched-link"><i class="bi bi-pencil"></i></a></td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>

<?= $this->endSection() ?>
