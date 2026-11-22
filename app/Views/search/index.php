<?php
/**
 * Full search page (Enter in the top bar search box).
 *
 * @var string $q
 * @var list<array<string, mixed>> $results
 */
?>
<?= $this->extend('layouts/app_layout') ?>

<?= $this->section('title') ?><?= lang('Dashboard.search') ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('components/page_header', ['title' => lang('Dashboard.search'), 'subtitle' => $q !== '' ? lang('Dashboard.resultsFor', [$q]) : null]) ?>

<div class="card shadow-sm">
    <div class="card-body p-0 py-2">
        <?= view('search/results', ['q' => $q, 'results' => $results]) ?>
    </div>
</div>

<?= $this->endSection() ?>
