<?php
/**
 * @var array<string, mixed>        $proposal  Stored proposal, or defaults on create (no 'id').
 * @var array<string, mixed>|null   $customer  Selected customer.
 * @var list<array<string, mixed>>  $contacts  Contacts of the selected customer.
 * @var array<int, string>          $reps      Sales rep options.
 * @var array<int, string>          $funding   Funding source options.
 * @var array<int, string>          $terms     Payment terms options.
 * @var array<int, string>          $shipments Shipment method options.
 * @var list<array<string, mixed>>  $items     Item rows for the item table script.
 * @var array<int, string>          $levels    School level options (quick-add school).
 * @var array<string, list<array<string, string>>> $regions Province list for the quick-add school address.
 * @var array<string, mixed>|null   $negotiation Under review: customer request, last revision, next revision number.
 */

use App\Models\CustomerModel;

$isEdit  = isset($proposal['id']);
$title   = $isEdit ? lang('Proposals.editProposal', [$proposal['cpl_id']]) : lang('Proposals.newProposal');
$action  = $isEdit ? site_url('proposals/' . $proposal['id']) : site_url('proposals');
$back    = $isEdit ? site_url('proposals/' . $proposal['id']) : site_url('proposals');
$contact = form_value('contact_person_id', $proposal);
$rep     = form_value('sales_rep_id', $proposal);
$errors  = form_errors();
?>
<?= $this->extend('layouts/app_layout') ?>

<?= $this->section('title') ?><?= esc($title) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('components/page_header', [
    'title'      => $title,
    'subtitle'   => $isEdit ? up($proposal['customer_name']) : lang('Proposals.numberOnSave'),
    'breadcrumb' => array_merge(
        [[lang('Proposals.proposals'), site_url('proposals')]],
        $isEdit ? [[$proposal['cpl_id'], $back], [lang('App.edit'), '']] : [[lang('App.new'), '']],
    ),
]) ?>

