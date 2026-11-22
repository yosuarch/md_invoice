<?php
/**
 * Add a contact to a school (existing person or a new one) or edit the link and the person.
 *
 * @var array<string, mixed>       $customer
 * @var array<string, mixed>       $contact Link with person fields (cp_*); empty on create.
 * @var array<int, string>         $roles   Contact roles.
 * @var list<array<string, mixed>> $others  Schools of the person (edit).
 */
$isEdit = $contact !== [];
$cid    = (int) $customer['id'];
$title  = $isEdit ? lang('Customers.editContact') : lang('Customers.addContact');
$action = $isEdit ? site_url("customers/{$cid}/contacts/{$contact['id']}") : site_url("customers/{$cid}/contacts");
$otherSchools = array_values(array_filter($others, static fn (array $l): bool => (int) $l['customer_id'] !== $cid));
$existing = (int) old('contact_id', null, false);
?>
<?= $this->extend('layouts/app_layout') ?>

<?= $this->section('title') ?><?= esc($title) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('components/page_header', [
    'title'      => $title,
    'subtitle'   => up($customer['cl_organization_name']),
    'breadcrumb' => [[lang('Customers.customers'), site_url('customers')], [up($customer['cl_organization_name']), site_url("customers/{$cid}")], [$title, '']],
]) ?>

