<?php
/**
 * Stat card component.
 *
 * @var array{label: string, value: int|string, icon: string, color: string, note?: string, url?: string} $stat
 *      note: small line under the value; url: makes the card a link.
 */
?>
<div class="card stat-card shadow-sm h-100<?= ! empty($stat['url']) ? ' position-relative' : '' ?>">
    <div class="card-body d-flex align-items-center gap-3">
        <span class="stat-icon bg-<?= esc($stat['color'], 'attr') ?>-subtle text-<?= esc($stat['color'], 'attr') ?>">
            <i class="bi bi-<?= esc($stat['icon'], 'attr') ?>"></i>
        </span>
        <div>
            <div class="small text-body-secondary">
                <?php if (! empty($stat['url'])) : ?>
                    <a href="<?= esc($stat['url'], 'attr') ?>" class="stretched-link text-reset text-decoration-none"><?= esc($stat['label']) ?></a>
                <?php else : ?>
                    <?= esc($stat['label']) ?>
                <?php endif ?>
            </div>
            <div class="stat-value fs-4 fw-semibold"><?= esc((string) $stat['value']) ?></div>
            <?php if (! empty($stat['note'])) : ?><div class="small text-body-secondary"><?= esc($stat['note']) ?></div><?php endif ?>
        </div>
    </div>
</div>
