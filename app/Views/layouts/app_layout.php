<?php
/**
 * Staff layout (the only app layout; customers do not log in):
 * branded light sidebar, top bar and dense content area.
 *
 * Sections: title, content, pageStyles (optional), pageScripts (optional).
 *
 * Single-page feel: htmx boosts every link and form (hx-boost), so navigation fetches the next page and swaps the
 * <body> without reloading scripts and styles. Downloads and new-tab links opt out (hx-boost="false" / target).
 */
?>
<!doctype html>
<html lang="<?= esc(service('request')->getLocale(), 'attr') ?>">
<head>
<?= $this->include('layouts/common/head') ?>
</head>
<body hx-boost="true">
    <div id="page-progress" aria-hidden="true"></div>
    <div id="csrf-state" data-token="<?= csrf_hash() ?>" hidden></div>
    <div class="d-lg-flex min-vh-100">
        <?= $this->include('layouts/common/sidebar') ?>

        <div class="app-main d-flex flex-column flex-grow-1 min-vh-100">
            <?= $this->include('layouts/common/topbar') ?>

            <main class="container-fluid p-3 p-lg-4 flex-grow-1">
                <?= $this->include('layouts/common/flash_messages') ?>
                <?= $this->renderSection('content') ?>
            </main>

            <?= $this->include('layouts/common/footer') ?>
        </div>
    </div>

    <?= $this->include('layouts/common/scripts') ?>
</body>
</html>
