<?php
/**
 * New credit note for an invoice: type of correction, quantity (and optionally a lower unit price) per invoice line,
 * and shipping to credit. "Credit everything" fills all remaining amounts (cancellation of the order).
 *
 * @var array<string, mixed>       $invoice  Invoice with details.
 * @var list<array<string, mixed>> $lines    Invoice lines with credited_quantity, credited_amount, creditable_amount.
 * @var string                     $shipping Shipping cost not yet credited.
 */

use App\Models\CreditNoteModel;
use App\Models\InvoiceModel;

$iid        = (int) $invoice['id'];
$number     = InvoiceModel::displayNumber($invoice);
$balance    = InvoiceModel::balance($invoice);
$uncredited = bcsub((string) $invoice['grand_total'], (string) $invoice['credit_total'], 2);
$old        = old('lines', null, false);
$old        = is_array($old) ? $old : [];
$errors     = form_errors();
$lineErrors = array_filter($errors, static fn ($k) => str_starts_with((string) $k, 'lines'), ARRAY_FILTER_USE_KEY);
$reasonType = form_value('reason_type');
?>
<?= $this->extend('layouts/app_layout') ?>

<?= $this->section('title') ?><?= lang('CreditNotes.creditNote') ?> · <?= esc($number) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('components/page_header', [
    'title'      => lang('CreditNotes.newCreditNote'),
    'subtitle'   => lang('Payments.invoiceCustomer', [$number, up($invoice['customer_name'])]),
    'breadcrumb' => [[lang('Invoices.invoices'), site_url('invoices')], [$number, site_url("invoices/{$iid}")], [lang('CreditNotes.creditNote'), '']],
]) ?>

<div class="alert alert-info small"><i class="bi bi-info-circle me-1"></i>
    <?= lang('CreditNotes.info', [esc(format_idr(bccomp($balance, '0', 2) > 0 ? $balance : '0'))]) ?>
</div>

