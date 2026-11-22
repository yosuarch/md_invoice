<?php
/**
 * Edit a person (shared by all their schools).
 *
 * @var array<string, mixed>       $person
 * @var list<array<string, mixed>> $links Schools of the person.
 */

use App\Models\ContactPersonModel;

$name = ContactPersonModel::displayName($person);
?>
<?= $this->extend('layouts/app_layout') ?>

<?= $this->section('title') ?><?= lang('Contacts.editPerson') ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('components/page_header', [
    'title'      => lang('Contacts.editPerson'),
    'subtitle'   => $name,
    'breadcrumb' => [[lang('Contacts.contacts'), site_url('contacts')], [$name, site_url('contacts/' . $person['id'])], [lang('App.edit'), '']],
]) ?>

<?php if (count($links) > 1) : ?>
    <div class="alert alert-info small" style="max-width: 860px"><i class="bi bi-diagram-3 me-1"></i><?= esc(lang('Contacts.sharedInfo', [implode(', ', array_map(static fn (array $l): string => up($l['cl_organization_name']), $links))])) ?></div>
<?php endif ?>

<div class="card shadow-sm" style="max-width: 860px">
    <div class="card-body">
        <form action="<?= site_url('contacts/' . $person['id']) ?>" method="post" class="row g-3" novalidate>
            <?= csrf_field() ?>
            <div class="col-12 col-md-6">
                <label for="cp_first_name" class="form-label"><?= lang('Customers.firstName') ?> <span class="text-danger">*</span></label>
                <input type="text" class="form-control text-uppercase<?= invalid('cp_first_name') ?>" id="cp_first_name" name="cp_first_name" maxlength="64" required value="<?= esc(form_value('cp_first_name', $person)) ?>">
                <?= field_error('cp_first_name') ?>
            </div>
            <div class="col-12 col-md-6">
                <label for="cp_last_name" class="form-label"><?= lang('Customers.lastName') ?></label>
                <input type="text" class="form-control text-uppercase<?= invalid('cp_last_name') ?>" id="cp_last_name" name="cp_last_name" maxlength="64" value="<?= esc(form_value('cp_last_name', $person)) ?>">
                <?= field_error('cp_last_name') ?>
            </div>
            <div class="col-12 col-md-5">
                <label for="cp_phone" class="form-label"><?= lang('Contacts.phone') ?> <span class="text-danger">*</span></label>
                <input type="tel" class="form-control<?= invalid('cp_phone') ?>" id="cp_phone" name="cp_phone" maxlength="32" required value="<?= esc(form_value('cp_phone', $person)) ?>">
                <?= field_error('cp_phone') ?>
                <input type="hidden" name="cp_whatsapp" value="0">
                <div class="form-check mt-1">
                    <input class="form-check-input" type="checkbox" id="cp_whatsapp" name="cp_whatsapp" value="1"<?= form_checked('cp_whatsapp', $person, true) ?>>
                    <label class="form-check-label small" for="cp_whatsapp"><i class="bi bi-whatsapp text-success me-1"></i><?= lang('Contacts.whatsapp') ?></label>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <label for="cp_email" class="form-label"><?= lang('Contacts.email') ?></label>
                <input type="email" class="form-control<?= invalid('cp_email') ?>" id="cp_email" name="cp_email" maxlength="255" value="<?= esc(form_value('cp_email', $person)) ?>">
                <?= field_error('cp_email') ?>
            </div>
            <div class="col-12 col-md-3">
                <label for="cp_nip" class="form-label"><?= lang('Contacts.nip') ?></label>
                <input type="text" class="form-control<?= invalid('cp_nip') ?>" id="cp_nip" name="cp_nip" maxlength="32" inputmode="numeric" value="<?= esc(form_value('cp_nip', $person)) ?>">
                <?= field_error('cp_nip') ?>
            </div>
            <div class="col-12">
                <label for="cp_note" class="form-label"><?= lang('Contacts.note') ?></label>
                <textarea class="form-control" id="cp_note" name="cp_note" rows="2" maxlength="2000" placeholder="<?= esc(lang('Contacts.notesHelp'), 'attr') ?>"><?= esc(form_value('cp_note', $person)) ?></textarea>
            </div>
            <div class="col-12">
                <input type="hidden" name="is_active" value="0">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1"<?= form_checked('is_active', $person, true) ?>>
                    <label class="form-check-label" for="is_active"><?= lang('Contacts.active') ?></label>
                </div>
            </div>
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i><?= lang('App.save') ?></button>
                <a href="<?= site_url('contacts/' . $person['id']) ?>" class="btn btn-outline-secondary"><?= lang('App.cancel') ?></a>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>
