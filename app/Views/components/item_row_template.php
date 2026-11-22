<?php
/**
 * Row template for the editable item table (assets/js/document-items.js). Inputs carry data-name="field";
 * rows are posted as items[n][field]. Unit and type are display-only (copied from the product); the tax rate
 * defaults from the product and can be changed per line (Dolibarr style); the price can be typed excl. or incl. tax
 * (price_base). The description (special request / custom text) is copied from the product and editable per line.
 *
 * @var array<string, string>|null $taxes Tax codes valid today => rate (App\Services\LineTax::options()); empty = not PKP.
 */
$taxes ??= \App\Services\LineTax::options();
?>
<template id="item-row-template">
    <tr data-item-row>
        <td class="text-body-secondary small" data-line-no></td>
        <td>
            <input type="hidden" data-name="id">
            <input type="hidden" data-name="product_id">
            <input type="hidden" data-name="variant_id">
            <input type="hidden" data-name="sku">
            <input type="hidden" data-name="unit_code">
            <input type="hidden" data-name="item_type">
            <input type="hidden" data-name="tax_rate">
            <input type="hidden" data-name="min_price">
            <input type="text" class="form-control form-control-sm text-uppercase" data-name="item_title" maxlength="200" required aria-label="<?= esc(lang('App.title'), 'attr') ?>">
            <textarea class="form-control form-control-sm item-description mt-1" data-name="item_description" rows="1" maxlength="2000"
                      placeholder="<?= esc(lang('Js.lineDescription'), 'attr') ?>" aria-label="<?= esc(lang('Js.lineDescription'), 'attr') ?>"></textarea>
            <div class="small text-body-secondary mt-1" data-meta></div>
        </td>
        <td><input type="number" class="form-control form-control-sm text-end" data-name="quantity" min="0" step="any" required aria-label="<?= esc(lang('App.quantity'), 'attr') ?>"></td>
        <td class="small" data-unit></td>
        <td>
            <input type="number" class="form-control form-control-sm text-end" data-name="unit_price" min="0" step="any" required aria-label="<?= esc(lang('App.unitPrice'), 'attr') ?>">
            <select class="form-select form-select-sm mt-1 price-base" data-name="price_base" aria-label="<?= esc(lang('Js.priceBase'), 'attr') ?>"<?= $taxes === [] ? ' hidden' : '' ?>>
                <option value="EXCL"><?= esc(lang('Js.exclTax')) ?></option>
                <option value="INCL"><?= esc(lang('Js.inclTax')) ?></option>
            </select>
            <div class="small text-body-secondary text-end mt-1" data-price-hint></div>
            <div class="small text-danger text-end" data-min-warning hidden></div>
        </td>
        <td><input type="number" class="form-control form-control-sm text-end" data-name="discount_amount" min="0" step="any" aria-label="<?= esc(lang('App.discount'), 'attr') ?>"></td>
        <td>
            <select class="form-select form-select-sm line-tax" data-name="tax_code" aria-label="<?= esc(lang('App.tax'), 'attr') ?>"<?= $taxes === [] ? ' disabled' : '' ?>>
                <option value="" data-rate="0"><?= esc(lang('Js.noTaxShort')) ?></option>
                <?php foreach ($taxes as $code => $rate) : ?>
                    <option value="<?= esc($code, 'attr') ?>" data-rate="<?= esc($rate, 'attr') ?>"><?= esc($code . ' ' . rtrim(rtrim($rate, '0'), '.') . '%') ?></option>
                <?php endforeach ?>
            </select>
        </td>
        <td class="text-end text-nowrap" data-line-total></td>
        <td class="text-end"><button type="button" class="btn btn-link btn-sm text-danger p-1" data-remove-row title="<?= esc(lang('App.remove'), 'attr') ?>" aria-label="<?= esc(lang('App.remove'), 'attr') ?>"><i class="bi bi-x-lg"></i></button></td>
    </tr>
</template>
