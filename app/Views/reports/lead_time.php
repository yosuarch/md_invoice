<?php
/**
 * Lead-time report: per step average / median / fastest / slowest, and one row per finished order.
 *
 * @var array{rows: list<array<string, mixed>>, stats: array<string, array{count: int, avg: ?float, median: ?float, min: ?float, max: ?float}>} $report
 * @var array{q: string, rep: string, level: string, funding: string} $filters
 * @var string             $from    Y-m-d
 * @var string             $to      Y-m-d
 * @var array<int, string> $reps    Sales rep options (empty for users who only see their own records).
 * @var array<int, string> $levels  School levels.
 * @var array<int, string> $funding Funding sources.
 */

use App\Services\LeadTime;

$query  = array_filter(['from' => $from, 'to' => $to] + $filters);
$csvUrl = site_url('reports/lead-time') . '?' . http_build_query($query + ['export' => 'csv']);
$steps  = array_keys(LeadTime::STEPS);

/** Duration in seconds as "2,5 d" / "3,0 h" / "12 min". */
$dur = static function (int|float|null $seconds): string {
    $p = LeadTime::parts($seconds === null ? null : (int) round($seconds));

    return $p === null ? '–' : lang('Reports.dur_' . $p[1], [$p[0]]);
};

// Step with the longest median: highlighted (where most time goes).
$slowest = null;

foreach ($steps as $s) {
    if ($s !== 'total' && $report['stats'][$s]['median'] !== null && ($slowest === null || $report['stats'][$s]['median'] > $report['stats'][$slowest]['median'])) {
        $slowest = $s;
    }
}
?>
<?= $this->extend('layouts/app_layout') ?>

<?= $this->section('title') ?><?= lang('Reports.leadTime') ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('components/page_header', [
    'title'      => lang('Reports.leadTime'),
    'subtitle'   => lang('Reports.leadTimeSubtitle', [format_date($from), format_date($to)]),
    'breadcrumb' => [[lang('Reports.reports'), site_url('reports')], [lang('Reports.leadTime'), '']],
    'actions'    => '<a href="' . esc($csvUrl, 'attr') . '" class="btn btn-outline-secondary" hx-boost="false"><i class="bi bi-filetype-csv me-1"></i>' . esc(lang('Reports.exportCsv')) . '</a>',
]) ?>

<form method="get" class="card shadow-sm mb-3" data-live-filter>
    <div class="card-body row g-2 align-items-end">
        <div class="col-6 col-md-2">
            <label for="from" class="form-label small mb-1"><?= lang('Reports.finishedFrom') ?></label>
            <input type="date" class="form-control" id="from" name="from" value="<?= esc($from, 'attr') ?>">
        </div>
        <div class="col-6 col-md-2">
            <label for="to" class="form-label small mb-1"><?= lang('Reports.to') ?></label>
            <input type="date" class="form-control" id="to" name="to" value="<?= esc($to, 'attr') ?>">
        </div>
        <div class="col-12 col-md-2">
            <label for="q" class="form-label small mb-1"><?= lang('App.search') ?></label>
            <input type="search" class="form-control" id="q" name="q" value="<?= esc($filters['q']) ?>" placeholder="<?= esc(lang('Reports.reconSearchHint'), 'attr') ?>">
        </div>
        <?php if ($reps !== []) : ?>
            <div class="col-6 col-md-2">
                <label for="rep" class="form-label small mb-1"><?= lang('Reports.csvSalesRep') ?></label>
                <?= form_dropdown('rep', ['' => lang('App.all')] + array_map('up', $reps), $filters['rep'], ['class' => 'form-select', 'id' => 'rep']) ?>
            </div>
        <?php endif ?>
        <div class="col-6 col-md-<?= $reps !== [] ? '1' : '2' ?>">
            <label for="level" class="form-label small mb-1"><?= lang('Reports.schoolLevel') ?></label>
            <?= form_dropdown('level', ['' => lang('App.all')] + $levels, $filters['level'], ['class' => 'form-select', 'id' => 'level']) ?>
        </div>
        <div class="col-6 col-md-2">
            <label for="funding" class="form-label small mb-1"><?= lang('Reports.csvFunding') ?></label>
            <?= form_dropdown('funding', ['' => lang('App.all')] + $funding, $filters['funding'], ['class' => 'form-select', 'id' => 'funding']) ?>
        </div>
        <div class="col-6 col-md-1 d-grid">
            <button type="submit" class="btn btn-outline-primary"><i class="bi bi-search"></i><span class="visually-hidden"><?= lang('App.filter') ?></span></button>
        </div>
    </div>
