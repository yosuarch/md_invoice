<?php
/**
 * @var array<string, mixed>       $customer
 * @var array<string, mixed>|null  $term
 * @var array<string, mixed>|null  $level
 * @var list<array<string, mixed>> $reps
 * @var list<array<string, mixed>> $contacts
 * @var list<array<string, mixed>> $addresses
 * @var list<array<string, mixed>>|null $proposals Recent proposals (null without proposals.view)
 * @var array<string, mixed>|null      $activityPanel Activities & follow-ups (null without crm.view)
 * @var list<array<string, mixed>>|null $invoices  Recent invoices (null without invoices.view); open ones show the balance
 */

use App\Models\ContactPersonModel;
use App\Models\CustomerAddressModel;
use App\Models\CustomerContactModel;
use App\Models\InvoiceModel;

$name     = up($customer['cl_organization_name']);
$subtitle = implode(' · ', array_filter([
    $level['name'] ?? null,
    $customer['school_status'] ? code_label('schoolStatus', $customer['school_status']) : null,
    $customer['npsn'] ? 'NPSN ' . $customer['npsn'] : null,
]));

$canManage   = user_can('customers.manage');
$canContacts = user_can('contacts.manage');
$cid       = (int) $customer['id'];
$actions   = '';

if ($canManage) {
    $actions = '<a href="' . site_url("customers/{$cid}/edit") . '" class="btn btn-outline-primary"><i class="bi bi-pencil me-1"></i>' . esc(lang('App.edit')) . '</a>'
        . view('components/delete_button', [
            'action'  => site_url("customers/{$cid}/delete"),
            'label'   => lang('App.delete'),
            'icon'    => 'trash',
            'class'   => 'btn btn-outline-danger',
            'confirm' => lang('Customers.deleteConfirm', [$name]),
        ]);
}
?>
<?= $this->extend('layouts/app_layout') ?>

<?= $this->section('title') ?><?= esc($name) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('components/page_header', [
    'title'      => $name,
    'subtitle'   => $subtitle,
    'breadcrumb' => [[lang('Customers.customers'), site_url('customers')], [$name, '']],
    'actions'    => $actions,
]) ?>

