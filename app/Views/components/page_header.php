<?php
/**
 * Page header with optional breadcrumb, subtitle and action buttons.
 *
 * @var string                                   $title
 * @var string|null                              $subtitle
 * @var list<array{0: string, 1: string}>|null   $breadcrumb [label, url] pairs (last one = current page, url ignored)
 * @var string|null                              $actions    Pre-rendered HTML for the right side.
 */
$breadcrumb ??= [];
?>
<div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
    <div>
        <?php if ($breadcrumb !== []) : ?>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb small mb-1">
                    <?php foreach ($breadcrumb as $i => [$label, $url]) : ?>
                        <?php if ($i === array_key_last($breadcrumb)) : ?>
                            <li class="breadcrumb-item active" aria-current="page"><?= esc($label) ?></li>
                        <?php else : ?>
                            <li class="breadcrumb-item"><a href="<?= esc($url, 'attr') ?>"><?= esc($label) ?></a></li>
                        <?php endif ?>
                    <?php endforeach ?>
                </ol>
            </nav>
        <?php endif ?>
        <h1 class="h4 mb-0"><?= esc($title) ?></h1>
        <?php if (! empty($subtitle)) : ?>
            <div class="text-body-secondary small mt-1"><?= esc($subtitle) ?></div>
        <?php endif ?>
    </div>
    <?php if (! empty($actions)) : ?>
        <div class="d-flex flex-wrap gap-2"><?= $actions ?></div>
    <?php endif ?>
</div>
