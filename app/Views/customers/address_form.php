<?php
/**
 * @var array<string, mixed> $customer
 * @var array<string, mixed> $address Empty on create.
 * @var string               $type    BILLING or SHIPPING (preselected).
 * @var array<string, list<array{code: string, name: string}>> $regions provinces + cities of the selected province.
 */

use App\Models\CustomerAddressModel;

$isEdit = $address !== [];
$cid    = (int) $customer['id'];
$title  = $isEdit ? lang('Customers.editAddress') : lang('Customers.addAddress');
$action = $isEdit ? site_url("customers/{$cid}/addresses/{$address['id']}") : site_url("customers/{$cid}/addresses");
$current = form_value('address_type', $address, $type);
?>
<?= $this->extend('layouts/app_layout') ?>

<?= $this->section('title') ?><?= esc($title) ?><?= $this->endSection() ?>


<?= $this->section('content') ?>

<?= view('components/page_header', [
    'title'      => $title,
    'subtitle'   => up($customer['cl_organization_name']),
    'breadcrumb' => [[lang('Customers.customers'), site_url('customers')], [up($customer['cl_organization_name']), site_url("customers/{$cid}")], [$title, '']],
]) ?>

<div class="card shadow-sm" style="max-width: 760px">
    <div class="card-body">
        <form action="<?= $action ?>" method="post" class="row g-3" novalidate>
            <?= csrf_field() ?>
            <div class="col-12">
                <div class="form-label"><?= lang('App.type') ?> <span class="text-danger">*</span></div>
                <?php foreach (array_keys(CustomerAddressModel::TYPES) as $key) : ?>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input<?= invalid('address_type') ?>" type="radio" name="address_type" id="type_<?= $key ?>"
                               value="<?= $key ?>"<?= $current === $key ? ' checked' : '' ?>>
                        <label class="form-check-label" for="type_<?= $key ?>"><?= esc(code_label('addressType', $key)) ?></label>
                    </div>
                <?php endforeach ?>
                <?= field_error('address_type') ?>
            </div>
            <div class="col-12">
                <label for="address_line" class="form-label"><?= lang('Customers.address') ?> <span class="text-danger">*</span></label>
                <textarea class="form-control text-uppercase<?= invalid('address_line') ?>" id="address_line" name="address_line" rows="3" required
                          placeholder="<?= esc(lang('Customers.addressHint'), 'attr') ?>"><?= esc(form_value('address_line', $address)) ?></textarea>
                <?= field_error('address_line') ?>
            </div>
            <?= view('components/region_selects', ['record' => $address, 'regions' => $regions, 'colClass' => 'col-12 col-md-4']) ?>
            <div class="col-12 col-md-3">
                <label for="postal_code" class="form-label"><?= lang('Customers.postalCode') ?></label>
                <input type="text" class="form-control<?= invalid('postal_code') ?>" id="postal_code" name="postal_code" maxlength="5"
                       inputmode="numeric" value="<?= esc(form_value('postal_code', $address)) ?>">
                <?= field_error('postal_code') ?>
            </div>
            <div class="col-12">
                <input type="hidden" name="is_default" value="0">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="is_default" name="is_default" value="1"<?= form_checked('is_default', $address) ?>>
                    <label class="form-check-label" for="is_default"><?= lang('Customers.defaultHelp') ?></label>
                </div>
            </div>
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i><?= lang('App.save') ?></button>
                <a href="<?= site_url("customers/{$cid}#addresses") ?>" class="btn btn-outline-secondary"><?= lang('App.cancel') ?></a>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>

