<?= $this->extend('layouts/app_layout') ?>

<?= $this->section('title') ?><?= lang('Settings.company') ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('components/page_header', [
    'title'      => lang('Settings.company'),
    'subtitle'   => lang('Settings.companySubtitle'),
    'breadcrumb' => [[lang('Settings.settings'), site_url('settings')], [lang('Settings.company'), '']],
]) ?>

<form action="<?= site_url('settings/company') ?>" method="post" enctype="multipart/form-data" novalidate>
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-12 col-xl-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white fw-semibold"><?= lang('Settings.details') ?></div>
                <div class="card-body row g-3">
                    <div class="col-12">
                        <label for="company_name" class="form-label"><?= lang('Settings.companyName') ?> <span class="text-danger">*</span></label>
                        <input type="text" class="form-control<?= invalid('company_name') ?>" id="company_name" name="company_name"
                               maxlength="150" required value="<?= esc(form_value('company_name', $profile)) ?>">
                        <?= field_error('company_name') ?>
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="tax_id" class="form-label">NPWP</label>
                        <input type="text" class="form-control<?= invalid('tax_id') ?>" id="tax_id" name="tax_id" maxlength="32"
                               placeholder="00.000.000.0-000.000" value="<?= esc(form_value('tax_id', $profile)) ?>">
                        <?= field_error('tax_id') ?>
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="phone" class="form-label"><?= lang('Settings.phone') ?></label>
                        <input type="tel" class="form-control<?= invalid('phone') ?>" id="phone" name="phone" maxlength="32"
                               value="<?= esc(form_value('phone', $profile)) ?>">
                        <?= field_error('phone') ?>
                    </div>
                    <div class="col-12">
                        <label for="email" class="form-label"><?= lang('Settings.email') ?></label>
                        <input type="email" class="form-control<?= invalid('email') ?>" id="email" name="email" maxlength="255"
                               value="<?= esc(form_value('email', $profile)) ?>">
                        <?= field_error('email') ?>
                    </div>
                    <div class="col-12">
                        <label for="address" class="form-label"><?= lang('Settings.address') ?></label>
                        <textarea class="form-control<?= invalid('address') ?>" id="address" name="address" rows="3"><?= esc(form_value('address', $profile)) ?></textarea>
                        <?= field_error('address') ?>
                    </div>
                    <div class="col-12">
                        <label for="default_bank_account_id" class="form-label"><?= lang('Settings.defaultBank') ?></label>
                        <select class="form-select<?= invalid('default_bank_account_id') ?>" id="default_bank_account_id" name="default_bank_account_id">
                            <option value=""><?= lang('Proposals.noneOption') ?></option>
                            <?php foreach ($bankAccounts as $id => $label) : ?>
                                <option value="<?= $id ?>"<?= form_value('default_bank_account_id', $profile) === (string) $id ? ' selected' : '' ?>><?= esc($label) ?></option>
                            <?php endforeach ?>
                        </select>
                        <?= field_error('default_bank_account_id') ?>
                        <div class="form-text"><?= lang('Settings.defaultBankHelp') ?> <a href="<?= site_url('settings/bank-accounts') ?>"><?= lang('Settings.manageBanks') ?></a></div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="website" class="form-label"><?= lang('Settings.website') ?></label>
                        <input type="text" class="form-control<?= invalid('website') ?>" id="website" name="website" maxlength="255"
                               value="<?= esc(form_value('website', $profile)) ?>">
                        <?= field_error('website') ?>
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="document_city" class="form-label"><?= lang('Settings.documentCity') ?></label>
                        <input type="text" class="form-control<?= invalid('document_city') ?>" id="document_city" name="document_city" maxlength="100"
                               placeholder="<?= esc(lang('Settings.documentCityHint'), 'attr') ?>" value="<?= esc(form_value('document_city', $profile)) ?>">
                        <?= field_error('document_city') ?>
                        <div class="form-text"><?= lang('Settings.documentCityHelp') ?></div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mt-3">
                <div class="card-header bg-white fw-semibold"><?= lang('Settings.pdfDocs') ?></div>
                <div class="card-body row g-3">
                    <div class="col-12 col-md-7">
                        <label for="signatory_name" class="form-label"><?= lang('Settings.signatoryName') ?></label>
                        <input type="text" class="form-control<?= invalid('signatory_name') ?>" id="signatory_name" name="signatory_name" maxlength="128"
                               value="<?= esc(form_value('signatory_name', $profile)) ?>">
                        <?= field_error('signatory_name') ?>
                    </div>
                    <div class="col-12 col-md-5">
                        <label for="signatory_position" class="form-label"><?= lang('Settings.signatoryPosition') ?></label>
                        <input type="text" class="form-control<?= invalid('signatory_position') ?>" id="signatory_position" name="signatory_position" maxlength="64"
                               placeholder="<?= esc(lang('Settings.signatoryPositionHint'), 'attr') ?>" value="<?= esc(form_value('signatory_position', $profile)) ?>">
                        <?= field_error('signatory_position') ?>
                    </div>
                    <div class="col-12">
                        <label for="invoice_terms" class="form-label"><?= lang('Settings.invoiceTerms') ?></label>
                        <textarea class="form-control<?= invalid('invoice_terms') ?>" id="invoice_terms" name="invoice_terms" rows="4"
                                  placeholder="<?= esc(lang('Settings.invoiceTermsHint'), 'attr') ?>"><?= esc(form_value('invoice_terms', $profile)) ?></textarea>
                        <?= field_error('invoice_terms') ?>
                        <div class="form-text"><?= lang('Settings.invoiceTermsHelp') ?></div>
                    </div>
                    <div class="col-12">
                        <label for="agreement_terms" class="form-label"><?= lang('Settings.agreementTerms') ?></label>
                        <textarea class="form-control<?= invalid('agreement_terms') ?>" id="agreement_terms" name="agreement_terms" rows="6"
                                  placeholder="<?= esc(lang('Settings.agreementTermsHint'), 'attr') ?>"><?= esc(form_value('agreement_terms', $profile)) ?></textarea>
                        <?= field_error('agreement_terms') ?>
                        <div class="form-text"><?= lang('Settings.agreementTermsHelp') ?></div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mt-3">
                <div class="card-header bg-white fw-semibold"><?= lang('Settings.taxStatus') ?></div>
                <div class="card-body row g-3">
                    <div class="col-12">
                        <input type="hidden" name="is_pkp" value="0">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="is_pkp" name="is_pkp" value="1"<?= form_checked('is_pkp', $profile, true) ?>>
                            <label class="form-check-label" for="is_pkp"><?= lang('Settings.pkp') ?></label>
                        </div>
                        <div class="form-text"><?= lang('Settings.pkpHelp') ?></div>
                    </div>
                    <div class="col-12 col-md-7">
                        <label for="umkm_certificate_number" class="form-label"><?= lang('Settings.umkmNumber') ?></label>
                        <input type="text" class="form-control<?= invalid('umkm_certificate_number') ?>" id="umkm_certificate_number" name="umkm_certificate_number"
                               maxlength="64" value="<?= esc(form_value('umkm_certificate_number', $profile)) ?>">
                        <?= field_error('umkm_certificate_number') ?>
                    </div>
                    <div class="col-12 col-md-5">
                        <label for="umkm_certificate_valid_until" class="form-label"><?= lang('Settings.validUntil') ?></label>
                        <input type="date" class="form-control<?= invalid('umkm_certificate_valid_until') ?>" id="umkm_certificate_valid_until" name="umkm_certificate_valid_until"
                               value="<?= esc(form_value('umkm_certificate_valid_until', $profile)) ?>">
                        <?= field_error('umkm_certificate_valid_until') ?>
                    </div>
                    <div class="col-12">
                        <div class="form-text mt-0"><?= lang('Settings.umkmHelp') ?> <a href="<?= site_url('settings/lookups/tax-withholding-rules') ?>"><?= lang('Settings.whRules') ?></a>.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="card shadow-sm">
                <div class="card-header bg-white fw-semibold"><?= lang('Settings.logo') ?></div>
                <div class="card-body">
                    <div class="border rounded p-3 mb-3 text-center bg-white">
                        <?php if (! empty($profile['logo_path'])) : ?>
                            <img src="<?= site_url('settings/company/logo') ?>?v=<?= esc(md5($profile['logo_path']), 'url') ?>" alt="Company logo" class="img-fluid" style="max-height: 140px">
                        <?php else : ?>
                            <img src="<?= base_url('assets/img/logo-full.png') ?>" alt="Default logo" class="img-fluid opacity-75" style="max-height: 140px">
                            <div class="small text-body-secondary mt-2"><?= lang('Settings.logoDefault') ?></div>
                        <?php endif ?>
                    </div>
                    <label for="logo" class="form-label"><?= lang('Settings.uploadLogo') ?></label>
                    <input type="file" class="form-control<?= invalid('logo') ?>" id="logo" name="logo" accept="image/png,image/jpeg,image/webp">
                    <?= field_error('logo') ?>
                    <div class="form-text"><?= lang('Settings.logoHelp') ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mt-3">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i><?= lang('Settings.saveProfile') ?></button>
        <a href="<?= site_url('settings') ?>" class="btn btn-outline-secondary"><?= lang('App.cancel') ?></a>
    </div>
</form>

<?= $this->endSection() ?>