<form action="<?= $action ?>" method="post" novalidate id="proposal-form"
      data-customers-url="<?= site_url('api/customers') ?>"
      data-products-url="<?= site_url('api/products') ?>">
    <?= csrf_field() ?>

    <?php if (! empty($negotiation)) : ?>
        <?php $req = $negotiation['request']; $rev = $negotiation['revision'] ?>
        <div class="card shadow-sm mb-3 border-warning-subtle negotiation-card">
            <div class="card-header bg-warning-subtle fw-semibold"><i class="bi bi-chat-left-text me-1"></i><?= lang('Proposals.negotiation') ?></div>
            <div class="card-body row g-3 small">
                <div class="col-12 col-lg-7">
                    <div class="text-body-secondary mb-1"><?= lang('Proposals.customerRequest') ?><?= $req !== null ? ' · ' . esc(format_date($req['created_at'], true)) . ' · ' . esc($req['full_name'] ? up($req['full_name']) : $req['username']) : '' ?></div>
                    <div class="fs-6"><?= $req !== null && ! empty($req['note']) ? nl2br(esc($req['note'])) : '<span class="text-body-secondary">' . esc(lang('Proposals.noRequest')) . '</span>' ?></div>
                </div>
                <div class="col-12 col-lg-5">
                    <?php if ($rev !== null) : ?>
                        <div class="text-body-secondary mb-1"><?= lang('Proposals.lastSent', [(int) $rev['revision_no'], format_date($rev['created_at'])]) ?></div>
                        <div class="fs-6 fw-semibold"><?= esc(format_idr($rev['grand_total'])) ?></div>
                        <div class="text-body-secondary"><?= lang('Proposals.changesShownAfterSave') ?></div>
                    <?php endif ?>
                </div>
                <div class="col-12">
                    <label for="revision_note" class="form-label mb-1"><?= lang('Proposals.whatChanged', [(int) $negotiation['next']]) ?> <span class="text-body-secondary"><?= lang('Proposals.whatChangedHelp') ?></span></label>
                    <input type="text" class="form-control form-control-sm" id="revision_note" name="revision_note" maxlength="2000"
                           value="<?= esc((string) old('revision_note')) ?>" placeholder="<?= esc(lang('Proposals.whatChangedHint'), 'attr') ?>">
                </div>
            </div>
        </div>
    <?php endif ?>

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold"><?= esc(lang('Proposals.customerAndProposal')) ?></div>
        <div class="card-body row g-3">
            <div class="col-12 col-lg-6">
                <label for="customer_search" class="form-label"><?= lang('App.customer') ?> <span class="text-danger">*</span></label>
                <input type="hidden" name="customer_id" id="customer_id" value="<?= esc((string) ($customer['id'] ?? '')) ?>">
                <div class="position-relative">
                    <div class="input-group has-validation">
                        <input type="text" class="form-control<?= invalid('customer_id') ?>" id="customer_search" autocomplete="off"
                               placeholder="<?= esc(lang('Proposals.customerSearchHint'), 'attr') ?>" <?= $isEdit ? '' : 'autofocus' ?>
                               value="<?= esc(up($customer['cl_organization_name'] ?? '')) ?>">
                        <?php if (! $isEdit) : ?>
                            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#quick-customer" title="<?= esc(lang('Proposals.addNewSchool'), 'attr') ?>">
                                <i class="bi bi-plus-lg"></i><span class="d-none d-sm-inline ms-1"><?= lang('Proposals.newSchool') ?></span>
                            </button>
                        <?php endif ?>
                        <?= field_error('customer_id') ?>
                    </div>
                </div>
                <div class="form-text" id="customer_info">
                    <?php if ($customer !== null && $customer['school_status']) : ?>
                        <?= esc(code_label('schoolStatus', $customer['school_status'])) ?><?= $customer['npsn'] ? ' · NPSN ' . esc($customer['npsn']) : '' ?>
                    <?php endif ?>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-3">
                <label for="contact_person_id" class="form-label"><?= lang('Proposals.contactPerson') ?></label>
                <select class="form-select<?= invalid('contact_person_id') ?>" id="contact_person_id" name="contact_person_id">
                    <option value=""><?= lang('Proposals.noneOption') ?></option>
                    <?php foreach ($contacts as $c) : ?>
                        <option value="<?= $c['id'] ?>"<?= $contact === (string) $c['id'] ? ' selected' : '' ?>>
                            <?= esc(up(trim($c['ccp_first_name'] . ' ' . ($c['ccp_last_name'] ?? '')))) ?><?php $roleText = \App\Models\CustomerContactModel::roleLabel($c) ?><?= $roleText !== '' ? ' (' . esc($roleText) . ')' : '' ?><?= ! empty($c['ended_on']) ? ' · ' . esc(lang('Contacts.left')) : '' ?>
                        </option>
                    <?php endforeach ?>
                </select>
                <?= field_error('contact_person_id') ?>
            </div>
            <div class="col-12 col-md-6 col-lg-3">
                <label for="sales_rep_id" class="form-label"><?= lang('Proposals.salesRep') ?></label>
                <select class="form-select<?= invalid('sales_rep_id') ?>" id="sales_rep_id" name="sales_rep_id">
                    <option value=""><?= lang('Proposals.noneOption') ?></option>
                    <?php foreach ($reps as $id => $name) : ?>
                        <option value="<?= $id ?>"<?= $rep === (string) $id ? ' selected' : '' ?>><?= esc(up($name)) ?></option>
                    <?php endforeach ?>
                </select>
                <?= field_error('sales_rep_id') ?>
            </div>
            <div class="col-12 col-lg-6">
                <label for="cpl_proposal_title" class="form-label"><?= lang('Proposals.title') ?> <span class="text-danger">*</span></label>
                <input type="text" class="form-control text-uppercase<?= invalid('cpl_proposal_title') ?>" id="cpl_proposal_title" name="cpl_proposal_title"
                       maxlength="200" required placeholder="<?= esc(lang('Proposals.titleHint'), 'attr') ?>"
                       value="<?= esc(form_value('cpl_proposal_title', $proposal)) ?>">
                <?= field_error('cpl_proposal_title') ?>
            </div>
            <div class="col-12 col-md-6 col-lg-3">
                <label for="funding_source_id" class="form-label"><?= lang('Proposals.fundingSource') ?></label>
                <select class="form-select<?= invalid('funding_source_id') ?>" id="funding_source_id" name="funding_source_id">
                    <option value=""><?= lang('Proposals.notKnownYet') ?></option>
                    <?php foreach ($funding as $id => $name) : ?>
                        <option value="<?= $id ?>"<?= form_value('funding_source_id', $proposal) === (string) $id ? ' selected' : '' ?>><?= esc($name) ?></option>
                    <?php endforeach ?>
                </select>
                <?= field_error('funding_source_id') ?>
            </div>
            <div class="col-12 col-md-6 col-lg-3">
                <label for="valid_until" class="form-label"><?= lang('Proposals.validUntil') ?></label>
                <input type="date" class="form-control<?= invalid('valid_until') ?>" id="valid_until" name="valid_until"
                       value="<?= esc(form_value('valid_until', $proposal)) ?>">
                <?= field_error('valid_until') ?>
            </div>
            <div class="col-12 col-md-4">
                <label for="payment_term_id" class="form-label"><?= lang('Proposals.paymentTerms') ?></label>
                <select class="form-select<?= invalid('payment_term_id') ?>" id="payment_term_id" name="payment_term_id">
                    <option value=""><?= lang('Proposals.customerDefault') ?></option>
                    <?php foreach ($terms as $id => $label) : ?>
                        <option value="<?= $id ?>"<?= form_value('payment_term_id', $proposal) === (string) $id ? ' selected' : '' ?>><?= esc($label) ?></option>
                    <?php endforeach ?>
                </select>
                <?= field_error('payment_term_id') ?>
            </div>
            <div class="col-12 col-md-4">
                <label for="shipment_id" class="form-label"><?= lang('Proposals.shipmentMethod') ?></label>
                <select class="form-select<?= invalid('shipment_id') ?>" id="shipment_id" name="shipment_id">
                    <option value=""><?= lang('Proposals.notSpecified') ?></option>
                    <?php foreach ($shipments as $id => $label) : ?>
                        <option value="<?= $id ?>"<?= form_value('shipment_id', $proposal) === (string) $id ? ' selected' : '' ?>><?= esc($label) ?></option>
                    <?php endforeach ?>
                </select>
                <?= field_error('shipment_id') ?>
            </div>
            <div class="col-12 col-md-4">
                <label for="delivery_date" class="form-label"><?= lang('Proposals.plannedDelivery') ?></label>
                <input type="date" class="form-control<?= invalid('delivery_date') ?>" id="delivery_date" name="delivery_date"
                       value="<?= esc(form_value('delivery_date', $proposal)) ?>">
                <?= field_error('delivery_date') ?>
            </div>
            <div class="col-12">
                <div class="form-text mt-0"><i class="bi bi-info-circle me-1"></i><?= lang('Proposals.fundingInfo') ?></div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span class="fw-semibold"><?= lang('Proposals.items') ?></span>
            <div class="position-relative flex-grow-1" style="max-width: 420px">
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" id="product_search" autocomplete="off" placeholder="<?= esc(lang('Proposals.productSearchHint'), 'attr') ?>">
                </div>
            </div>
        </div>
        <?php if (array_filter($errors, static fn ($k) => str_starts_with((string) $k, 'items.'), ARRAY_FILTER_USE_KEY) !== []) : ?>
            <div class="alert alert-danger small rounded-0 mb-0 py-2">
                <?php foreach ($errors as $key => $message) : ?>
                    <?php if (str_starts_with((string) $key, 'items.')) : ?><div><?= esc($message) ?></div><?php endif ?>
                <?php endforeach ?>
            </div>
        <?php endif ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0 item-table" id="item-table">
                <thead class="table-light small">
                    <tr>
                        <th style="width: 2.5rem">#</th>
                        <th style="min-width: 280px"><?= lang('App.title') ?> / <?= lang('App.description') ?></th>
                        <th class="text-end" style="width: 110px"><?= lang('App.quantity') ?></th>
                        <th style="width: 60px"><?= lang('App.unit') ?></th>
                        <th class="text-end" style="width: 165px"><?= lang('App.unitPrice') ?></th>
                        <th class="text-end" style="width: 140px"><?= lang('App.discount') ?></th>
                        <th style="width: 125px"><?= lang('App.tax') ?></th>
                        <th class="text-end" style="width: 150px"><?= lang('App.lineTotal') ?></th>
                        <th style="width: 2.5rem"></th>
                    </tr>
                </thead>
                <tbody></tbody>
                <tbody data-empty-row>
                    <tr><td colspan="9" class="text-center text-body-secondary py-4 small">
                        <i class="bi bi-box-seam fs-4 d-block mb-1"></i><?= lang('Proposals.noItemsYet') ?>
                    </td></tr>
                </tbody>
                <tfoot class="border-top">
                    <tr><td colspan="7" class="text-end text-body-secondary small border-0 pb-0"><?= lang('App.subtotal') ?></td><td class="text-end text-nowrap border-0 pb-0" data-total="subtotal">Rp 0,00</td><td class="border-0"></td></tr>
                    <tr><td colspan="7" class="text-end text-body-secondary small border-0 py-0"><?= lang('Proposals.inclDiscounts') ?></td><td class="text-end text-nowrap small text-body-secondary border-0 py-0" data-total="discount_total">Rp 0,00</td><td class="border-0"></td></tr>
                    <tr><td colspan="7" class="text-end text-body-secondary small border-0 pt-0"><?= lang('Proposals.taxPpn') ?></td><td class="text-end text-nowrap border-0 pt-0" data-total="tax_total">Rp 0,00</td><td class="border-0"></td></tr>
                    <tr class="table-light"><td colspan="7" class="text-end fw-semibold"><?= lang('App.grandTotal') ?></td><td class="text-end text-nowrap fw-bold fs-6" data-total="grand_total">Rp 0,00</td><td></td></tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <label for="cpl_note" class="form-label"><?= lang('Proposals.note') ?></label>
            <textarea class="form-control<?= invalid('cpl_note') ?>" id="cpl_note" name="cpl_note" rows="3" maxlength="5000"
                      placeholder="<?= esc(lang('Proposals.noteHint'), 'attr') ?>"><?= esc(form_value('cpl_note', $proposal)) ?></textarea>
            <?= field_error('cpl_note') ?>
        </div>
    </div>

    <div class="d-flex gap-2 mb-4">
        <?php if (! empty($negotiation)) : ?>
            <button type="submit" name="send" value="1" class="btn btn-primary"><i class="bi bi-send me-1"></i><?= esc(lang('Proposals.saveAndSend', [(int) $negotiation['next']])) ?></button>
            <button type="submit" class="btn btn-outline-primary"><i class="bi bi-check-lg me-1"></i><?= lang('Proposals.saveSendLater') ?></button>
        <?php else : ?>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i><?= $isEdit ? lang('App.saveChanges') : lang('Proposals.createProposal') ?></button>
        <?php endif ?>
        <a href="<?= $back ?>" class="btn btn-outline-secondary"><?= lang('App.cancel') ?></a>
    </div>
