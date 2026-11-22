<?php
/**
 * Order tracker: proposal → invoice → payment → delivery (App\Services\OrderFlow), plus the linked documents.
 *
 * @var array{steps: list<array<string, mixed>>, documents: list<array<string, mixed>>} $flow
 * @var string|null $current   URL of the page being shown (highlighted in the document list).
 * @var bool|null   $documents Show the linked documents list (default true).
 */
$documents ??= true;
$current   ??= '';

$styles = [
    'done'      => ['bg-success text-white', 'check-lg'],
    'current'   => ['bg-primary text-white', null],
    'pending'   => ['bg-body-secondary text-body-secondary', null],
    'cancelled' => ['bg-danger-subtle text-danger-emphasis', 'x-lg'],
    'skipped'   => ['bg-body-secondary text-body-secondary', 'dash-lg'],
];
?>
<div class="card shadow-sm mb-3 order-flow">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-diagram-3 me-1"></i><?= lang('Orders.tracker') ?></div>
    <div class="card-body">
        <ol class="order-flow-steps list-unstyled mb-0">
            <?php foreach ($flow['steps'] as $step) : ?>
                <?php [$class, $icon] = $styles[$step['state']] ?? $styles['pending'] ?>
                <li class="order-flow-step is-<?= esc($step['state'], 'attr') ?>">
                    <span class="order-flow-dot <?= $class ?>"><i class="bi bi-<?= esc($icon ?? $step['icon'], 'attr') ?>"></i></span>
                    <div class="order-flow-text">
                        <div class="fw-semibold small"><?= esc($step['label']) ?></div>
                        <?php if ($step['url'] !== null) : ?>
                            <a href="<?= esc($step['url'], 'attr') ?>" class="small text-decoration-none"><?= esc($step['detail']) ?></a>
                        <?php else : ?>
                            <span class="small text-body-secondary"><?= esc($step['detail']) ?></span>
                        <?php endif ?>
                    </div>
                </li>
            <?php endforeach ?>
        </ol>
    </div>
    <?php if ($documents && count($flow['documents']) > 1) : ?>
        <div class="list-group list-group-flush border-top">
            <?php foreach ($flow['documents'] as $doc) : ?>
                <a href="<?= esc($doc['url'], 'attr') ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center gap-2 small<?= $doc['url'] === $current ? ' active-doc' : '' ?>">
                    <span class="text-truncate">
                        <span class="text-body-secondary me-1"><?= esc($doc['type']) ?></span>
                        <span class="font-monospace fw-semibold"><?= esc($doc['number']) ?></span>
                        <?php if (! empty($doc['date'])) : ?><span class="text-body-secondary ms-1"><?= esc(format_date($doc['date'])) ?></span><?php endif ?>
                    </span>
                    <span class="text-nowrap">
                        <?php if ($doc['amount'] !== null) : ?><span class="me-2"><?= esc(str_starts_with($doc['amount'], '-') ? '−' . format_idr(substr($doc['amount'], 1)) : format_idr($doc['amount'])) ?></span><?php endif ?>
                        <?= $doc['status'] ?>
                    </span>
                </a>
            <?php endforeach ?>
        </div>
    <?php endif ?>
</div>
