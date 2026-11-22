<?php
/**
 * Negotiation history of a proposal (App\Services\ProposalRevisions::history): each revision sent, compared with the
 * one before, plus the changes not yet sent.
 *
 * @var array{revisions: list<array<string, mixed>>, pending: ?array<string, mixed>} $negotiation
 * @var int $pid Proposal id.
 */

/**
 * One value of a line field, formatted for display.
 */
$fmt = static fn (string $field, string $value, string $unit): string => match ($field) {
    'quantity'    => format_qty($value) . ' ' . $unit,
    'title'       => up($value),
    'description' => $value === '' ? '-' : $value,
    'tax_rate'    => rtrim(rtrim($value, '0'), '.') . '%',
    default    => format_idr($value),
};

/**
 * Change of the grand total as a coloured badge (lower = good for the customer, shown green).
 */
$delta = static function (?string $d): string {
    if ($d === null || bccomp($d, '0', 2) === 0) {
        return $d === null ? '' : '<span class="badge bg-secondary-subtle text-secondary-emphasis">' . esc(lang('Proposals.noTotalChange')) . '</span>';
    }

    $down = bccomp($d, '0', 2) < 0;

    return '<span class="badge ' . ($down ? 'bg-success-subtle text-success-emphasis' : 'bg-warning-subtle text-warning-emphasis') . '">'
        . ($down ? '−' : '+') . esc(format_idr(ltrim($d, '-'))) . '</span>';
};

/** Field label of a line change in the screen language. */
$fieldLabel = static fn (string $field): string => match ($field) {
    'quantity'    => lang('App.quantity'),
    'unit_price'  => lang('App.unitPrice'),
    'discount'    => lang('App.discount'),
    'title'       => lang('App.title'),
    'description' => lang('App.description'),
    'tax_rate'    => lang('App.tax'),
    default       => $field,
};

/**
 * Line differences as a compact list.
 *
 * @param list<array<string, mixed>> $diff
 */
$renderDiff = static function (array $diff) use ($fmt, $fieldLabel): string {
    if ($diff === []) {
        return '<div class="text-body-secondary">' . esc(lang('Proposals.noItemChanges')) . '</div>';
    }

    $html = '<ul class="list-unstyled mb-0 revision-diff">';

    foreach ($diff as $d) {
        $html .= '<li class="py-1">';

        if ($d['kind'] === 'added') {
            $html .= '<span class="badge bg-success-subtle text-success-emphasis me-1">' . esc(lang('Proposals.added')) . '</span>' . esc(up($d['title']))
                . ' <span class="text-body-secondary">' . esc(format_qty($d['new']['quantity']) . ' ' . $d['unit'] . ' × ' . format_price($d['new']['unit_price'])) . '</span>';
        } elseif ($d['kind'] === 'removed') {
            $html .= '<span class="badge bg-danger-subtle text-danger-emphasis me-1">' . esc(lang('Proposals.removed')) . '</span><span class="text-decoration-line-through">' . esc(up($d['title'])) . '</span>';
        } else {
            $html .= '<span class="badge bg-info-subtle text-info-emphasis me-1">' . esc(lang('Proposals.changed')) . '</span>' . esc(up($d['title'])) . '<div class="ms-3">';

            foreach ($d['changes'] as $c) {
                $html .= '<div class="text-body-secondary">' . esc($fieldLabel($c['field'])) . ': <span class="text-decoration-line-through">' . esc($fmt($c['field'], $c['old'], $d['unit']))
                    . '</span> → <span class="text-body fw-semibold">' . esc($fmt($c['field'], $c['new'], $d['unit'])) . '</span></div>';
            }

            $html .= '</div>';
        }

        $html .= '</li>';
    }

    return $html . '</ul>';
};
?>
<?php if ($negotiation['revisions'] !== []) : ?>
    <div class="card shadow-sm mb-3" id="negotiation">
        <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
            <span><i class="bi bi-arrow-left-right me-1"></i><?= lang('Proposals.negotiation') ?></span>
            <span class="small text-body-secondary fw-normal"><?= count($negotiation['revisions']) === 1 ? lang('Proposals.revisionOne') : lang('Proposals.revisions', [count($negotiation['revisions'])]) ?></span>
        </div>
        <div class="list-group list-group-flush small">
            <?php if ($negotiation['pending'] !== null) : ?>
                <div class="list-group-item bg-warning-subtle">
                    <div class="d-flex flex-wrap justify-content-between gap-2">
                        <span class="fw-semibold"><i class="bi bi-pencil-square me-1"></i><?= lang('Proposals.notSentYet') ?></span>
                        <span><?= esc(format_idr($negotiation['pending']['grand_total'])) ?> <?= $delta($negotiation['pending']['delta']) ?></span>
                    </div>
                    <div class="mt-1"><?= $renderDiff($negotiation['pending']['diff']) ?></div>
                </div>
            <?php endif ?>
            <?php foreach ($negotiation['revisions'] as $i => $rev) : ?>
                <?php $open = $i === 0 && $negotiation['pending'] === null ?>
                <div class="list-group-item">
                    <div class="d-flex flex-wrap justify-content-between gap-2">
                        <span>
                            <span class="fw-semibold"><?= lang('Proposals.revision', [(int) $rev['revision_no']]) ?></span>
                            <?= $rev['status_code'] === 'STORE_APPROVE' ? '<span class="badge bg-success ms-1">' . esc(lang('Proposals.approvedVersion')) . '</span>' : '' ?>
                            <span class="text-body-secondary ms-1"><?= esc(format_date($rev['created_at'], true)) ?> · <?= esc($rev['full_name'] ? up($rev['full_name']) : ($rev['username'] ?? '-')) ?></span>
                        </span>
                        <span><?= esc(format_idr($rev['grand_total'])) ?> <?= $delta($rev['delta']) ?></span>
                    </div>
                    <?php if (! empty($rev['note'])) : ?><div class="mt-1 text-break"><?= nl2br(esc($rev['note'])) ?></div><?php endif ?>
                    <?php if ((int) $rev['revision_no'] > 1) : ?>
                        <details class="mt-1"<?= $open ? ' open' : '' ?>>
                            <summary class="text-primary"><?= lang('Proposals.changesFrom', [(int) $rev['revision_no'] - 1, count($rev['diff'])]) ?></summary>
                            <div class="mt-1"><?= $renderDiff($rev['diff']) ?></div>
                        </details>
                    <?php endif ?>
                </div>
            <?php endforeach ?>
        </div>
    </div>
<?php endif ?>