</form>

<?= view('components/item_row_template') ?>

<?php if (! $isEdit) : ?>
    <div class="modal fade" id="quick-customer" tabindex="-1" aria-labelledby="quick-customer-title" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <form class="modal-content" id="quick-customer-form" hx-boost="false" action="<?= site_url('api/customers/quick') ?>" method="post" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="quick-customer-title"><i class="bi bi-building-add me-1"></i><?= lang('Proposals.quickTitle') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= esc(lang('App.close'), 'attr') ?>"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-12 small text-body-secondary"><?= lang('Proposals.quickInfo') ?></div>
                    <div class="alert alert-danger small py-2 mb-0" data-errors hidden></div>
                    <div class="col-12 col-md-8">
                        <label for="qc-name" class="form-label"><?= lang('Proposals.schoolName') ?> <span class="text-danger">*</span></label>
                        <input type="text" class="form-control text-uppercase" id="qc-name" name="cl_organization_name" maxlength="200" required>
                    </div>
                    <div class="col-12 col-md-4">
                        <label for="qc-npsn" class="form-label">NPSN</label>
                        <input type="text" class="form-control" id="qc-npsn" name="npsn" maxlength="8" inputmode="numeric">
                    </div>
                    <div class="col-6 col-md-4">
                        <label for="qc-level" class="form-label"><?= lang('Proposals.level') ?></label>
                        <select class="form-select" id="qc-level" name="school_level_id">
                            <option value="">-</option>
                            <?php foreach ($levels as $id => $name) : ?><option value="<?= $id ?>"><?= esc($name) ?></option><?php endforeach ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-4">
                        <label for="qc-status" class="form-label"><?= lang('App.status') ?></label>
                        <select class="form-select" id="qc-status" name="school_status">
                            <option value=""><?= lang('Proposals.notASchool') ?></option>
                            <?php foreach (array_keys(CustomerModel::SCHOOL_STATUSES) as $code) : ?><option value="<?= $code ?>"><?= esc(code_label('schoolStatus', $code)) ?></option><?php endforeach ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-4">
                        <label for="qc-phone" class="form-label"><?= lang('Customers.phone') ?></label>
                        <input type="text" class="form-control" id="qc-phone" name="cl_phone" maxlength="32">
                    </div>
                    <div class="col-12">
                        <label for="qc-address" class="form-label"><?= lang('Customers.address') ?></label>
                        <input type="text" class="form-control text-uppercase" id="qc-address" name="address_line" maxlength="1000" placeholder="<?= esc(lang('Proposals.streetHint'), 'attr') ?>">
                    </div>
                    <?= view('components/region_selects', ['record' => [], 'regions' => $regions, 'colClass' => 'col-12 col-md-4', 'provinceField' => 'province', 'cityField' => 'city', 'districtField' => 'district']) ?>
                    <div class="col-12 col-md-5">
                        <label for="qc-contact" class="form-label"><?= lang('Proposals.contactPerson') ?></label>
                        <input type="text" class="form-control text-uppercase" id="qc-contact" name="contact_name" maxlength="128" placeholder="<?= esc(lang('Proposals.contactHint'), 'attr') ?>">
                    </div>
                    <div class="col-6 col-md-3">
                        <label for="qc-contact-phone" class="form-label"><?= lang('Proposals.contactPhone') ?></label>
                        <input type="text" class="form-control" id="qc-contact-phone" name="contact_phone" maxlength="32">
                    </div>
                    <div class="col-6 col-md-4">
                        <label for="qc-contact-position" class="form-label"><?= lang('Customers.position') ?></label>
                        <input type="text" class="form-control text-uppercase" id="qc-contact-position" name="contact_position" maxlength="64" placeholder="<?= esc(lang('Proposals.positionHint'), 'attr') ?>">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i><?= lang('Proposals.saveSchool') ?></button>
                </div>
            </form>
        </div>
    </div>
<?php endif ?>

<script type="application/json" id="item-data"><?= json_encode($items, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

<?= $this->endSection() ?>

<?= $this->section('pageScripts') ?>
<script src="<?= base_url('assets/js/proposal-form.js') ?>"></script>
<?= $this->endSection() ?>