<div class="row g-3">
    <div class="col-12 col-xl-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-semibold d-flex justify-content-between">
                <span><?= lang('App.details') ?></span><?= active_badge($customer['is_active']) ?>
            </div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-5 text-body-secondary fw-normal">NPSN</dt><dd class="col-7 font-monospace"><?= esc($customer['npsn'] ?? '-') ?></dd>
                    <dt class="col-5 text-body-secondary fw-normal"><?= lang('Customers.schoolLevel') ?></dt><dd class="col-7"><?= esc($level['name'] ?? '-') ?></dd>
                    <dt class="col-5 text-body-secondary fw-normal"><?= lang('App.type') ?></dt><dd class="col-7"><?= $customer['school_status'] ? esc(code_label('schoolStatus', $customer['school_status'])) : '-' ?></dd>
                    <dt class="col-5 text-body-secondary fw-normal">NPWP</dt><dd class="col-7"><?= esc($customer['cl_tax_id'] ?? '-') ?></dd>
                    <dt class="col-5 text-body-secondary fw-normal"><?= lang('Customers.email') ?></dt><dd class="col-7 text-break"><?= esc($customer['cl_email'] ?? '-') ?></dd>
                    <dt class="col-5 text-body-secondary fw-normal"><?= lang('Customers.phone') ?></dt><dd class="col-7"><?= esc($customer['cl_phone'] ?? '-') ?></dd>
                    <dt class="col-5 text-body-secondary fw-normal"><?= lang('Customers.paymentTerms') ?></dt><dd class="col-7"><?= $term ? esc($term['code']) . ' <span class="text-body-secondary">(' . esc(lang('Customers.days', [$term['due_days']])) . ')</span>' : '-' ?></dd>
                    <dt class="col-5 text-body-secondary fw-normal"><?= lang('Customers.creditLimit') ?></dt><dd class="col-7"><?= $customer['credit_limit'] !== null ? esc(format_idr($customer['credit_limit'])) : esc(lang('Customers.noLimit')) ?></dd>
                    <dt class="col-5 text-body-secondary fw-normal"><?= lang('App.createdAt') ?></dt><dd class="col-7"><?= esc(format_date($customer['created_at'], true)) ?></dd>
                    <dt class="col-5 text-body-secondary fw-normal"><?= lang('Customers.customerId') ?></dt><dd class="col-7 mb-0 font-monospace text-break" style="font-size: .75rem"><?= esc($customer['cl_id']) ?></dd>
                </dl>
                <hr>
                <div class="text-body-secondary small mb-1"><?= lang('Customers.salesRepsLong') ?></div>
                <?php foreach ($reps as $r) : ?>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <i class="bi bi-person-badge text-primary"></i>
                        <span><?= esc(up($r['rep_name'])) ?></span>
                        <?php if (! empty($r['rep_phone'])) : ?><span class="small text-body-secondary"><?= esc($r['rep_phone']) ?></span><?php endif ?>
                        <?php if (! $r['is_active']) : ?><span class="badge bg-secondary-subtle text-secondary-emphasis"><?= lang('App.inactive') ?></span><?php endif ?>
                    </div>
                <?php endforeach ?>
                <?php if ($reps === []) : ?><div class="small text-body-secondary"><?= lang('Customers.noneAssigned') ?></div><?php endif ?>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-8">
        <div class="card shadow-sm mb-3" id="contacts">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><?= lang('Customers.contactPersons') ?></span>
                <?php if ($canContacts) : ?>
                    <a href="<?= site_url("customers/{$cid}/contacts/new") ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-plus-lg me-1"></i><?= lang('Customers.addContact') ?></a>
                <?php endif ?>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th class="ps-3"><?= lang('Customers.name') ?></th><th><?= lang('Contacts.role') ?></th><th><?= lang('Customers.phone') ?></th><th><?= lang('Customers.email') ?></th><?php if ($canContacts) : ?><th></th><?php endif ?></tr></thead>
                    <tbody>
                        <?php foreach ($contacts as $p) : ?>
                            <?php $wa = $p['cp_whatsapp'] ? ContactPersonModel::whatsappUrl($p['ccp_phone_number']) : null ?>
                            <tr class="<?= $p['ended_on'] ? 'text-body-tertiary' : '' ?>">
                                <td class="ps-3">
                                    <a href="<?= site_url('contacts/' . $p['contact_id']) ?>" class="<?= $p['ended_on'] ? 'text-reset' : '' ?>"><?= esc(ContactPersonModel::displayName($p)) ?></a>
                                    <?php if ($p['is_primary']) : ?><span class="badge bg-primary ms-1"><?= lang('Customers.primary') ?></span><?php endif ?>
                                    <?php if ($p['ended_on']) : ?><span class="badge bg-secondary-subtle text-secondary-emphasis border ms-1"><?= esc(lang('Contacts.leftOnShort', [format_date($p['ended_on'])])) ?></span><?php endif ?>
                                </td>
                                <td class="small"><?= esc(CustomerContactModel::roleLabel($p)) ?></td>
                                <td class="small text-nowrap">
                                    <?php if ($p['ccp_phone_number']) : ?>
                                        <a href="tel:<?= esc(preg_replace('/[^0-9+]/', '', (string) $p['ccp_phone_number']), 'attr') ?>" class="text-reset"><?= esc($p['ccp_phone_number']) ?></a>
                                        <?php if ($wa !== null) : ?><a href="<?= esc($wa, 'attr') ?>" target="_blank" rel="noopener" class="ms-1 text-success" title="<?= esc(lang('Contacts.chatWhatsapp'), 'attr') ?>"><i class="bi bi-whatsapp"></i></a><?php endif ?>
                                    <?php endif ?>
                                </td>
                                <td class="small text-break"><?= $p['ccp_email'] ? '<a href="mailto:' . esc($p['ccp_email'], 'attr') . '" class="text-reset">' . esc($p['ccp_email']) . '</a>' : '' ?></td>
                                <?php if ($canContacts) : ?>
                                    <td class="text-end text-nowrap pe-3">
                                        <a href="<?= site_url("customers/{$cid}/contacts/{$p['id']}/edit") ?>" class="btn btn-link btn-sm p-1" title="<?= esc(lang('App.edit'), 'attr') ?>"><i class="bi bi-pencil"></i></a>
                                        <?= view('components/delete_button', [
                                            'action'  => site_url("customers/{$cid}/contacts/{$p['id']}/delete"),
                                            'label'   => '',
                                            'icon'    => 'x-lg',
                                            'class'   => 'btn btn-link btn-sm p-1 text-danger',
                                            'confirm' => lang('Contacts.removeConfirm', [ContactPersonModel::displayName($p)]),
                                        ]) ?>
                                    </td>
                                <?php endif ?>
                            </tr>
                        <?php endforeach ?>
                        <?php if ($contacts === []) : ?>
                            <tr><td colspan="5" class="text-center text-body-secondary py-3 small"><?= lang('Customers.noContacts') ?></td></tr>
                        <?php endif ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($activityPanel !== null) : ?>
            <?= view('components/activity_panel', ['panel' => $activityPanel], ['saveData' => false]) ?>
        <?php endif ?>

        <div class="card shadow-sm" id="addresses">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><?= lang('Customers.addresses') ?></span>
                <?php if ($canManage) : ?>
                    <div class="d-flex gap-2">
                        <a href="<?= site_url("customers/{$cid}/addresses/new?type=BILLING") ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-plus-lg me-1"></i><?= esc(code_label('addressType', 'BILLING')) ?></a>
                        <a href="<?= site_url("customers/{$cid}/addresses/new?type=SHIPPING") ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-plus-lg me-1"></i><?= esc(code_label('addressType', 'SHIPPING')) ?></a>
                    </div>
                <?php endif ?>
            </div>
            <div class="list-group list-group-flush">
                <?php foreach ($addresses as $a) : ?>
                    <div class="list-group-item d-flex justify-content-between align-items-start gap-2">
                        <div>
                            <span class="badge <?= $a['address_type'] === 'BILLING' ? 'bg-info-subtle text-info-emphasis' : 'bg-success-subtle text-success-emphasis' ?> me-1"><?= esc(code_label('addressType', $a['address_type'])) ?></span>
                            <?php if ($a['is_default']) : ?><span class="badge bg-primary"><?= lang('Customers.default') ?></span><?php endif ?>
                            <div class="mt-1"><?= nl2br(esc(up(CustomerAddressModel::format($a)))) ?></div>
                        </div>
                        <?php if ($canManage) : ?>
                            <div class="text-nowrap">
                                <a href="<?= site_url("customers/{$cid}/addresses/{$a['id']}/edit") ?>" class="btn btn-link btn-sm p-1" title="<?= esc(lang('App.edit'), 'attr') ?>"><i class="bi bi-pencil"></i></a>
                                <?= view('components/delete_button', [
                                    'action'  => site_url("customers/{$cid}/addresses/{$a['id']}/delete"),
                                    'label'   => '',
                                    'icon'    => 'trash',
                                    'class'   => 'btn btn-link btn-sm p-1 text-danger',
                                    'confirm' => lang('Customers.deleteAddressConfirm'),
                                ]) ?>
                            </div>
                        <?php endif ?>
                    </div>
                <?php endforeach ?>
                <?php if ($addresses === []) : ?>
                    <div class="list-group-item text-center text-body-secondary py-3 small"><?= lang('Customers.noAddresses') ?></div>
                <?php endif ?>
            </div>
        </div>

        <?php if ($proposals !== null) : ?>
            <div class="card shadow-sm mt-3" id="proposals">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-semibold"><?= lang('Customers.recentProposals') ?></span>
                    <div class="d-flex gap-2">
                        <a href="<?= site_url("proposals?customer={$cid}") ?>" class="btn btn-link btn-sm"><?= lang('App.all') ?></a>
                        <?php if (user_can('proposals.manage') && $customer['is_active']) : ?>
                            <a href="<?= site_url("proposals/new?customer={$cid}") ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-plus-lg me-1"></i><?= lang('Proposals.newProposal') ?></a>
                        <?php endif ?>
                    </div>
                </div>
                <div class="list-group list-group-flush">
                    <?php foreach ($proposals as $p) : ?>
                        <a href="<?= site_url('proposals/' . $p['id']) ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center gap-2">
                            <div class="text-truncate">
                                <span class="font-monospace small fw-semibold"><?= esc($p['cpl_id']) ?></span>
                                <span class="small ms-1"><?= esc(up($p['cpl_proposal_title'])) ?></span>
                            </div>
                            <div class="text-nowrap small">
                                <span class="me-2"><?= esc(format_idr($p['grand_total'])) ?></span><?= proposal_status_badge($p['status_code']) ?>
                            </div>
                        </a>
                    <?php endforeach ?>
                    <?php if ($proposals === []) : ?>
                        <div class="list-group-item text-center text-body-secondary py-3 small"><?= lang('Proposals.noneYet') ?></div>
                    <?php endif ?>
                </div>
            </div>
        <?php endif ?>

        <?php if ($invoices !== null) : ?>
            <div class="card shadow-sm mt-3" id="invoices">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-semibold"><?= lang('Customers.recentInvoices') ?></span>
                    <a href="<?= site_url("invoices?customer={$cid}") ?>" class="btn btn-link btn-sm"><?= lang('App.all') ?></a>
                </div>
                <div class="list-group list-group-flush">
                    <?php foreach ($invoices as $inv) : ?>
                        <?php $payable = in_array($inv['status_code'], \App\Services\InvoiceWorkflow::PAYABLE, true) ?>
                        <a href="<?= site_url('invoices/' . $inv['id']) ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center gap-2">
                            <div class="text-truncate">
                                <span class="font-monospace small fw-semibold"><?= esc(InvoiceModel::displayNumber($inv)) ?></span>
                                <?php if ($payable) : ?><span class="small text-body-secondary ms-1"><?= lang('Invoices.dueOn', [format_date($inv['due_date'])]) ?></span><?php endif ?>
                            </div>
                            <div class="text-nowrap small">
                                <span class="me-2"><?= esc(format_idr($payable ? InvoiceModel::balance($inv) : $inv['grand_total'])) ?></span><?= invoice_status_badge($inv['status_code']) ?>
                            </div>
                        </a>
                    <?php endforeach ?>
                    <?php if ($invoices === []) : ?>
                        <div class="list-group-item text-center text-body-secondary py-3 small"><?= lang('Invoices.noneYet') ?></div>
                    <?php endif ?>
                </div>
            </div>
        <?php endif ?>
    </div>
</div>

<?= $this->endSection() ?>
