<?php
/**
 * Record a payment: cash received + taxes the treasurer withheld (with proof numbers).
 *
 * @var array<string, mixed>   $invoice    Invoice with details.
 * @var array{amount: string, withholdings: list<array<string, mixed>>, extra?: array<string, mixed>} $defaults
 * @var array<int, string>     $methods
 * @var array<int, string>     $banks
 * @var array<string, string>  $kinds      tax_kind => label
 * @var int|null               $transferId Payment method id of TRANSFER.
 */

use App\Models\InvoiceModel;

$iid     = (int) $invoice['id'];
$number  = InvoiceModel::displayNumber($invoice);
$balance = InvoiceModel::balance($invoice);
$record  = [
    'payment_date'      => date('Y-m-d'),
    'amount'            => $defaults['amount'],
    'payment_method_id' => $invoice['payment_method_id'] ?? $transferId,
    'bank_account_id'   => $invoice['bank_account_id'],
];
$extra   = $defaults['extra'] ?? [];
$errors  = form_errors();
$whErrors = array_filter($errors, static fn ($k) => str_starts_with((string) $k, 'withholdings.'), ARRAY_FILTER_USE_KEY);
?>
<?= $this->extend('layouts/app_layout') ?>

<?= $this->section('title') ?><?= lang('Payments.recordPayment') ?> · <?= esc($number) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('components/page_header', [
    'title'      => lang('Payments.recordPayment'),
    'subtitle'   => lang('Payments.invoiceCustomer', [$number, up($invoice['customer_name'])]),
    'breadcrumb' => [[lang('Invoices.invoices'), site_url('invoices')], [$number, site_url("invoices/{$iid}")], [lang('Payments.recordPayment'), '']],
]) ?>

