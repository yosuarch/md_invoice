<?php
/**
 * Product page (read-only; edit button for products.manage).
 *
 * @var array<string, mixed>       $product  Product with unit_code, tax_code, tax_rate.
 * @var list<array<string, mixed>> $variants
 * @var bool                       $inUse
 */
$canManage = user_can('products.manage');
?>
<?= $this->extend('layouts/app_layout') ?>

<?= $this->section('title') ?><?= esc(up($product['pl_title'])) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('components/page_header', [
    'title'      => up($product['pl_title']),
    'subtitle'   => $product['pl_sku'] . ($product['pl_alias'] ? ' · ' . up($product['pl_alias']) : ''),
    'breadcrumb' => [[lang('Products.products'), site_url('products')], [$product['pl_sku'], '']],
    'actions'    => $canManage ? '<a href="' . site_url('products/' . $product['id'] . '/edit') . '" class="btn btn-primary"><i class="bi bi-pencil me-1"></i>' . esc(lang('App.edit')) . '</a>' : '',
]) ?>

<div class="row g-3">
    <div class="col-12 col-md-4 col-xl-3">
        <div class="card shadow-sm">
            <?php if (! empty($product['image_path'])) : ?>
                <img src="<?= site_url('products/' . $product['id'] . '/image') ?>" class="card-img-top product-picture" alt="<?= esc(up($product['pl_title']), 'attr') ?>">
            <?php else : ?>
                <div class="product-picture product-picture-empty"><i class="bi bi-image fs-1"></i><span class="small"><?= lang('Products.noPicture') ?></span></div>
            <?php endif ?>
        </div>
    </div>
    <div class="col-12 col-md-8 col-xl-9">
        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-sm-3"><?= lang('App.type') ?></dt>
                    <dd class="col-sm-9"><?= esc(item_type_label($product['pl_type'])) ?></dd>
                    <dt class="col-sm-3"><?= lang('App.unit') ?></dt>
                    <dd class="col-sm-9 font-monospace"><?= esc($product['unit_code'] ?? '') ?></dd>
                    <dt class="col-sm-3"><?= lang('Products.defaultPrice') ?></dt>
                    <dd class="col-sm-9"><?= esc(format_idr($product['pl_price'])) ?> <span class="text-body-secondary">(<?= $product['pl_price_incl_tax'] ? lang('Products.inclTaxShort') : lang('Products.beforeTax') ?>)</span></dd>
                    <?php if ($product['pl_min_price'] !== null) : ?>
                        <dt class="col-sm-3"><?= lang('Products.minPrice') ?></dt>
                        <dd class="col-sm-9"><?= esc(format_idr($product['pl_min_price'])) ?></dd>
                    <?php endif ?>
                    <dt class="col-sm-3"><?= lang('App.tax') ?></dt>
                    <dd class="col-sm-9"><?= $product['tax_code'] ? esc($product['tax_code'] . ' ' . rtrim(rtrim((string) $product['tax_rate'], '0'), '.') . '%') : esc(lang('App.noTax')) ?></dd>
                    <dt class="col-sm-3"><?= lang('App.status') ?></dt>
                    <dd class="col-sm-9"><?= active_badge($product['is_active']) ?></dd>
                    <dt class="col-sm-3"><?= lang('App.description') ?></dt>
                    <dd class="col-sm-9 mb-0"><?= $product['pl_description'] ? nl2br(esc($product['pl_description'])) : '<span class="text-body-secondary">-</span>' ?></dd>
                </dl>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-diagram-3 me-1"></i><?= lang('Products.variants') ?></div>
            <?php if ($variants === []) : ?>
                <div class="card-body small text-body-secondary"><?= lang('Products.noVariants') ?></div>
            <?php else : ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width: 48px"></th>
                                <th><?= lang('App.sku') ?></th>
                                <th><?= lang('Products.variantName') ?></th>
                                <th class="text-end"><?= lang('App.price') ?></th>
                                <th><?= lang('App.status') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($variants as $v) : ?>
                                <tr>
                                    <td>
                                        <?php if (! empty($v['image_path'])) : ?>
                                            <img src="<?= site_url('products/variants/' . $v['id'] . '/image') ?>" alt="" class="product-thumb" loading="lazy">
                                        <?php endif ?>
                                    </td>
                                    <td class="font-monospace small"><?= esc($v['pv_sku']) ?></td>
                                    <td><?= esc(up($v['pv_name'])) ?></td>
                                    <td class="text-end text-nowrap"><?= esc(format_idr($v['pv_price'])) ?></td>
                                    <td><?= active_badge($v['is_active']) ?></td>
                                </tr>
                            <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
            <?php endif ?>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
