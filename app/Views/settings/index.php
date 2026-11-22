<?= $this->extend('layouts/app_layout') ?>

<?= $this->section('title') ?><?= lang('Settings.settings') ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('components/page_header', ['title' => lang('Settings.settings'), 'subtitle' => lang('Settings.subtitle')]) ?>

<div class="row g-3 mb-4">
    <div class="col-12 col-md-6">
        <a href="<?= site_url('settings/company') ?>" class="card shadow-sm h-100 text-decoration-none settings-card">
            <div class="card-body d-flex gap-3 align-items-start">
                <span class="stat-icon bg-primary-subtle text-primary"><i class="bi bi-building-gear"></i></span>
                <div>
                    <div class="fw-semibold text-body"><?= lang('Settings.company') ?></div>
                    <div class="small text-body-secondary"><?= lang('Settings.companyText') ?></div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-12 col-md-6">
        <a href="<?= site_url('settings/bank-accounts') ?>" class="card shadow-sm h-100 text-decoration-none settings-card">
            <div class="card-body d-flex gap-3 align-items-start">
                <span class="stat-icon bg-primary-subtle text-primary"><i class="bi bi-bank"></i></span>
                <div>
                    <div class="fw-semibold text-body"><?= lang('Settings.banks') ?></div>
                    <div class="small text-body-secondary"><?= lang('Settings.banksText') ?></div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-12 col-md-6">
        <a href="<?= site_url('settings/numbering') ?>" class="card shadow-sm h-100 text-decoration-none settings-card">
            <div class="card-body d-flex gap-3 align-items-start">
                <span class="stat-icon bg-primary-subtle text-primary"><i class="bi bi-123"></i></span>
                <div>
                    <div class="fw-semibold text-body"><?= lang('Settings.numbering') ?></div>
                    <div class="small text-body-secondary"><?= lang('Settings.numberingText') ?></div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-12 col-md-6">
        <a href="<?= site_url('settings/calculation') ?>" class="card shadow-sm h-100 text-decoration-none settings-card">
            <div class="card-body d-flex gap-3 align-items-start">
                <span class="stat-icon bg-primary-subtle text-primary"><i class="bi bi-calculator"></i></span>
                <div>
                    <div class="fw-semibold text-body"><?= lang('Settings.calculation') ?></div>
                    <div class="small text-body-secondary"><?= lang('Settings.calculationText') ?></div>
                </div>
            </div>
        </a>
    </div>
    <?php if (user_can('backups.manage')) : ?>
    <div class="col-12 col-md-6">
        <a href="<?= site_url('settings/backups') ?>" class="card shadow-sm h-100 text-decoration-none settings-card">
            <div class="card-body d-flex gap-3 align-items-start">
                <span class="stat-icon bg-primary-subtle text-primary"><i class="bi bi-database-down"></i></span>
                <div>
                    <div class="fw-semibold text-body"><?= lang('Backups.backups') ?></div>
                    <div class="small text-body-secondary"><?= lang('Backups.backupsText') ?></div>
                </div>
            </div>
        </a>
    </div>
    <?php endif ?>
</div>

<h2 class="h6 text-uppercase text-body-secondary mb-2"><?= lang('Settings.lookups') ?></h2>
<div class="row g-3">
    <?php foreach ($lookups as $slug => $def) : ?>
        <div class="col-12 col-md-6 col-xl-4">
            <a href="<?= site_url('settings/lookups/' . $slug) ?>" class="card shadow-sm h-100 text-decoration-none settings-card">
                <div class="card-body d-flex gap-3 align-items-start">
                    <span class="stat-icon bg-info-subtle text-info-emphasis"><i class="bi bi-<?= esc($def['icon'], 'attr') ?>"></i></span>
                    <div>
                        <div class="fw-semibold text-body">
                            <?= esc($def['title']) ?>
                            <?php if (! empty($def['readonly'])) : ?><span class="badge bg-secondary-subtle text-secondary-emphasis ms-1"><?= lang('Settings.system') ?></span><?php endif ?>
                        </div>
                        <div class="small text-body-secondary"><?= esc($def['description']) ?></div>
                    </div>
                </div>
            </a>
        </div>
    <?php endforeach ?>
</div>

<?= $this->endSection() ?>
