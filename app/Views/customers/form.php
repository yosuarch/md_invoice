<?php
/**
 * @var array<string, mixed> $customer     Empty on create.
 * @var list<int>            $assignedReps Selected sales rep ids.
 * @var array<int, string>   $terms
 * @var array<int, string>   $levels
 * @var array<int, string>   $reps
 */

use App\Models\CustomerModel;

$isEdit   = $customer !== [];
$title    = $isEdit ? lang('Customers.editCustomer') : lang('Customers.newCustomer');
$action   = $isEdit ? site_url('customers/' . $customer['id']) : site_url('customers');
$back     = $isEdit ? site_url('customers/' . $customer['id']) : site_url('customers');
$selected = array_map('intval', (array) (old('sales_reps') ?? $assignedReps));
?>
<?= $this->extend('layouts/app_layout') ?>

<?= $this->section('title') ?><?= esc($title) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('components/page_header', [
    'title'      => $title,
    'subtitle'   => $isEdit ? up($customer['cl_organization_name']) : lang('Customers.uuidInfo'),
    'breadcrumb' => array_merge(
        [[lang('Customers.customers'), site_url('customers')]],
        $isEdit ? [[up($customer['cl_organization_name']), $back], [lang('App.edit'), '']] : [[lang('App.new'), '']],
    ),
]) ?>

