<?php
/**
 * Draft invoice form (header + items). Customer and proposal are fixed.
 *
 * @var array<string, mixed>        $invoice   Invoice with details (customer_name, proposal_number, ...).
 * @var list<array<string, mixed>>  $items     Item rows for the item table script.
 * @var array<int, string>          $terms     Payment terms options.
 * @var array<int, string>          $methods   Payment method options.
 * @var array<int, string>          $shipments Shipment options.
 * @var array<int, string>          $banks     Bank account options.
 * @var array<int, string>          $funding   Funding source options.
 * @var array<int, string>          $reps      Sales rep options.
 */

use App\Models\CustomerModel;

$iid    = (int) $invoice['id'];
$back   = site_url("invoices/{$iid}");
$errors = form_errors();

/**
 * <option> list with the current value selected.
 *
 * @param array<int, string> $options id => label
 * @param string             $field   Field name.
 */
$options = static function (array $options, string $field) use ($invoice): string {
    $current = form_value($field, $invoice);
    $html    = '';

    foreach ($options as $id => $label) {
        $html .= '<option value="' . $id . '"' . ($current === (string) $id ? ' selected' : '') . '>' . esc($field === 'sales_rep_id' ? up($label) : $label) . '</option>';
    }

    return $html;
};
?>
<?= $this->extend('layouts/app_layout') ?>

<?= $this->section('title') ?><?= lang('Invoices.editDraftNo', [$iid]) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('components/page_header', [
    'title'      => lang('Invoices.editDraftNo', [$iid]),
    'subtitle'   => up($invoice['customer_name']) . ($invoice['proposal_number'] ? ' · ' . lang('Invoices.fromProposal', [$invoice['proposal_number']]) : ''),
    'breadcrumb' => [[lang('Invoices.invoices'), site_url('invoices')], [lang('Invoices.draftNo', [$iid]), $back], [lang('App.edit'), '']],
]) ?>

