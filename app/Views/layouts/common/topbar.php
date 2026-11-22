<?php
/**
 * Top bar: sidebar toggle (small screens), page heading and profile dropdown.
 */
?>
<header class="app-topbar navbar px-3 pt-2 sticky-top">
    <div class="d-flex align-items-center gap-2">
        <button class="btn btn-outline-primary btn-sm d-lg-none" type="button" data-bs-toggle="offcanvas"
                data-bs-target="#appSidebar" aria-controls="appSidebar" aria-label="<?= esc(lang('Nav.openMenu'), 'attr') ?>">
            <i class="bi bi-list fs-5"></i>
        </button>
        <span class="page-title"><?= esc(trim($this->renderSection('title', true))) ?></span>
    </div>

    <form action="<?= site_url('search') ?>" method="get" role="search" class="app-search ms-auto me-2 position-relative">
        <div class="input-group input-group-sm">
            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
            <input type="search" name="q" class="form-control" placeholder="<?= esc(lang('Nav.searchPlaceholder'), 'attr') ?>" aria-label="<?= esc(lang('Nav.searchLabel'), 'attr') ?>"
                   autocomplete="off" value="<?= url_is('search') ? esc((string) service('request')->getGet('q')) : '' ?>"
                   hx-get="<?= site_url('search') ?>" hx-trigger="input changed delay:300ms, search" hx-target="#search-results"
                   hx-swap="innerHTML" hx-sync="this:replace" hx-push-url="false">
        </div>
        <div id="search-results" class="app-search-results card shadow" role="listbox" aria-label="<?= esc(lang('Nav.searchResults'), 'attr') ?>"></div>
    </form>
    <div class="me-2"><?= view('components/language_toggle') ?></div>
    <?= $this->include('layouts/common/user_menu') ?>
</header>
