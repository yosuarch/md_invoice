<?php
/**
 * Person page: details, schools with role, activities with this person.
 *
 * @var array<string, mixed>            $person
 * @var list<array<string, mixed>>      $links      Schools of the person (visible to the user).
 * @var list<array<string, mixed>>|null $activities Null without crm.view.
 */

use App\Models\ContactPersonModel;
use App\Models\CustomerContactModel;

$name = ContactPersonModel::displayName($person);
$wa   = $person['cp_whatsapp'] ? ContactPersonModel::whatsappUrl($person['cp_phone']) : null;
$actions = '';

if ($wa !== null) {
    $actions .= '<a href="' . esc($wa, 'attr') . '" target="_blank" rel="noopener" class="btn btn-success"><i class="bi bi-whatsapp me-1"></i>' . esc(lang('Contacts.chatWhatsapp')) . '</a>';
}

if (! empty($person['cp_phone'])) {
    $actions .= '<a href="tel:' . esc(preg_replace('/[^0-9+]/', '', (string) $person['cp_phone']), 'attr') . '" class="btn btn-outline-primary"><i class="bi bi-telephone me-1"></i>' . esc(lang('Contacts.call')) . '</a>';
}

if (user_can('contacts.manage')) {
    $actions .= '<a href="' . site_url('contacts/' . $person['id'] . '/edit') . '" class="btn btn-outline-primary"><i class="bi bi-pencil me-1"></i>' . esc(lang('App.edit')) . '</a>';
}
?>
<?= $this->extend('layouts/app_layout') ?>

<?= $this->section('title') ?><?= esc($name) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('components/page_header', [
    'title'      => $name,
    'subtitle'   => implode(' · ', array_filter([$person['cp_phone'], $person['cp_email']])),
    'breadcrumb' => [[lang('Contacts.contacts'), site_url('contacts')], [$name, '']],
    'actions'    => $actions,
]) ?>

<div class="row g-3">
    <div class="col-12 col-xl-4">
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold d-flex justify-content-between"><span><?= lang('App.details') ?></span><?= active_badge($person['is_active']) ?></div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-5 text-body-secondary fw-normal"><?= lang('Contacts.phone') ?></dt><dd class="col-7"><?= esc($person['cp_phone'] ?: '-') ?><?= $person['cp_whatsapp'] ? ' <i class="bi bi-whatsapp text-success"></i>' : '' ?></dd>
                    <dt class="col-5 text-body-secondary fw-normal"><?= lang('Contacts.email') ?></dt><dd class="col-7 text-break"><?= esc($person['cp_email'] ?: '-') ?></dd>
                    <dt class="col-5 text-body-secondary fw-normal"><?= lang('Contacts.nip') ?></dt><dd class="col-7 font-monospace"><?= esc($person['cp_nip'] ?: '-') ?></dd>
                    <dt class="col-5 text-body-secondary fw-normal"><?= lang('Contacts.note') ?></dt><dd class="col-7 mb-0 text-break"><?= $person['cp_note'] ? nl2br(esc($person['cp_note'])) : '-' ?></dd>
                </dl>
            </div>
        </div>
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-building me-1"></i><?= lang('Contacts.schools') ?></div>
            <div class="list-group list-group-flush">
                <?php foreach ($links as $l) : ?>
                    <a href="<?= site_url('customers/' . $l['customer_id'] . '#contacts') ?>" class="list-group-item list-group-item-action small<?= $l['ended_on'] ? ' text-body-tertiary' : '' ?>">
                        <div class="fw-semibold"><?= esc(up($l['cl_organization_name'])) ?>
                            <?php if ($l['is_primary'] && ! $l['ended_on']) : ?><span class="badge bg-primary ms-1"><?= lang('Customers.primary') ?></span><?php endif ?></div>
                        <div><?= esc(CustomerContactModel::roleLabel($l)) ?><?= $l['ended_on'] ? ' · ' . esc(lang('Contacts.leftOnShort', [format_date($l['ended_on'])])) : '' ?></div>
                    </a>
                <?php endforeach ?>
                <?php if ($links === []) : ?><div class="list-group-item small text-body-secondary"><?= lang('Contacts.noSchools') ?></div><?php endif ?>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-8">
        <?php if ($activities !== null) : ?>
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-calendar-check me-1"></i><?= lang('Contacts.activities') ?></div>
                <div class="list-group list-group-flush">
                    <?php foreach ($activities as $a) : ?>
                        <?= view('components/activity_item', ['a' => $a, 'showSchool' => true, 'returnTo' => 'contacts/' . $person['id'], 'users' => \App\Services\UserService::activeOptions()], ['saveData' => false]) ?>
                    <?php endforeach ?>
                    <?php if ($activities === []) : ?><div class="list-group-item text-center text-body-secondary small py-3"><?= lang('Activities.none') ?></div><?php endif ?>
                </div>
            </div>
        <?php endif ?>
    </div>
</div>

<?= $this->endSection() ?>