<form action="<?= $back ?>" method="post" novalidate id="invoice-form" data-products-url="<?= site_url('api/products') ?>">
    <?= csrf_field() ?>

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold"><?= esc(lang('Invoices.customerBilling')) ?></div>
        <div class="card-body row g-3">
            <div class="col-12 col-lg-6">
                <label class="form-label"><?= lang('App.customer') ?></label>
                <div class="form-control-plaintext pt-0">
                    <?= customer_link($invoice['customer_id'], $invoice['customer_name'], 'fw-semibold') ?>
                    <?php if ($invoice['school_status']) : ?><div class="small text-body-secondary"><?= esc(code_label('schoolStatus', $invoice['school_status'])) ?><?= $invoice['npsn'] ? ' · NPSN ' . esc($invoice['npsn']) : '' ?></div><?php endif ?>
                </div>
                <label for="billing_address" class="form-label"><?= lang('Invoices.billingAddress') ?> <span class="text-danger">*</span></label>
                <textarea class="form-control text-uppercase<?= invalid('billing_address') ?>" id="billing_address" name="billing_address" rows="3" maxlength="1000"
                          placeholder="<?= esc(lang('Invoices.billingHint'), 'attr') ?>"><?= esc(form_value('billing_address', $invoice)) ?></textarea>
                <?= field_error('billing_address') ?>
                <div class="form-text"><?= lang('Invoices.billingHelp') ?></div>
            </div>
            <div class="col-12 col-lg-6 row g-3 m-0 p-0 align-content-start">
                <div class="col-12 col-md-6">
                    <label for="customer_tax_id" class="form-label"><?= lang('Invoices.customerNpwp') ?></label>
                    <input type="text" class="form-control<?= invalid('customer_tax_id') ?>" id="customer_tax_id" name="customer_tax_id" maxlength="32"
                           value="<?= esc(form_value('customer_tax_id', $invoice)) ?>">
                    <?= field_error('customer_tax_id') ?>
                </div>
                <div class="col-12 col-md-6">
                    <label for="funding_source_id" class="form-label"><?= lang('Proposals.fundingSource') ?></label>
                    <select class="form-select<?= invalid('funding_source_id') ?>" id="funding_source_id" name="funding_source_id">
                        <option value=""><?= lang('Invoices.notKnown') ?></option>
                        <?= $options($funding, 'funding_source_id') ?>
                    </select>
                    <?= field_error('funding_source_id') ?>
                </div>
                <div class="col-12 col-md-6">
                    <label for="sales_rep_id" class="form-label"><?= lang('Proposals.salesRep') ?></label>
                    <select class="form-select<?= invalid('sales_rep_id') ?>" id="sales_rep_id" name="sales_rep_id">
                        <option value=""><?= lang('Proposals.noneOption') ?></option>
                        <?= $options($reps, 'sales_rep_id') ?>
                    </select>
                    <?= field_error('sales_rep_id') ?>
                </div>
                <div class="col-12 col-md-6">
                    <label for="payment_term_id" class="form-label"><?= lang('Proposals.paymentTerms') ?> <span class="text-danger">*</span></label>
                    <select class="form-select<?= invalid('payment_term_id') ?>" id="payment_term_id" name="payment_term_id" required>
                        <?= $options($terms, 'payment_term_id') ?>
                    </select>
                    <?= field_error('payment_term_id') ?>
                </div>
                <div class="col-12">
                    <div class="form-text mt-0"><i class="bi bi-info-circle me-1"></i><?= lang('Invoices.withholdingInfo') ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold"><?= esc(lang('Invoices.paymentDelivery')) ?></div>
        <div class="card-body row g-3">
            <div class="col-12 col-md-4">
                <label for="payment_method_id" class="form-label"><?= lang('Invoices.paymentMethod') ?></label>
                <select class="form-select<?= invalid('payment_method_id') ?>" id="payment_method_id" name="payment_method_id">
                    <option value=""><?= lang('Proposals.notSpecified') ?></option>
                    <?= $options($methods, 'payment_method_id') ?>
                </select>
                <?= field_error('payment_method_id') ?>
            </div>
            <div class="col-12 col-md-8">
                <label for="bank_account_id" class="form-label"><?= lang('Invoices.bankForTransfer') ?></label>
                <select class="form-select<?= invalid('bank_account_id') ?>" id="bank_account_id" name="bank_account_id">
                    <option value=""><?= lang('Proposals.noneOption') ?></option>
                    <?= $options($banks, 'bank_account_id') ?>
                </select>
                <?= field_error('bank_account_id') ?>
                <?php if ($banks === []) : ?><div class="form-text"><?= lang('Invoices.noBanks') ?></div><?php endif ?>
            </div>
            <div class="col-12 col-md-4">
                <label for="shipment_id" class="form-label"><?= lang('Invoices.shipment') ?></label>
                <select class="form-select<?= invalid('shipment_id') ?>" id="shipment_id" name="shipment_id">
                    <option value=""><?= lang('Proposals.notSpecified') ?></option>
                    <?= $options($shipments, 'shipment_id') ?>
                </select>
                <?= field_error('shipment_id') ?>
            </div>
            <div class="col-12 col-md-4">
                <label for="shipping_cost" class="form-label"><?= lang('Invoices.shippingCost') ?></label>
                <div class="input-group">
                    <span class="input-group-text">Rp</span>
                    <input type="number" class="form-control text-end<?= invalid('shipping_cost') ?>" id="shipping_cost" name="shipping_cost" min="0" step="any"
                           value="<?= esc(form_value('shipping_cost', $invoice)) ?>">
                    <?= field_error('shipping_cost') ?>
                </div>
                <div class="form-text"><?= lang('Invoices.shippingHelp') ?></div>
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
                        <i class="bi bi-box-seam fs-4 d-block mb-1"></i><?= lang('Invoices.noItems') ?>
                    </td></tr>
                </tbody>
                <tfoot class="border-top">
                    <tr><td colspan="7" class="text-end text-body-secondary small border-0 pb-0"><?= lang('App.subtotal') ?></td><td class="text-end text-nowrap border-0 pb-0" data-total="subtotal">Rp 0,00</td><td class="border-0"></td></tr>
                    <tr><td colspan="7" class="text-end text-body-secondary small border-0 py-0"><?= lang('Proposals.inclDiscounts') ?></td><td class="text-end text-nowrap small text-body-secondary border-0 py-0" data-total="discount_total">Rp 0,00</td><td class="border-0"></td></tr>
                    <tr><td colspan="7" class="text-end text-body-secondary small border-0 py-0"><?= lang('Proposals.taxPpn') ?></td><td class="text-end text-nowrap border-0 py-0" data-total="tax_total">Rp 0,00</td><td class="border-0"></td></tr>
                    <tr><td colspan="7" class="text-end text-body-secondary small border-0 pt-0"><?= lang('Invoices.shipping') ?></td><td class="text-end text-nowrap border-0 pt-0" data-total="shipping_cost">Rp 0,00</td><td class="border-0"></td></tr>
                    <tr class="table-light"><td colspan="7" class="text-end fw-semibold"><?= lang('App.grandTotal') ?></td><td class="text-end text-nowrap fw-bold fs-6" data-total="grand_total">Rp 0,00</td><td></td></tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <label for="notes" class="form-label"><?= lang('Invoices.notes') ?></label>
            <textarea class="form-control<?= invalid('notes') ?>" id="notes" name="notes" rows="3" maxlength="5000"
                      placeholder="<?= esc(lang('Invoices.notesHint'), 'attr') ?>"><?= esc(form_value('notes', $invoice)) ?></textarea>
            <?= field_error('notes') ?>
        </div>
    </div>

    <div class="d-flex gap-2 mb-4">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i><?= lang('Invoices.saveDraft') ?></button>
        <a href="<?= $back ?>" class="btn btn-outline-secondary"><?= lang('App.cancel') ?></a>
    </div>
</form>

<?= view('components/item_row_template') ?>

<script type="application/json" id="item-data"><?= json_encode($items, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

<?= $this->endSection() ?>

<?= $this->section('pageScripts') ?>
<script src="<?= asset_url('assets/js/invoice-form.js') ?>"></script>
<?= $this->endSection() ?>
