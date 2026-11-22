<?php
/**
 * @var list<array<string, mixed>> $products
 * @var array<int, int>            $variants Active variant count per product id.
 * @var \CodeIgniter\Pager\Pager   $pager
 * @var string                     $search
 * @var string                     $status
 */
$canManage = user_can('products.manage');
?>
<?= $this->extend('layouts/app_layout') ?>

<?= $this->section('title') ?><?= lang('Products.products') ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('components/page_header', [
    'title'    => lang('Products.products'),
    'subtitle' => lang('Products.listSubtitle'),
    'actions'  => $canManage ? '<a href="' . site_url('products/new') . '" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>' . esc(lang('Products.newProduct')) . '</a>' : '',
]) ?>

<form method="get" class="card shadow-sm mb-3" data-live-filter>
    <div class="card-body row g-2 align-items-end">
        <div class="col-12 col-md-6">
            <label for="q" class="form-label small mb-1"><?= lang('App.search') ?></label>
            <input type="search" class="form-control" id="q" name="q" value="<?= esc($search) ?>" placeholder="<?= esc(lang('Products.searchHint'), 'attr') ?>">
        </div>
        <div class="col-6 col-md-3">
            <label for="status" class="form-label small mb-1"><?= lang('App.status') ?></label>
            <select class="form-select" id="status" name="status">
                <option value=""><?= lang('App.all') ?></option>
                <option value="active"<?= $status === 'active' ? ' selected' : '' ?>><?= lang('App.active') ?></option>
                <option value="inactive"<?= $status === 'inactive' ? ' selected' : '' ?>><?= lang('App.inactive') ?></option>
            </select>
        </div>
        <div class="col-6 col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-outline-primary flex-grow-1"><i class="bi bi-search me-1"></i><?= lang('App.filter') ?></button>
            <?php if ($search !== '' || $status !== '') : ?>
                <a href="<?= site_url('products') ?>" class="btn btn-outline-secondary" title="<?= esc(lang('App.clearFilters'), 'attr') ?>"><i class="bi bi-x-lg"></i></a>
            <?php endif ?>
        </div>
    </div>
</form>

<div class="card shadow-sm" id="list-results">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-dark">
                <tr>
                    <th style="width: 56px"></th>
                    <th><?= lang('App.sku') ?></th>
                    <th><?= lang('App.title') ?></th>
                    <th><?= lang('App.type') ?></th>
                    <th><?= lang('App.unit') ?></th>
                    <th class="text-end"><?= lang('App.price') ?></th>
                    <th><?= lang('App.tax') ?></th>
                    <th><?= lang('App.status') ?></th>
                    <?php if ($canManage) : ?><th class="text-end"><?= lang('App.actions') ?></th><?php endif ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $p) : ?>
                    <?php $variantCount = $variants[(int) $p['id']] ?? 0 ?>
                    <tr>
                        <td>
                            <?php if (! empty($p['image_path'])) : ?>
                                <img src="<?= site_url('products/' . $p['id'] . '/image') ?>" alt="" class="product-thumb" loading="lazy">
                            <?php else : ?>
                                <span class="product-thumb product-thumb-empty"><i class="bi bi-image"></i></span>
                            <?php endif ?>
                        </td>
                        <td class="font-monospace small fw-semibold"><?= esc($p['pl_sku']) ?></td>
                        <td>
                            <a href="<?= site_url('products/' . $p['id']) ?>" class="text-reset text-decoration-none fw-semibold"><?= esc(up($p['pl_title'])) ?></a>
                            <?php if (! empty($p['pl_alias'])) : ?><div class="small text-body-secondary"><?= esc(up($p['pl_alias'])) ?></div><?php endif ?>
                            <?php if ($variantCount > 0) : ?><span class="badge bg-info-subtle text-info-emphasis border"><?= lang('Products.variantsCount', [$variantCount]) ?></span><?php endif ?>
                        </td>
                        <td class="small"><?= esc(item_type_label($p['pl_type'])) ?></td>
                        <td class="small font-monospace"><?= esc($p['unit_code'] ?? '') ?></td>
                        <td class="text-end text-nowrap"><?= esc(format_idr($p['pl_price'])) ?></td>
                        <td class="small"><?= $p['tax_code'] ? esc($p['tax_code'] . ' ' . rtrim(rtrim((string) $p['tax_rate'], '0'), '.') . '%') : '<span class="text-body-secondary">-</span>' ?></td>
                        <td><?= active_badge($p['is_active']) ?></td>
                        <?php if ($canManage) : ?>
                            <td class="text-end text-nowrap">
                                <a href="<?= site_url('products/' . $p['id'] . '/edit') ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil"></i> <?= lang('App.edit') ?></a>
                            </td>
                        <?php endif ?>
                    </tr>
                <?php endforeach ?>
                <?php if ($products === []) : ?>
                    <tr><td colspan="9" class="text-center text-body-secondary py-4">
                        <i class="bi bi-box-seam fs-3 d-block mb-1"></i>
                        <?= $search !== '' || $status !== '' ? lang('Products.noMatch') : lang('Products.noneYet') ?>
                    </td></tr>
                <?php endif ?>
            </tbody>
        </table>
    </div>
    <?php if ($pager->getPageCount() > 1) : ?>
        <div class="card-footer bg-white d-flex justify-content-between align-items-center">
            <span class="small text-body-secondary"><?= lang('Products.count', [$pager->getTotal()]) ?></span>
            <?= $pager->links('default', 'bootstrap') ?>
        </div>
    <?php endif ?>
</div>

<?= $this->endSection() ?>
