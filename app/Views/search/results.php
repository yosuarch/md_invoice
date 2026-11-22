<?php
/**
 * Quick search results (top bar dropdown and the full search page).
 *
 * @var string $q
 * @var list<array{label: string, icon: string, url: string, items: list<array{title: string, detail: string, url: string}>}> $results
 */
?>
<?php if (mb_strlen($q) < 2) : ?>
    <div class="px-3 py-2 small text-body-secondary"><?= lang('Dashboard.minChars') ?></div>
<?php elseif ($results === []) : ?>
    <div class="px-3 py-2 small text-body-secondary"><?= esc(lang('Dashboard.nothingFound', [$q])) ?></div>
<?php else : ?>
    <?php foreach ($results as $group) : ?>
        <div class="search-group">
            <div class="d-flex justify-content-between align-items-center px-3 pt-2 pb-1 small">
                <span class="text-uppercase fw-semibold text-body-secondary"><i class="bi bi-<?= esc($group['icon'], 'attr') ?> me-1"></i><?= esc($group['label']) ?></span>
                <a href="<?= esc($group['url'], 'attr') ?>" class="text-decoration-none"><?= lang('Dashboard.allResults') ?></a>
            </div>
            <?php foreach ($group['items'] as $item) : ?>
                <a href="<?= esc($item['url'], 'attr') ?>" class="search-item d-block px-3 py-1 text-decoration-none">
                    <div class="small fw-semibold text-body text-truncate"><?= esc(up($item['title'])) ?></div>
                    <div class="small text-body-secondary text-truncate"><?= esc(up($item['detail'])) ?></div>
                </a>
            <?php endforeach ?>
        </div>
    <?php endforeach ?>
<?php endif ?>
