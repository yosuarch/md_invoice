<?php
/**
 * Layout for Shield auth pages (login, register, magic link, ...).
 * Registered in Config\Auth::$views['layout']. Shield views fill the 'main' section.
 *
 * Sections: title, main, pageStyles (optional), pageScripts (optional).
 */
?>
<!doctype html>
<html lang="<?= esc(service('request')->getLocale(), 'attr') ?>">
<head>
<?= $this->include('layouts/common/head') ?>
</head>
<body class="auth-area d-flex flex-column min-vh-100" data-auth-page>
    <div id="auth-page" hidden></div>

    <div class="position-absolute top-0 end-0 p-3"><?= view('components/language_toggle') ?></div>

    <div class="text-center pt-5">
        <a href="<?= site_url('/') ?>" class="d-inline-block text-decoration-none">
            <img src="<?= base_url('assets/img/logo-full.png') ?>" class="auth-logo"
                 alt="<?= esc(config('Navigation')->companyName, 'attr') ?>">
        </a>
        <div class="app-brand-caption mt-2"><?= esc(config('Navigation')->moduleName) ?></div>
    </div>

    <main role="main" class="container flex-grow-1">
        <?= $this->renderSection('main') ?>
    </main>

    <?= $this->include('layouts/common/footer') ?>
    <?= $this->include('layouts/common/scripts') ?>
</body>
</html>
