<?php
/**
 * Product create/edit form with picture and description; on edit also the variants (each row its own form via the
 * HTML form attribute, so the table needs no nested forms).
 *
 * @var array<string, mixed>            $product  Empty on create.
 * @var array<int, string>              $units
 * @var array<int, string>              $taxRates
 * @var bool|null                       $inUse
 * @var list<array<string, mixed>>|null $variants
 */
$isEdit   = $product !== [];
$title    = $isEdit ? lang('Products.editProduct') : lang('Products.newProduct');
$action   = $isEdit ? site_url('products/' . $product['id']) : site_url('products');
$variants ??= [];
$errors   = session('errors') ?? [];
$openForm = session('variant_form'); // variant form that failed validation ('new' or id)

/**
 * Value of a variant field: old input when this variant's form failed, else the stored value.
 */
$variantValue = static function (string $field, array $variant, string|int $formKey, string $default = '') use ($openForm): string {
    if ((string) $openForm === (string) $formKey) {
        return (string) old($field, $variant[$field] ?? $default, false);
    }

    return (string) ($variant[$field] ?? $default);
};
?>
<?= $this->extend('layouts/app_layout') ?>

<?= $this->section('title') ?><?= esc($title) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('components/page_header', [
    'title'      => $title,
    'subtitle'   => $isEdit ? $product['pl_sku'] . ' · ' . up($product['pl_title']) : null,
    'breadcrumb' => $isEdit
        ? [[lang('Products.products'), site_url('products')], [$product['pl_sku'], site_url('products/' . $product['id'])], [lang('App.edit'), '']]
        : [[lang('Products.products'), site_url('products')], [lang('App.new'), '']],
]) ?>

<?php if ($isEdit && ! empty($inUse)) : ?>
    <div class="alert alert-info small"><i class="bi bi-info-circle me-1"></i><?= lang('Products.inUseInfo') ?></div>
<?php endif ?>

