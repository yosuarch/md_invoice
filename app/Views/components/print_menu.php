<?php
/**
 * "Print" dropdown with PDF documents (open in a new tab; a second link downloads).
 *
 * @var list<array{label: string, url: string, icon?: string}> $items
 */
?>
<div class="btn-group">
    <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="bi bi-printer me-1"></i><?= lang('App.print') ?>
    </button>
    <ul class="dropdown-menu dropdown-menu-end">
        <?php foreach ($items as $item) : ?>
            <li class="d-flex align-items-center">
                <a class="dropdown-item" href="<?= esc($item['url'], 'attr') ?>" target="_blank" rel="noopener">
                    <i class="bi bi-<?= esc($item['icon'] ?? 'file-earmark-pdf', 'attr') ?> me-2"></i><?= esc($item['label']) ?>
                </a>
                <a class="btn btn-link btn-sm px-2" hx-boost="false" href="<?= esc(download_url($item['url']), 'attr') ?>" title="<?= esc(lang('App.download'), 'attr') ?>" aria-label="<?= esc(lang('App.download') . ' ' . $item['label'], 'attr') ?>"><i class="bi bi-download"></i></a>
            </li>
        <?php endforeach ?>
    </ul>
</div>