<form action="<?= $action ?>" method="post" novalidate id="contact-form" data-search-url="<?= site_url('api/contacts') ?>" style="max-width: 900px">
    <?= csrf_field() ?>

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold"><i class="bi bi-person me-1"></i><?= lang('Contacts.personDetails') ?></div>
        <div class="card-body row g-3">
            <?php if (! $isEdit) : ?>
                <div class="col-12">
                    <label for="person_search" class="form-label"><?= lang('Contacts.existingPerson') ?></label>
                    <input type="hidden" name="contact_id" id="contact_id" value="<?= $existing > 0 ? $existing : '' ?>">
                    <div class="position-relative">
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control<?= invalid('contact_id') ?>" id="person_search" autocomplete="off" placeholder="<?= esc(lang('Contacts.existingHint'), 'attr') ?>">
                            <button type="button" class="btn btn-outline-secondary" id="person_clear" hidden><?= lang('Contacts.clearPerson') ?></button>
                            <?= field_error('contact_id') ?>
                        </div>
                    </div>
                    <div class="form-text" id="person_info"></div>
                </div>
                <div class="col-12 small text-body-secondary fw-semibold" data-new-person><?= lang('Contacts.orNewPerson') ?></div>
            <?php elseif ($otherSchools !== []) : ?>
                <div class="col-12">
                    <div class="alert alert-info small mb-0"><i class="bi bi-diagram-3 me-1"></i><?= esc(lang('Contacts.sharedInfo', [implode(', ', array_map(static fn (array $l): string => up($l['cl_organization_name']), $otherSchools))])) ?></div>
                </div>
            <?php endif ?>
            <div class="col-12 col-md-6" data-new-person>
                <label for="cp_first_name" class="form-label"><?= lang('Customers.firstName') ?> <span class="text-danger">*</span></label>
                <input type="text" class="form-control text-uppercase<?= invalid('cp_first_name') ?>" id="cp_first_name" name="cp_first_name" maxlength="64"
                       value="<?= esc(form_value('cp_first_name', $contact)) ?>">
                <?= field_error('cp_first_name') ?>
            </div>
            <div class="col-12 col-md-6" data-new-person>
                <label for="cp_last_name" class="form-label"><?= lang('Customers.lastName') ?></label>
                <input type="text" class="form-control text-uppercase<?= invalid('cp_last_name') ?>" id="cp_last_name" name="cp_last_name" maxlength="64"
                       value="<?= esc(form_value('cp_last_name', $contact)) ?>">
                <?= field_error('cp_last_name') ?>
            </div>
            <div class="col-12 col-md-5" data-new-person>
                <label for="cp_phone" class="form-label"><?= lang('Contacts.phone') ?> <span class="text-danger">*</span></label>
                <input type="tel" class="form-control<?= invalid('cp_phone') ?>" id="cp_phone" name="cp_phone" maxlength="32"
                       value="<?= esc(form_value('cp_phone', $contact)) ?>">
                <?= field_error('cp_phone') ?>
                <input type="hidden" name="cp_whatsapp" value="0">
                <div class="form-check mt-1">
                    <input class="form-check-input" type="checkbox" id="cp_whatsapp" name="cp_whatsapp" value="1"<?= form_checked('cp_whatsapp', $contact, true) ?>>
                    <label class="form-check-label small" for="cp_whatsapp"><i class="bi bi-whatsapp text-success me-1"></i><?= lang('Contacts.whatsapp') ?></label>
                </div>
            </div>
            <div class="col-12 col-md-4" data-new-person>
                <label for="cp_email" class="form-label"><?= lang('Contacts.email') ?></label>
                <input type="email" class="form-control<?= invalid('cp_email') ?>" id="cp_email" name="cp_email" maxlength="255"
                       value="<?= esc(form_value('cp_email', $contact)) ?>">
                <?= field_error('cp_email') ?>
            </div>
            <div class="col-12 col-md-3" data-new-person>
                <label for="cp_nip" class="form-label"><?= lang('Contacts.nip') ?></label>
                <input type="text" class="form-control<?= invalid('cp_nip') ?>" id="cp_nip" name="cp_nip" maxlength="32" inputmode="numeric"
                       value="<?= esc(form_value('cp_nip', $contact)) ?>">
                <?= field_error('cp_nip') ?>
            </div>
            <div class="col-12" data-new-person>
                <label for="cp_note" class="form-label"><?= lang('Contacts.note') ?></label>
                <input type="text" class="form-control" id="cp_note" name="cp_note" maxlength="2000" placeholder="<?= esc(lang('Contacts.notesHelp'), 'attr') ?>"
                       value="<?= esc(form_value('cp_note', $contact)) ?>">
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold"><i class="bi bi-building me-1"></i><?= lang('Contacts.schoolLink') ?></div>
        <div class="card-body row g-3">
            <div class="col-12 col-md-5">
                <label for="role_id" class="form-label"><?= lang('Contacts.role') ?></label>
                <select class="form-select<?= invalid('role_id') ?>" id="role_id" name="role_id">
                    <option value=""><?= lang('App.select') ?></option>
                    <?php foreach ($roles as $id => $label) : ?>
                        <option value="<?= $id ?>"<?= form_value('role_id', $contact) === (string) $id ? ' selected' : '' ?>><?= esc($label) ?></option>
                    <?php endforeach ?>
                </select>
                <?= field_error('role_id') ?>
            </div>
            <div class="col-12 col-md-7">
                <label for="ccp_position" class="form-label"><?= lang('Contacts.position') ?></label>
                <input type="text" class="form-control text-uppercase<?= invalid('ccp_position') ?>" id="ccp_position" name="ccp_position" maxlength="64"
                       value="<?= esc(form_value('ccp_position', $contact)) ?>">
                <?= field_error('ccp_position') ?>
                <div class="form-text"><?= lang('Contacts.positionHelp') ?></div>
            </div>
            <div class="col-12 col-md-5">
                <input type="hidden" name="is_primary" value="0">
                <div class="form-check mt-md-4">
                    <input class="form-check-input" type="checkbox" id="is_primary" name="is_primary" value="1"<?= form_checked('is_primary', $contact) ?>>
                    <label class="form-check-label" for="is_primary"><?= lang('Contacts.primary') ?></label>
                </div>
            </div>
            <?php if ($isEdit) : ?>
                <div class="col-12 col-md-7">
                    <label for="ended_on" class="form-label"><?= lang('Contacts.leftOn') ?></label>
                    <input type="date" class="form-control<?= invalid('ended_on') ?>" id="ended_on" name="ended_on" max="<?= date('Y-m-d') ?>"
                           value="<?= esc(form_value('ended_on', $contact)) ?>">
                    <?= field_error('ended_on') ?>
                    <div class="form-text"><?= lang('Contacts.leftOnHelp') ?></div>
                </div>
            <?php endif ?>
        </div>
    </div>

    <div class="d-flex gap-2 mb-4">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i><?= lang('App.save') ?></button>
        <a href="<?= site_url("customers/{$cid}#contacts") ?>" class="btn btn-outline-secondary"><?= lang('App.cancel') ?></a>
    </div>
</form>

<?= $this->endSection() ?>

<?= $this->section('pageScripts') ?>
<script>
// Existing person picker: choosing a person hides the new-person fields (they are ignored by the server).
(() => {
    const form = document.getElementById('contact-form');
    const input = document.getElementById('person_search');
    if (!form || !input) return;
    const hidden = document.getElementById('contact_id');
    const info = document.getElementById('person_info');
    const clear = document.getElementById('person_clear');
    const toggle = (picked) => {
        form.querySelectorAll('[data-new-person]').forEach((el) => { el.hidden = picked; });
        clear.hidden = !picked;
        input.readOnly = picked;
    };
    App.typeahead(input, {
        url: form.dataset.searchUrl,
        label: (p) => p.name + (p.phone ? ' · ' + p.phone : ''),
        detail: (p) => p.schools || p.email || '',
        onSelect: (p) => {
            hidden.value = p.id;
            input.value = p.name;
            info.textContent = p.schools;
            toggle(true);
        },
    });
    clear.addEventListener('click', () => {
        hidden.value = '';
        input.value = '';
        info.textContent = '';
        toggle(false);
        input.focus();
    });
    toggle(hidden.value !== '');
})();
</script>
<?= $this->endSection() ?>