<div class="card shadow-sm mb-3" style="max-width: 1000px">
    <div class="card-body">
        <form action="<?= $action ?>" method="post" enctype="multipart/form-data" class="row g-3" novalidate>
            <?= csrf_field() ?>
            <div class="col-12 col-md-4">
                <label for="pl_sku" class="form-label"><?= lang('App.sku') ?></label>
                <input type="text" class="form-control font-monospace" id="pl_sku" readonly tabindex="-1"
                       value="<?= esc($isEdit ? $product['pl_sku'] : (new \App\Services\DocumentSequence())->preview('SKU')) ?>">
                <div class="form-text"><?= $isEdit ? lang('Products.skuGenerated') : lang('Products.skuOnSave') ?></div>
            </div>
            <div class="col-12 col-md-8">
                <label for="pl_title" class="form-label"><?= lang('App.title') ?> <span class="text-danger">*</span></label>
                <input type="text" class="form-control text-uppercase<?= invalid('pl_title') ?>" id="pl_title" name="pl_title" maxlength="200"
                       required value="<?= esc(form_value('pl_title', $product)) ?>">
                <?= field_error('pl_title') ?>
            </div>
            <div class="col-12">
                <label for="pl_alias" class="form-label"><?= lang('Products.alias') ?></label>
                <input type="text" class="form-control text-uppercase<?= invalid('pl_alias') ?>" id="pl_alias" name="pl_alias" maxlength="200"
                       value="<?= esc(form_value('pl_alias', $product)) ?>">
                <?= field_error('pl_alias') ?>
                <div class="form-text"><?= lang('Products.aliasHelp') ?></div>
            </div>
            <div class="col-12">
                <label for="pl_description" class="form-label"><?= lang('App.description') ?></label>
                <textarea class="form-control<?= invalid('pl_description') ?>" id="pl_description" name="pl_description" rows="3" maxlength="2000"><?= esc(form_value('pl_description', $product)) ?></textarea>
                <?= field_error('pl_description') ?>
                <div class="form-text"><?= lang('Products.descriptionHelp') ?></div>
            </div>
            <div class="col-12">
                <div class="form-label"><?= lang('App.type') ?> <span class="text-danger">*</span></div>
                <?php $currentType = form_value('pl_type', $product, 'GOODS') ?>
                <?php foreach (array_keys(\App\Models\ProductModel::TYPES) as $key) : ?>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input<?= invalid('pl_type') ?>" type="radio" name="pl_type" id="type_<?= $key ?>"
                               value="<?= $key ?>"<?= $currentType === $key ? ' checked' : '' ?>>
                        <label class="form-check-label" for="type_<?= $key ?>"><?= esc(item_type_label($key)) ?></label>
                    </div>
                <?php endforeach ?>
                <?= field_error('pl_type') ?>
                <div class="form-text"><?= lang('Products.typeHelp') ?></div>
            </div>
            <div class="col-12 col-md-4">
                <label for="unit_id" class="form-label"><?= lang('App.unit') ?> <span class="text-danger">*</span></label>
                <select class="form-select<?= invalid('unit_id') ?>" id="unit_id" name="unit_id" required>
                    <option value=""><?= lang('App.select') ?></option>
                    <?php foreach ($units as $id => $label) : ?>
                        <option value="<?= $id ?>"<?= form_value('unit_id', $product) === (string) $id ? ' selected' : '' ?>><?= esc($label) ?></option>
                    <?php endforeach ?>
                </select>
                <?= field_error('unit_id') ?>
            </div>
            <div class="col-12 col-md-4">
                <label for="pl_price" class="form-label"><?= lang('Products.defaultPrice') ?> <span class="text-danger">*</span></label>
                <div class="input-group has-validation">
                    <span class="input-group-text">Rp</span>
                    <input type="number" class="form-control text-end<?= invalid('pl_price') ?>" id="pl_price" name="pl_price" min="0" step="0.01"
                           required value="<?= esc(form_value('pl_price', $product, '0.00')) ?>">
                    <?= field_error('pl_price') ?>
                </div>
                <input type="hidden" name="pl_price_incl_tax" value="0">
                <div class="form-check mt-1">
                    <input class="form-check-input" type="checkbox" id="pl_price_incl_tax" name="pl_price_incl_tax" value="1"<?= form_checked('pl_price_incl_tax', $product) ?>>
                    <label class="form-check-label small" for="pl_price_incl_tax"><?= lang('Products.priceInclTax') ?></label>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <label for="tax_rate_id" class="form-label"><?= lang('App.tax') ?></label>
                <select class="form-select<?= invalid('tax_rate_id') ?>" id="tax_rate_id" name="tax_rate_id">
                    <option value=""><?= lang('Products.noTaxOption') ?></option>
                    <?php foreach ($taxRates as $id => $label) : ?>
                        <option value="<?= $id ?>"<?= form_value('tax_rate_id', $product) === (string) $id ? ' selected' : '' ?>><?= esc($label) ?></option>
                    <?php endforeach ?>
                </select>
                <?= field_error('tax_rate_id') ?>
            </div>
            <div class="col-12 col-md-4">
                <label for="pl_min_price" class="form-label"><?= lang('Products.minPrice') ?></label>
                <div class="input-group has-validation">
                    <span class="input-group-text">Rp</span>
                    <input type="number" class="form-control text-end<?= invalid('pl_min_price') ?>" id="pl_min_price" name="pl_min_price" min="0" step="0.01"
                           value="<?= esc(form_value('pl_min_price', $product)) ?>">
                    <?= field_error('pl_min_price') ?>
                </div>
            </div>
            <div class="col-12 col-md-8 d-flex align-items-end">
                <div class="form-text"><?= lang('Products.minPriceHelp') ?></div>
            </div>
            <div class="col-12">
                <label for="image" class="form-label"><?= lang('App.picture') ?></label>
                <div class="d-flex flex-wrap align-items-start gap-3">
                    <?php if ($isEdit && ! empty($product['image_path'])) : ?>
                        <div class="text-center">
                            <img src="<?= site_url('products/' . $product['id'] . '/image') ?>" alt="" class="product-thumb product-thumb-lg">
                            <div class="form-check small mt-1">
                                <input type="hidden" name="remove_image" value="0">
                                <input class="form-check-input" type="checkbox" id="remove_image" name="remove_image" value="1">
                                <label class="form-check-label" for="remove_image"><?= lang('Products.removePicture') ?></label>
                            </div>
                        </div>
                    <?php endif ?>
                    <div class="flex-grow-1">
                        <input type="file" class="form-control<?= isset($errors['image']) ? ' is-invalid' : '' ?>" id="image" name="image" accept="image/png,image/jpeg,image/webp">
                        <?php if (isset($errors['image'])) : ?><div class="invalid-feedback"><?= esc($errors['image']) ?></div><?php endif ?>
                        <div class="form-text"><?= lang('Products.pictureHelp') ?></div>
                    </div>
                </div>
            </div>
            <div class="col-12">
                <input type="hidden" name="is_active" value="0">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1"<?= form_checked('is_active', $product, true) ?>>
                    <label class="form-check-label" for="is_active"><?= lang('Products.activeHelp') ?></label>
                </div>
            </div>
            <div class="col-12 d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i><?= lang('App.save') ?></button>
                <a href="<?= site_url($isEdit ? 'products/' . $product['id'] : 'products') ?>" class="btn btn-outline-secondary"><?= lang('App.cancel') ?></a>
            </div>
        </form>
        <?php if ($isEdit && empty($inUse)) : ?>
            <hr>
            <?= view('components/delete_button', [
                'action'  => site_url('products/' . $product['id'] . '/delete'),
                'label'   => lang('Products.deleteProduct'),
                'icon'    => 'trash',
                'confirm' => lang('Products.deleteConfirm', [$product['pl_sku']]),
            ]) ?>
        <?php endif ?>
    </div>
