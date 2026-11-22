<?php
/**
 * Agreement (Surat Perintah Kerja) form for an approved proposal.
 *
 * @var array<string, mixed> $proposal  Proposal with details.
 * @var array<string, mixed> $agreement Stored agreement or defaults.
 * @var bool                 $isNew
 */
$pid   = (int) $proposal['id'];
$title = $isNew ? lang('Agreements.newSpk') : lang('Agreements.spkNo', [$agreement['agreement_number']]);
?>
<?= $this->extend('layouts/app_layout') ?>

<?= $this->section('title') ?><?= esc($title) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('components/page_header', [
    'title'      => $title,
    'subtitle'   => $proposal['cpl_id'] . ' · ' . up($proposal['customer_name']) . ' · ' . format_idr($proposal['grand_total']),
    'breadcrumb' => [[lang('Proposals.proposals'), site_url('proposals')], [$proposal['cpl_id'], site_url("proposals/{$pid}")], [lang('Agreements.spk'), '']],
]) ?>

<div class="alert alert-info small"><i class="bi bi-info-circle me-1"></i>
    <?= lang('Agreements.info') ?>
    <?= $isNew ? lang('Agreements.numberOnSave') : '' ?>
</div>

<form action="<?= site_url("proposals/{$pid}/agreement") ?>" method="post" novalidate>
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-12 col-xl-6">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold"><?= lang('Agreements.dates') ?></div>
                <div class="card-body row g-3">
                    <div class="col-12 col-md-6">
                        <label for="agreement_date" class="form-label"><?= lang('Agreements.agreementDate') ?> <span class="text-danger">*</span></label>
                        <input type="date" class="form-control<?= invalid('agreement_date') ?>" id="agreement_date" name="agreement_date" required value="<?= esc(form_value('agreement_date', $agreement)) ?>">
                        <?= field_error('agreement_date') ?>
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="completion_date" class="form-label"><?= lang('Agreements.completion') ?></label>
                        <input type="date" class="form-control<?= invalid('completion_date') ?>" id="completion_date" name="completion_date" value="<?= esc(form_value('completion_date', $agreement)) ?>">
                        <?= field_error('completion_date') ?>
                        <div class="form-text"><?= lang('Agreements.completionHelp') ?></div>
                    </div>
                </div>
            </div>
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold"><?= lang('Agreements.customerParty') ?></div>
                <div class="card-body row g-3">
                    <div class="col-12">
                        <label for="client_signatory_name" class="form-label"><?= lang('Agreements.name') ?> <span class="text-danger">*</span></label>
                        <input type="text" class="form-control text-uppercase<?= invalid('client_signatory_name') ?>" id="client_signatory_name" name="client_signatory_name" maxlength="128" required
                               value="<?= esc(form_value('client_signatory_name', $agreement)) ?>" placeholder="<?= esc(lang('Agreements.nameHint'), 'attr') ?>">
                        <?= field_error('client_signatory_name') ?>
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="client_signatory_position" class="form-label"><?= lang('Agreements.position') ?></label>
                        <input type="text" class="form-control text-uppercase<?= invalid('client_signatory_position') ?>" id="client_signatory_position" name="client_signatory_position" maxlength="64"
                               value="<?= esc(form_value('client_signatory_position', $agreement)) ?>" placeholder="<?= esc(lang('Agreements.positionHint'), 'attr') ?>">
                        <?= field_error('client_signatory_position') ?>
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="client_signatory_nip" class="form-label">NIP</label>
                        <input type="text" class="form-control<?= invalid('client_signatory_nip') ?>" id="client_signatory_nip" name="client_signatory_nip" maxlength="32"
                               value="<?= esc(form_value('client_signatory_nip', $agreement)) ?>">
                        <?= field_error('client_signatory_nip') ?>
                    </div>
                </div>
            </div>
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold"><?= lang('Agreements.storeParty') ?></div>
                <div class="card-body row g-3">
                    <div class="col-12 col-md-7">
                        <label for="store_signatory_name" class="form-label"><?= lang('Agreements.name') ?> <span class="text-danger">*</span></label>
                        <input type="text" class="form-control text-uppercase<?= invalid('store_signatory_name') ?>" id="store_signatory_name" name="store_signatory_name" maxlength="128" required
                               value="<?= esc(form_value('store_signatory_name', $agreement)) ?>">
                        <?= field_error('store_signatory_name') ?>
                    </div>
                    <div class="col-12 col-md-5">
                        <label for="store_signatory_position" class="form-label"><?= lang('Agreements.position') ?></label>
                        <input type="text" class="form-control text-uppercase<?= invalid('store_signatory_position') ?>" id="store_signatory_position" name="store_signatory_position" maxlength="64"
                               value="<?= esc(form_value('store_signatory_position', $agreement)) ?>">
                        <?= field_error('store_signatory_position') ?>
                    </div>
                    <div class="col-12 form-text mt-1"><?= lang('Agreements.defaultsInfo') ?></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-6">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold"><?= lang('Agreements.clauses') ?></div>
                <div class="card-body">
                    <textarea class="form-control<?= invalid('terms') ?>" id="terms" name="terms" rows="14" maxlength="10000" aria-label="<?= esc(lang('Agreements.clauses'), 'attr') ?>"><?= esc(form_value('terms', $agreement)) ?></textarea>
                    <?= field_error('terms') ?>
                    <div class="form-text"><?= lang('Agreements.clausesHelp') ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="d-flex gap-2 mb-4">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i><?= $isNew ? lang('Agreements.create') : lang('Agreements.save') ?></button>
        <a href="<?= site_url("proposals/{$pid}") ?>" class="btn btn-outline-secondary"><?= lang('App.cancel') ?></a>
    </div>
</form>

<?= $this->endSection() ?>