</form>

<div id="list-results">
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold"><?= lang('Reports.step') ?> · <?= lang('Reports.orders') ?>: <?= count($report['rows']) ?></div>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th><?= lang('Reports.step') ?></th>
                        <th class="text-end"><?= lang('Reports.orders') ?></th>
                        <th class="text-end"><?= lang('Reports.average') ?></th>
                        <th class="text-end"><?= lang('Reports.median') ?></th>
                        <th class="text-end d-none d-sm-table-cell"><?= lang('Reports.min') ?></th>
                        <th class="text-end d-none d-sm-table-cell"><?= lang('Reports.max') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($steps as $s) : ?>
                        <?php $st = $report['stats'][$s] ?>
                        <tr class="<?= $s === 'total' ? 'fw-semibold table-light' : '' ?>">
                            <td>
                                <?= lang('Reports.step_' . $s) ?>
                                <?php if ($s === $slowest) : ?><span class="badge bg-warning-subtle text-warning-emphasis border ms-1"><i class="bi bi-hourglass-split"></i></span><?php endif ?>
                            </td>
                            <td class="text-end"><?= $st['count'] ?></td>
                            <td class="text-end"><?= esc($dur($st['avg'])) ?></td>
                            <td class="text-end"><?= esc($dur($st['median'])) ?></td>
                            <td class="text-end text-body-secondary d-none d-sm-table-cell"><?= esc($dur($st['min'])) ?></td>
                            <td class="text-end d-none d-sm-table-cell"><?= esc($dur($st['max'])) ?></td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white small text-body-secondary"><i class="bi bi-info-circle me-1"></i><?= lang('Reports.leadTimeHelp') ?></div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white fw-semibold"><?= lang('Reports.perOrder', [count($report['rows'])]) ?></div>
        <?php if ($report['rows'] === []) : ?>
            <div class="card-body text-body-secondary small"><?= lang('Reports.noFinished') ?></div>
        <?php else : ?>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th><?= lang('Reports.csvProposal') ?></th>
                            <th><?= lang('Reports.csvCustomer') ?></th>
                            <th><?= lang('Reports.finished') ?></th>
                            <?php foreach ($steps as $s) : ?>
                                <th class="text-end text-nowrap"><?= lang('Reports.stepShort_' . $s) ?></th>
                            <?php endforeach ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($report['rows'] as $r) : ?>
                            <tr>
                                <td class="text-nowrap"><a href="<?= site_url('proposals/' . (int) $r['id']) ?>" class="font-monospace"><?= esc($r['cpl_id']) ?></a></td>
                                <td><?= esc(up($r['customer_name'])) ?><?php if (! empty($r['rep_name'])) : ?><div class="small text-body-secondary"><?= esc(up($r['rep_name'])) ?></div><?php endif ?></td>
                                <td class="text-nowrap small"><?= esc(format_date($r['finished'])) ?></td>
                                <?php foreach ($steps as $s) : ?>
                                    <?php $noGoods = ! $r['has_goods'] && in_array($s, LeadTime::DELIVERY_STEPS, true) ?>
                                    <td class="text-end text-nowrap<?= $s === 'total' ? ' fw-semibold' : '' ?>"><?= $noGoods ? '<span class="text-body-secondary">·</span>' : esc($dur($r['steps'][$s]['seconds'])) ?></td>
                                <?php endforeach ?>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        <?php endif ?>
    </div>
</div>

<?= $this->endSection() ?>
