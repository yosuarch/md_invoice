<?php
/**
 * Staff sidebar. Fixed on large screens, slide-in offcanvas on smaller ones.
 * Shows only the items the current user has permission for.
 */
$nav = config('Navigation');
?>
<aside class="app-sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="appSidebar"
       aria-labelledby="appSidebarLabel">
    <div class="offcanvas-header p-0 d-lg-none">
        <span class="visually-hidden" id="appSidebarLabel"><?= esc($nav->appName) ?> <?= lang('Nav.menu') ?></span>
    </div>

    <div class="offcanvas-body d-flex flex-column p-0">
        <div class="d-flex align-items-center">
            <a href="<?= site_url('/') ?>" class="app-brand flex-grow-1">
                <img src="<?= base_url('assets/img/logo-mark.png') ?>" alt="" class="app-brand-mark">
                <span>
                    <img src="<?= base_url('assets/img/logo-wordmark.png') ?>" alt="<?= esc($nav->companyName, 'attr') ?>"
                         class="app-brand-wordmark">
                    <span class="app-brand-caption"><?= esc($nav->moduleName) ?></span>
                </span>
            </a>
            <button type="button" class="btn-close me-3 d-lg-none" data-bs-dismiss="offcanvas"
                    data-bs-target="#appSidebar" aria-label="<?= esc(lang('App.close'), 'attr') ?>"></button>
        </div>

        <nav class="p-2">
            <?php foreach ($nav->sidebar as $heading => $items) : ?>
                <?php $visible = nav_visible_items($items) ?>
                <?php if ($visible === []) {
                    continue;
                } ?>

                <?php if ($heading !== '') : ?>
                    <div class="sidebar-heading text-uppercase px-3 mt-3 mb-1"><?= esc(lang($heading)) ?></div>
                <?php endif ?>

                <ul class="nav flex-column gap-1">
                    <?php foreach ($visible as $item) : ?>
                        <?php $active = nav_is_active($item) ?>
                        <li class="nav-item">
                            <a class="nav-link d-flex align-items-center gap-2<?= $active ? ' active' : '' ?>"
                               href="<?= site_url($item['uri']) ?>"<?= $active ? ' aria-current="page"' : '' ?>>
                                <i class="bi bi-<?= esc($item['icon'], 'attr') ?>"></i><?= esc(lang($item['label'])) ?>
                            </a>
                        </li>
                    <?php endforeach ?>
                </ul>
            <?php endforeach ?>
        </nav>
    </div>
</aside>