<form action="<?= site_url("invoices/{$iid}/payments") ?>" method="post" novalidate id="payment-form" data-balance="<?= esc($balance, 'attr') ?>">
    <?= csrf_field() ?>

    <div class="row g-3">
        <div class="col-12 col-xl-8">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold"><?= lang('Payments.cashReceived') ?></div>
                <div class="card-body row g-3">
                    <div class="col-12 col-md-4">
                        <label for="payment_date" class="form-label"><?= lang('Payments.paymentDate') ?> <span class="text-danger">*</span></label>
                        <input type="date" class="form-control<?= invalid('payment_date') ?>" id="payment_date" name="payment_date" required
                               max="<?= date('Y-m-d') ?>" min="<?= esc($invoice['issue_date']) ?>" value="<?= esc(form_value('payment_date', $record)) ?>">
                        <?= field_error('payment_date') ?>
                    </div>
                    <div class="col-12 col-md-4">
                        <label for="amount" class="form-label"><?= lang('Payments.amountReceived') ?> <span class="text-danger">*</span></label>
                        <div class="input-group has-validation">
                            <span class="input-group-text">Rp</span>
                            <input type="number" class="form-control text-end<?= invalid('amount') ?>" id="amount" name="amount" min="0.01" step="any" required
                                   value="<?= esc(form_value('amount', $record)) ?>">
                            <?= field_error('amount') ?>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <label for="payment_method_id" class="form-label"><?= lang('Payments.method') ?> <span class="text-danger">*</span></label>
                        <select class="form-select<?= invalid('payment_method_id') ?>" id="payment_method_id" name="payment_method_id" required>
                            <?php $current = form_value('payment_method_id', $record) ?>
                            <?php foreach ($methods as $id => $name) : ?>
                                <option value="<?= $id ?>"<?= $current === (string) $id ? ' selected' : '' ?>><?= esc($name) ?></option>
                            <?php endforeach ?>
                        </select>
                        <?= field_error('payment_method_id') ?>
                    </div>
                    <div class="col-12 col-md-8">
                        <label for="bank_account_id" class="form-label"><?= lang('Payments.receivedOn') ?></label>
                        <select class="form-select<?= invalid('bank_account_id') ?>" id="bank_account_id" name="bank_account_id">
                            <option value=""><?= lang('Payments.noneCash') ?></option>
                            <?php $current = form_value('bank_account_id', $record) ?>
                            <?php foreach ($banks as $id => $label) : ?>
                                <option value="<?= $id ?>"<?= $current === (string) $id ? ' selected' : '' ?>><?= esc($label) ?></option>
                            <?php endforeach ?>
                        </select>
                        <?= field_error('bank_account_id') ?>
                    </div>
                    <div class="col-12 col-md-4">
                        <label for="reference_number" class="form-label"><?= lang('Payments.reference') ?></label>
                        <input type="text" class="form-control<?= invalid('reference_number') ?>" id="reference_number" name="reference_number" maxlength="64"
                               placeholder="<?= esc(lang('Payments.referenceHint'), 'attr') ?>" value="<?= esc(form_value('reference_number')) ?>">
                        <?= field_error('reference_number') ?>
                    </div>
                    <div class="col-12">
                        <label for="note" class="form-label"><?= lang('App.note') ?></label>
                        <input type="text" class="form-control<?= invalid('note') ?>" id="note" name="note" maxlength="2000" value="<?= esc(form_value('note')) ?>">
                        <?= field_error('note') ?>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold"><?= lang('Payments.taxesWithheld') ?></div>
                <?php if ($whErrors !== []) : ?>
                    <div class="alert alert-danger small rounded-0 mb-0 py-2">
                        <?php foreach ($whErrors as $message) : ?><div><?= esc($message) ?></div><?php endforeach ?>
                    </div>
                <?php endif ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th class="ps-3" style="min-width: 230px"><?= lang('App.tax') ?></th>
                                <th class="text-end"><?= lang('Payments.expectedWithheld') ?></th>
                                <th class="text-end" style="width: 170px"><?= lang('Payments.withheldNow') ?></th>
                                <th style="width: 190px"><?= lang('Payments.proofNumber') ?></th>
                                <th class="pe-3" style="width: 160px"><?= lang('Payments.proofDate') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($defaults['withholdings'] as $i => $w) : ?>
                                <tr>
                                    <td class="ps-3">
                                        <input type="hidden" name="withholdings[<?= $i ?>][invoice_withholding_id]" value="<?= (int) $w['invoice_withholding_id'] ?>">
                                        <div class="small fw-semibold"><?= esc(code_label('taxKind', $w['tax_kind'])) ?></div>
                                        <div class="small text-body-secondary"><?= esc($w['description']) ?></div>
                                    </td>
                                    <td class="text-end small text-nowrap"><?= esc(format_idr($w['expected'])) ?><div class="text-body-secondary"><?= esc(lang('Payments.soFar', [format_idr($w['withheld'])])) ?></div></td>
                                    <td><input type="number" class="form-control form-control-sm text-end" name="withholdings[<?= $i ?>][amount]" min="0" step="any" data-withheld
                                               value="<?= esc((string) $w['amount']) ?>" aria-label="<?= esc(lang('Payments.withheldAmount'), 'attr') ?>"></td>
                                    <td><input type="text" class="form-control form-control-sm" name="withholdings[<?= $i ?>][document_number]" maxlength="64"
                                               value="<?= esc((string) $w['document_number']) ?>" aria-label="<?= esc(lang('Payments.proofNumber'), 'attr') ?>"></td>
                                    <td class="pe-3"><input type="date" class="form-control form-control-sm" name="withholdings[<?= $i ?>][document_date]"
                                               value="<?= esc((string) $w['document_date']) ?>" aria-label="<?= esc(lang('Payments.proofDate'), 'attr') ?>"></td>
                                </tr>
                            <?php endforeach ?>
                            <tr>
                                <td class="ps-3">
                                    <select class="form-select form-select-sm" name="withholdings[extra][tax_kind]" aria-label="<?= esc(lang('Payments.otherTax'), 'attr') ?>">
                                        <option value=""><?= lang('Payments.otherTax') ?></option>
                                        <?php foreach (array_keys($kinds) as $code) : ?>
                                            <option value="<?= $code ?>"<?= ($extra['tax_kind'] ?? '') === $code ? ' selected' : '' ?>><?= esc(code_label('taxKind', $code)) ?></option>
                                        <?php endforeach ?>
                                    </select>
                                </td>
                                <td class="text-end small text-body-secondary">-</td>
                                <td><input type="number" class="form-control form-control-sm text-end" name="withholdings[extra][amount]" min="0" step="any" data-withheld
                                           value="<?= esc((string) ($extra['amount'] ?? '')) ?>" aria-label="<?= esc(lang('Payments.withheldAmount'), 'attr') ?>"></td>
                                <td><input type="text" class="form-control form-control-sm" name="withholdings[extra][document_number]" maxlength="64"
                                           value="<?= esc((string) ($extra['document_number'] ?? '')) ?>" aria-label="<?= esc(lang('Payments.proofNumber'), 'attr') ?>"></td>
                                <td class="pe-3"><input type="date" class="form-control form-control-sm" name="withholdings[extra][document_date]"
                                           value="<?= esc((string) ($extra['document_date'] ?? '')) ?>" aria-label="<?= esc(lang('Payments.proofDate'), 'attr') ?>"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-white small text-body-secondary"><?= lang('Payments.whFooter') ?></div>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold"><?= lang('Payments.summary') ?></div>
                <div class="card-body small">
                    <div class="d-flex justify-content-between"><span><?= lang('Invoices.invoiceTotal') ?></span><span><?= esc(format_idr($invoice['grand_total'])) ?></span></div>
                    <div class="d-flex justify-content-between"><span><?= lang('Invoices.openBalance') ?></span><strong><?= esc(format_idr($balance)) ?></strong></div>
                    <hr class="my-2">
                    <div class="d-flex justify-content-between"><span><?= lang('Payments.cashReceived') ?></span><span data-sum="cash">-</span></div>
                    <div class="d-flex justify-content-between"><span><?= lang('Invoices.taxesWithheld') ?></span><span data-sum="withheld">-</span></div>
                    <hr class="my-2">
                    <div class="d-flex justify-content-between fw-semibold fs-6"><span><?= lang('Payments.balanceAfter') ?></span><span data-sum="after">-</span></div>
                    <div class="mt-2" data-over<?= old('accept_overpayment') === '1' ? '' : ' hidden' ?>>
                        <div class="text-danger"><i class="bi bi-exclamation-triangle me-1"></i><?= lang('Payments.moreThanOpen') ?></div>
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" id="accept_overpayment" name="accept_overpayment" value="1"<?= old('accept_overpayment') === '1' ? ' checked' : '' ?>>
                            <label class="form-check-label" for="accept_overpayment"><?= lang('Payments.acceptOver') ?></label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="d-flex gap-2 mb-4">
                <button type="submit" class="btn btn-success"><i class="bi bi-check-lg me-1"></i><?= lang('Payments.recordPayment') ?></button>
                <a href="<?= site_url("invoices/{$iid}") ?>" class="btn btn-outline-secondary"><?= lang('App.cancel') ?></a>
            </div>
        </div>
    </div>
</form>

<?= $this->endSection() ?>

<?= $this->section('pageScripts') ?>
<script src="<?= base_url('assets/js/payment-form.js') ?>"></script>
<?= $this->endSection() ?>