<form action="<?= $action ?>" method="post" novalidate>
    <?= csrf_field() ?>
    <div class="row g-3" style="max-width: 1100px">
        <div class="col-12 col-xl-7">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold"><?= lang('Customers.organization') ?></div>
                <div class="card-body row g-3">
                    <div class="col-12">
                        <label for="cl_organization_name" class="form-label"><?= lang('Customers.orgName') ?> <span class="text-danger">*</span></label>
                        <input type="text" class="form-control text-uppercase<?= invalid('cl_organization_name') ?>" id="cl_organization_name"
                               name="cl_organization_name" maxlength="200" required autofocus placeholder="<?= esc(lang('Customers.orgNameHint'), 'attr') ?>"
                               value="<?= esc(form_value('cl_organization_name', $customer)) ?>">
                        <?= field_error('cl_organization_name') ?>
                    </div>
                    <div class="col-12 col-md-4">
                        <label for="npsn" class="form-label">NPSN</label>
                        <input type="text" class="form-control font-monospace<?= invalid('npsn') ?>" id="npsn" name="npsn" maxlength="8"
                               inputmode="numeric" placeholder="<?= esc(lang('Customers.npsnHint'), 'attr') ?>" value="<?= esc(form_value('npsn', $customer)) ?>">
                        <?= field_error('npsn') ?>
                    </div>
                    <div class="col-12 col-md-4">
                        <label for="school_level_id" class="form-label"><?= lang('Customers.schoolLevel') ?></label>
                        <select class="form-select<?= invalid('school_level_id') ?>" id="school_level_id" name="school_level_id">
                            <option value=""><?= lang('Customers.notASchool') ?></option>
                            <?php foreach ($levels as $id => $label) : ?>
                                <option value="<?= $id ?>"<?= form_value('school_level_id', $customer) === (string) $id ? ' selected' : '' ?>><?= esc($label) ?></option>
                            <?php endforeach ?>
                        </select>
                        <?= field_error('school_level_id') ?>
                    </div>
                    <div class="col-12 col-md-4">
                        <label for="school_status" class="form-label"><?= lang('App.type') ?></label>
                        <select class="form-select<?= invalid('school_status') ?>" id="school_status" name="school_status">
                            <option value=""><?= lang('Customers.notSet') ?></option>
                            <?php foreach (array_keys(CustomerModel::SCHOOL_STATUSES) as $key) : ?>
                                <option value="<?= $key ?>"<?= form_value('school_status', $customer) === $key ? ' selected' : '' ?>><?= esc(code_label('schoolStatus', $key)) ?></option>
                            <?php endforeach ?>
                        </select>
                        <?= field_error('school_status') ?>
                    </div>
                    <div class="col-12">
                        <div class="form-text mt-0"><i class="bi bi-info-circle me-1"></i><?= lang('Customers.publicInfo') ?></div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="cl_tax_id" class="form-label">NPWP</label>
                        <input type="text" class="form-control<?= invalid('cl_tax_id') ?>" id="cl_tax_id" name="cl_tax_id" maxlength="32"
                               placeholder="<?= esc(lang('Customers.npwpHint'), 'attr') ?>" value="<?= esc(form_value('cl_tax_id', $customer)) ?>">
                        <?= field_error('cl_tax_id') ?>
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="cl_phone" class="form-label"><?= lang('Customers.phone') ?></label>
                        <input type="tel" class="form-control<?= invalid('cl_phone') ?>" id="cl_phone" name="cl_phone" maxlength="32"
                               value="<?= esc(form_value('cl_phone', $customer)) ?>">
                        <?= field_error('cl_phone') ?>
                    </div>
                    <div class="col-12">
                        <label for="cl_email" class="form-label"><?= lang('Customers.email') ?></label>
                        <input type="email" class="form-control<?= invalid('cl_email') ?>" id="cl_email" name="cl_email" maxlength="255"
                               value="<?= esc(form_value('cl_email', $customer)) ?>">
                        <?= field_error('cl_email') ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-5">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold"><?= lang('Customers.salesRepsLong') ?></div>
                <div class="card-body">
                    <?php if ($reps === []) : ?>
                        <div class="small text-body-secondary"><?= lang('Customers.noRepsYet') ?> <a href="<?= site_url('sales-reps/new') ?>"><?= lang('Customers.addOne') ?></a>.</div>
                    <?php endif ?>
                    <?php foreach ($reps as $id => $name) : ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="sales_reps[]" id="rep_<?= $id ?>" value="<?= $id ?>"<?= in_array($id, $selected, true) ? ' checked' : '' ?>>
                            <label class="form-check-label" for="rep_<?= $id ?>"><?= esc(up($name)) ?></label>
                        </div>
                    <?php endforeach ?>
                    <?= isset(form_errors()['sales_reps']) ? '<div class="text-danger small mt-1">' . esc(form_errors()['sales_reps']) . '</div>' : '' ?>
                </div>
            </div>

            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold"><?= lang('Customers.commercial') ?></div>
                <div class="card-body row g-3">
                    <div class="col-12">
                        <label for="default_payment_term_id" class="form-label"><?= lang('Customers.defaultTerms') ?></label>
                        <select class="form-select<?= invalid('default_payment_term_id') ?>" id="default_payment_term_id" name="default_payment_term_id">
                            <option value=""><?= lang('Customers.noneOption') ?></option>
                            <?php foreach ($terms as $id => $label) : ?>
                                <option value="<?= $id ?>"<?= form_value('default_payment_term_id', $customer) === (string) $id ? ' selected' : '' ?>><?= esc($label) ?></option>
                            <?php endforeach ?>
                        </select>
                        <?= field_error('default_payment_term_id') ?>
                    </div>
                    <div class="col-12">
                        <label for="credit_limit" class="form-label"><?= lang('Customers.creditLimit') ?></label>
                        <div class="input-group has-validation">
                            <span class="input-group-text">Rp</span>
                            <input type="number" class="form-control<?= invalid('credit_limit') ?>" id="credit_limit" name="credit_limit"
                                   min="0" step="0.01" value="<?= esc(form_value('credit_limit', $customer)) ?>">
                            <?= field_error('credit_limit') ?>
                        </div>
                        <div class="form-text"><?= lang('Customers.creditLimitHelp') ?></div>
                    </div>
                    <div class="col-12">
                        <input type="hidden" name="is_active" value="0">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1"<?= form_checked('is_active', $customer, true) ?>>
                            <label class="form-check-label" for="is_active"><?= lang('Customers.activeHelp') ?></label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 d-flex gap-2">
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i><?= lang('App.save') ?></button>
            <a href="<?= $back ?>" class="btn btn-outline-secondary"><?= lang('App.cancel') ?></a>
        </div>
    </div>
</form>

<?= $this->endSection() ?>
