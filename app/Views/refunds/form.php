<?php
/**
 * Record a refund (money paid back to the customer) from an invoice's credit balance.
 *
 * @var array<string, mixed> $invoice    Invoice with details.
 * @var string               $refundable Max. cash that can be refunded.
 * @var array<int, string>   $methods
 * @var array<int, string>   $banks
 */

use App\Models\InvoiceModel;

$iid    = (int) $invoice['id'];
$number = InvoiceModel::displayNumber($invoice);
$record = [
    'refund_date'       => date('Y-m-d'),
    'amount'            => $refundable,
    'payment_method_id' => $invoice['payment_method_id'],
    'bank_account_id'   => $invoice['bank_account_id'],
];
$toReclaim = InvoiceModel::taxToReclaim($invoice);
?>
<?= $this->extend('layouts/app_layout') ?>

<?= $this->section('title') ?><?= lang('Payments.refund') ?> · <?= esc($number) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('components/page_header', [
    'title'      => lang('Payments.recordRefund'),
    'subtitle'   => lang('Payments.invoiceCustomer', [$number, up($invoice['customer_name'])]),
    'breadcrumb' => [[lang('Invoices.invoices'), site_url('invoices')], [$number, site_url("invoices/{$iid}")], [lang('Payments.refund'), '']],
]) ?>

<div class="alert alert-warning small">
    <i class="bi bi-arrow-counterclockwise me-1"></i><?= lang('Payments.refundInfo', [esc(format_idr($refundable))]) ?>
    <?php if (bccomp($toReclaim, '0', 2) > 0) : ?>
        <?= esc(lang('Payments.refundTaxInfo', [format_idr($toReclaim)])) ?>
    <?php endif ?>
</div>

<div class="card shadow-sm" style="max-width: 760px">
    <div class="card-body">
        <form action="<?= site_url("invoices/{$iid}/refunds") ?>" method="post" class="row g-3" novalidate>
            <?= csrf_field() ?>
            <div class="col-12 col-md-4">
                <label for="refund_date" class="form-label"><?= lang('Payments.refundDate') ?> <span class="text-danger">*</span></label>
                <input type="date" class="form-control<?= invalid('refund_date') ?>" id="refund_date" name="refund_date" required max="<?= date('Y-m-d') ?>"
                       value="<?= esc(form_value('refund_date', $record)) ?>">
                <?= field_error('refund_date') ?>
            </div>
            <div class="col-12 col-md-4">
                <label for="amount" class="form-label"><?= lang('Payments.amountRefunded') ?> <span class="text-danger">*</span></label>
                <div class="input-group has-validation">
                    <span class="input-group-text">Rp</span>
                    <input type="number" class="form-control text-end<?= invalid('amount') ?>" id="amount" name="amount" min="0.01" max="<?= esc($refundable, 'attr') ?>" step="any" required
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
                <label for="bank_account_id" class="form-label"><?= lang('Payments.paidFrom') ?></label>
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
                <label for="reference_number" class="form-label"><?= lang('Payments.transferRef') ?></label>
                <input type="text" class="form-control<?= invalid('reference_number') ?>" id="reference_number" name="reference_number" maxlength="64"
                       value="<?= esc(form_value('reference_number')) ?>">
                <?= field_error('reference_number') ?>
            </div>
            <div class="col-12">
                <label for="reason" class="form-label"><?= lang('App.reason') ?> <span class="text-danger">*</span></label>
                <textarea class="form-control<?= invalid('reason') ?>" id="reason" name="reason" rows="2" maxlength="2000" required
                          placeholder="<?= esc(lang('Payments.refundReasonHint'), 'attr') ?>"><?= esc(form_value('reason')) ?></textarea>
                <?= field_error('reason') ?>
            </div>
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-warning"><i class="bi bi-check-lg me-1"></i><?= lang('Payments.recordRefund') ?></button>
                <a href="<?= site_url("invoices/{$iid}") ?>" class="btn btn-outline-secondary"><?= lang('App.cancel') ?></a>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>