</div>

<div class="card shadow-sm" style="max-width: 1000px" id="variants">
    <div class="card-header bg-white">
        <div class="fw-semibold"><i class="bi bi-diagram-3 me-1"></i><?= lang('Products.variants') ?></div>
        <div class="small text-body-secondary"><?= lang('Products.variantsHelp') ?></div>
    </div>
    <?php if (! $isEdit) : ?>
        <div class="card-body small text-body-secondary"><?= lang('Products.variantsAfterSave') ?></div>
    <?php else : ?>
        <?php if (in_array($openForm, [null, ''], true) === false && ($errors['variant_image'] ?? $errors['pv_name'] ?? $errors['pv_price'] ?? null) !== null) : ?>
            <div class="alert alert-danger small m-3 mb-0"><?= esc(implode(' ', array_intersect_key($errors, array_flip(['pv_name', 'pv_price', 'variant_image', 'pv_sku'])))) ?></div>
        <?php endif ?>
        <?php foreach ([...array_column($variants, 'id'), 'new'] as $key) : ?>
            <form id="variant-form-<?= esc((string) $key, 'attr') ?>" method="post" enctype="multipart/form-data"
                  action="<?= site_url('products/' . $product['id'] . '/variants' . ($key === 'new' ? '' : '/' . $key)) ?>">
                <?= csrf_field() ?>
            </form>
        <?php endforeach ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr class="small">
                        <th style="width: 56px"></th>
                        <th style="width: 140px"><?= lang('App.sku') ?></th>
                        <th style="min-width: 220px"><?= lang('Products.variantName') ?> <span class="text-danger">*</span></th>
                        <th style="width: 150px"><?= lang('App.price') ?> <span class="text-danger">*</span></th>
                        <th style="width: 140px"><?= lang('Products.minPriceShort') ?></th>
                        <th style="width: 200px"><?= lang('App.picture') ?></th>
                        <th style="width: 80px"><?= lang('Products.order') ?></th>
                        <th style="width: 80px"><?= lang('App.active') ?></th>
                        <th class="text-end"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($variants as $v) : ?>
                        <?php $f = 'variant-form-' . $v['id'] ?>
                        <tr>
                            <td>
                                <?php if (! empty($v['image_path'])) : ?>
                                    <img src="<?= site_url('products/variants/' . $v['id'] . '/image') ?>" alt="" class="product-thumb">
                                <?php endif ?>
                            </td>
                            <td class="font-monospace small"><?= esc($v['pv_sku']) ?></td>
                            <td><input form="<?= $f ?>" type="text" name="pv_name" class="form-control form-control-sm text-uppercase" maxlength="128" required value="<?= esc($variantValue('pv_name', $v, $v['id'])) ?>"></td>
                            <td><input form="<?= $f ?>" type="number" name="pv_price" class="form-control form-control-sm text-end" min="0" step="0.01" required value="<?= esc($variantValue('pv_price', $v, $v['id'])) ?>"></td>
                            <td><input form="<?= $f ?>" type="number" name="pv_min_price" class="form-control form-control-sm text-end" min="0" step="0.01" value="<?= esc($variantValue('pv_min_price', $v, $v['id'])) ?>" aria-label="<?= esc(lang('Products.minPrice'), 'attr') ?>"></td>
                            <td>
                                <input form="<?= $f ?>" type="file" name="variant_image" class="form-control form-control-sm" accept="image/png,image/jpeg,image/webp" aria-label="<?= esc(lang('App.picture'), 'attr') ?>">
                                <?php if (! empty($v['image_path'])) : ?>
                                    <div class="form-check small mt-1">
                                        <input form="<?= $f ?>" type="hidden" name="remove_image" value="0">
                                        <input form="<?= $f ?>" class="form-check-input" type="checkbox" id="rm-<?= $v['id'] ?>" name="remove_image" value="1">
                                        <label class="form-check-label" for="rm-<?= $v['id'] ?>"><?= lang('Products.removePicture') ?></label>
                                    </div>
                                <?php endif ?>
                            </td>
                            <td><input form="<?= $f ?>" type="number" name="sort_order" class="form-control form-control-sm" min="0" step="1" value="<?= esc($variantValue('sort_order', $v, $v['id'], '0')) ?>"></td>
                            <td>
                                <input form="<?= $f ?>" type="hidden" name="is_active" value="0">
                                <div class="form-check form-switch m-0">
                                    <input form="<?= $f ?>" class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" aria-label="<?= esc(lang('App.active'), 'attr') ?>"<?= $variantValue('is_active', $v, $v['id'], '1') === '1' ? ' checked' : '' ?>>
                                </div>
                            </td>
                            <td class="text-end text-nowrap">
                                <button form="<?= $f ?>" type="submit" class="btn btn-outline-primary btn-sm" title="<?= esc(lang('App.save'), 'attr') ?>"><i class="bi bi-check-lg"></i></button>
                                <?= view('components/delete_button', [
                                    'action'  => site_url('products/' . $product['id'] . '/variants/' . $v['id'] . '/delete'),
                                    'label'   => '',
                                    'icon'    => 'trash',
                                    'confirm' => lang('Products.deleteVariantConfirm', [up($v['pv_name'])]),
                                ]) ?>
                            </td>
                        </tr>
                    <?php endforeach ?>
                    <tr class="table-light">
                        <td class="text-center text-body-secondary"><i class="bi bi-plus-lg"></i></td>
                        <td class="small text-body-secondary"><?= lang('App.new') ?></td>
                        <td><input form="variant-form-new" type="text" name="pv_name" class="form-control form-control-sm text-uppercase" maxlength="128" required placeholder="<?= esc(lang('Products.variantNameHint'), 'attr') ?>" value="<?= esc($variantValue('pv_name', [], 'new')) ?>"></td>
                        <td><input form="variant-form-new" type="number" name="pv_price" class="form-control form-control-sm text-end" min="0" step="0.01" required value="<?= esc($variantValue('pv_price', [], 'new', (string) $product['pl_price'])) ?>"></td>
                        <td><input form="variant-form-new" type="number" name="pv_min_price" class="form-control form-control-sm text-end" min="0" step="0.01" value="<?= esc($variantValue('pv_min_price', [], 'new')) ?>" aria-label="<?= esc(lang('Products.minPrice'), 'attr') ?>"></td>
                        <td><input form="variant-form-new" type="file" name="variant_image" class="form-control form-control-sm" accept="image/png,image/jpeg,image/webp" aria-label="<?= esc(lang('App.picture'), 'attr') ?>"></td>
                        <td><input form="variant-form-new" type="number" name="sort_order" class="form-control form-control-sm" min="0" step="1" value="<?= esc($variantValue('sort_order', [], 'new', (string) (count($variants) + 1))) ?>"></td>
                        <td>
                            <input form="variant-form-new" type="hidden" name="is_active" value="1">
                        </td>
                        <td class="text-end"><button form="variant-form-new" type="submit" class="btn btn-primary btn-sm text-nowrap"><i class="bi bi-plus-lg me-1"></i><?= lang('Products.addVariant') ?></button></td>
                    </tr>
                </tbody>
            </table>
        </div>
    <?php endif ?>
</div>

<?= $this->endSection() ?>