<form action="<?= site_url("invoices/{$iid}/credit-notes") ?>" method="post" novalidate id="credit-note-form" data-balance="<?= esc($uncredited, 'attr') ?>">
    <?= csrf_field() ?>

    <div class="card shadow-sm mb-3">
        <div class="card-body row g-3">
            <div class="col-12 col-md-5">
                <label for="reason_type" class="form-label"><?= lang('CreditNotes.typeOfCorrection') ?> <span class="text-danger">*</span></label>
                <select class="form-select<?= invalid('reason_type') ?>" id="reason_type" name="reason_type" required>
                    <option value=""><?= lang('App.select') ?></option>
                    <?php foreach (array_keys(CreditNoteModel::REASON_TYPES) as $code) : ?>
                        <option value="<?= $code ?>"<?= $reasonType === $code ? ' selected' : '' ?>><?= esc(code_label('reasonType', $code)) ?></option>
                    <?php endforeach ?>
                </select>
                <?= field_error('reason_type') ?>
            </div>
            <div class="col-12 col-md-7 d-flex align-items-end">
                <button type="button" class="btn btn-outline-danger" id="credit-all"><i class="bi bi-x-octagon me-1"></i><?= lang('CreditNotes.creditAll') ?></button>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold"><?= lang('CreditNotes.linesToCredit') ?></div>
        <?php if ($lineErrors !== []) : ?>
            <div class="alert alert-danger small rounded-0 mb-0 py-2">
                <?php foreach ($lineErrors as $message) : ?><div><?= esc($message) ?></div><?php endforeach ?>
            </div>
        <?php endif ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th class="ps-3"><?= lang('App.description') ?></th>
                        <th class="text-end"><?= lang('Invoices.invoiced') ?></th>
                        <th class="text-end"><?= lang('CreditNotes.alreadyCredited') ?></th>
                        <th class="text-end" style="width: 130px"><?= lang('CreditNotes.qtyToCredit') ?></th>
                        <th class="text-end" style="width: 170px"><?= lang('App.unitPrice') ?></th>
                        <th class="text-end"><?= lang('App.tax') ?></th>
                        <th class="text-end pe-3" style="width: 150px"><?= lang('CreditNotes.creditAmount') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lines as $line) : ?>
                        <?php
                        $lid       = (int) $line['id'];
                        $done      = bccomp((string) $line['creditable_amount'], '0', 2) <= 0;
                        $remaining = bcsub((string) $line['quantity'], (string) $line['credited_quantity'], 3);
                        ?>
                        <tr data-credit-line data-quantity="<?= esc((string) $line['quantity'], 'attr') ?>" data-discount="<?= esc((string) $line['discount_amount'], 'attr') ?>"
                            data-tax-rate="<?= esc((string) $line['tax_rate'], 'attr') ?>" data-remaining="<?= esc(bccomp($remaining, '0', 3) > 0 ? $remaining : '0', 'attr') ?>"
                            data-price="<?= esc(decimal_input($line['unit_price']), 'attr') ?>" data-base="<?= esc((string) $line['price_base'], 'attr') ?>"
                            data-price-incl="<?= esc((string) ($line['unit_price_incl'] ?? ''), 'attr') ?>" data-discount-incl="<?= esc((string) ($line['discount_incl'] ?? ''), 'attr') ?>" class="<?= isset($errors['lines.' . $lid]) ? 'table-danger' : '' ?>">
                            <td class="ps-3">
                                <?= esc(up($line['item_title'])) ?>
                                <div class="small text-body-secondary"><?= esc(item_type_label($line['item_type'])) ?><?= (float) $line['discount_amount'] > 0 ? ' · ' . esc(lang('CreditNotes.discountShort', [format_idr($line['discount_amount'])])) : '' ?></div>
                            </td>
                            <td class="text-end small text-nowrap"><?= esc(format_qty($line['quantity'])) ?> <?= esc($line['unit_code']) ?><div class="text-body-secondary"><?= esc(format_idr($line['line_total'])) ?></div></td>
                            <td class="text-end small text-nowrap"><?= (float) $line['credited_quantity'] > 0 ? esc(format_qty($line['credited_quantity'])) . ' ' . esc($line['unit_code']) . '<div class="text-body-secondary">' . esc(format_idr($line['credited_amount'])) . '</div>' : '<span class="text-body-secondary">-</span>' ?></td>
                            <td><input type="number" class="form-control form-control-sm text-end" name="lines[<?= $lid ?>][quantity]" min="0" max="<?= esc((string) $line['quantity'], 'attr') ?>" step="any"
                                       value="<?= esc((string) ($old[$lid]['quantity'] ?? '')) ?>" placeholder="0" data-field="quantity" aria-label="<?= esc(lang('CreditNotes.qtyToCredit'), 'attr') ?>"<?= $done ? ' disabled' : '' ?>></td>
                            <td><input type="number" class="form-control form-control-sm text-end" name="lines[<?= $lid ?>][unit_price]" min="0" max="<?= esc(decimal_input($line['unit_price']), 'attr') ?>" step="any"
                                       value="<?= esc((string) ($old[$lid]['unit_price'] ?? decimal_input($line['unit_price']))) ?>" data-field="unit_price" aria-label="<?= esc(lang('App.unitPrice'), 'attr') ?>"<?= $done ? ' disabled' : '' ?>></td>
                            <td class="text-end small text-nowrap"><?= esc(rtrim(rtrim((string) $line['tax_rate'], '0'), '.')) ?>%</td>
                            <td class="text-end text-nowrap pe-3" data-line-amount><?= $done ? '<span class="small text-body-secondary">' . esc(lang('CreditNotes.fullyCredited')) . '</span>' : 'Rp 0,00' ?></td>
                        </tr>
                    <?php endforeach ?>
                    <tr>
                        <td class="ps-3" colspan="3">
                            <?= lang('CreditNotes.shippingCost') ?>
                            <div class="small text-body-secondary"><?= esc(lang('CreditNotes.notYetCredited', [format_idr($shipping)])) ?></div>
                        </td>
                        <td colspan="3">
                            <input type="number" class="form-control form-control-sm text-end ms-auto<?= invalid('shipping_amount') ?>" style="max-width: 170px" name="shipping_amount" id="shipping_amount"
                                   min="0" max="<?= esc($shipping, 'attr') ?>" step="any" placeholder="0" value="<?= esc(form_value('shipping_amount')) ?>"
                                   data-max="<?= esc($shipping, 'attr') ?>" aria-label="<?= esc(lang('CreditNotes.shippingToCredit'), 'attr') ?>"<?= bccomp($shipping, '0', 2) <= 0 ? ' disabled' : '' ?>>
                            <?= field_error('shipping_amount') ?>
                        </td>
                        <td class="text-end text-nowrap pe-3" data-shipping-amount>Rp 0,00</td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr><td colspan="6" class="text-end text-body-secondary small border-0 pb-0"><?= lang('App.subtotal') ?></td><td class="text-end text-nowrap pe-3 border-0 pb-0" data-total="subtotal">Rp 0,00</td></tr>
                    <tr><td colspan="6" class="text-end text-body-secondary small border-0 py-0"><?= lang('Proposals.taxPpn') ?></td><td class="text-end text-nowrap pe-3 border-0 py-0" data-total="tax">Rp 0,00</td></tr>
                    <tr><td colspan="6" class="text-end text-body-secondary small border-0 pt-0"><?= lang('Invoices.shipping') ?></td><td class="text-end text-nowrap pe-3 border-0 pt-0" data-total="shipping">Rp 0,00</td></tr>
                    <tr class="table-light"><td colspan="6" class="text-end fw-semibold"><?= lang('CreditNotes.total') ?></td><td class="text-end text-nowrap fw-bold pe-3" data-total="grand">Rp 0,00</td></tr>
                    <tr><td colspan="7" class="text-end text-danger small border-0" data-over hidden><i class="bi bi-exclamation-triangle me-1"></i><?= lang('CreditNotes.overInvoice') ?></td></tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <label for="reason" class="form-label"><?= lang('App.reason') ?> <span class="text-danger">*</span></label>
            <textarea class="form-control<?= invalid('reason') ?>" id="reason" name="reason" rows="3" maxlength="2000" required
                      placeholder="<?= esc(lang('CreditNotes.reasonHint'), 'attr') ?>"><?= esc(form_value('reason')) ?></textarea>
            <?= field_error('reason') ?>
            <div class="form-text"><?= lang('CreditNotes.taxInfo') ?></div>
        </div>
    </div>

    <div class="d-flex gap-2 mb-4">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i><?= lang('CreditNotes.create') ?></button>
        <a href="<?= site_url("invoices/{$iid}") ?>" class="btn btn-outline-secondary"><?= lang('App.cancel') ?></a>
    </div>
</form>

<?= $this->endSection() ?>

<?= $this->section('pageScripts') ?>
<script src="<?= base_url('assets/js/credit-note-form.js') ?>"></script>
<?= $this->endSection() ?>
