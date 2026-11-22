<?php
/**
 * Shared <head> contents for every layout.
 *
 * Pages may define 'title' and 'pageStyles' sections (both optional). pageStyles is only applied on a full page
 * load; prefer app.css for styles.
 */
$pageTitle = trim($this->renderSection('title', true)); // keep: top bar renders it again
$appName   = config('Navigation')->appName;
?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#19545e">
    <meta name="csrf-token" content="<?= csrf_hash() ?>">
    <meta name="csrf-header" content="<?= csrf_header() ?>">

    <title><?= $pageTitle !== '' ? esc($pageTitle) . ' | ' : '' ?><?= esc($appName) ?></title>

    <link rel="icon" href="<?= base_url('favicon.ico') ?>" sizes="any">
    <link rel="icon" type="image/png" href="<?= base_url('assets/img/favicon-64.png') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap-icons/bootstrap-icons.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">

    <?php /* Global scripts load once in <head>: the staff layout swaps only <body> (htmx boost), so they must not
             be re-run on every page. They only define functions and delegated listeners; DOM work runs via htmx.onLoad. */ ?>
    <meta name="htmx-config" content='{"historyCacheSize":0,"refreshOnHistoryMiss":true,"scrollBehavior":"instant","includeIndicatorStyles":false,"defaultSettleDelay":0}'>
    <script>window.AppLang = <?= json_encode(js_strings(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
    <script>window.AppCalc = <?= json_encode(\App\Services\CalcSettings::current()->toJs()) ?>;</script>
    <script src="<?= base_url('assets/vendor/bootstrap/js/bootstrap.bundle.min.js') ?>"></script>
    <script src="<?= base_url('assets/vendor/htmx/htmx.min.js') ?>"></script>
    <script src="<?= base_url('assets/js/app.js') ?>"></script>
    <script src="<?= base_url('assets/js/typeahead.js') ?>"></script>
    <script src="<?= base_url('assets/js/document-items.js') ?>"></script>
    <script src="<?= base_url('assets/js/cascade-select.js') ?>"></script>

    <?= $this->renderSection('pageStyles') ?>
