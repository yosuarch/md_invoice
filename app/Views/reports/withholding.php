<?php
/**
 * Withholding reconciliation report.
 *
 * @var array{rows: list<array<string, mixed>>, summary: array<string, array<string, mixed>>, totals: array<string, string>} $report
 * @var array{q: string, kind: string, state: string} $filters
 * @var string $from Y-m-d
 * @var string $to   Y-m-d
 */

use App\Services\Reports;

$query  = array_filter(['from' => $from, 'to' => $to] + $filters);
$csvUrl = site_url('reports/withholding') . '?' . http_build_query($query + ['export' => 'csv']);
$kinds  = array_combine(['PPN', 'PPH22', 'PPH23', 'PPH_FINAL'], array_map(static fn (string $k): string => code_label('taxKind', $k), ['PPN', 'PPH22', 'PPH23', 'PPH_FINAL']));
$badges = [
    'ok'            => 'bg-success-subtle text-success-emphasis',
    'missing_proof' => 'bg-warning-subtle text-warning-emphasis',
    'difference'    => 'bg-danger-subtle text-danger-emphasis',
    'not_withheld'  => 'bg-secondary-subtle text-secondary-emphasis',
    'unexpected'    => 'bg-info-subtle text-info-emphasis',
];
?>
<?= $this->extend('layouts/app_layout') ?>

<?= $this->section('title') ?><?= lang('Reports.recon') ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('components/page_header', [
    'title'      => lang('Reports.recon'),
    'subtitle'   => lang('Reports.reconSubtitle', [format_date($from), format_date($to)]),
    'breadcrumb' => [[lang('Reports.reports'), site_url('reports')], [lang('Reports.withholding'), '']],
    'actions'    => '<a href="' . esc($csvUrl, 'attr') . '" class="btn btn-outline-secondary" hx-boost="false"><i class="bi bi-filetype-csv me-1"></i>' . esc(lang('Reports.exportCsv')) . '</a>',
]) ?>

<form method="get" class="card shadow-sm mb-3" data-live-filter>
    <div class="card-body row g-2 align-items-end">
        <div class="col-6 col-md-2">
            <label for="from" class="form-label small mb-1"><?= lang('Reports.issuedFrom') ?></label>
            <input type="date" class="form-control" id="from" name="from" value="<?= esc($from, 'attr') ?>">
        </div>
        <div class="col-6 col-md-2">
            <label for="to" class="form-label small mb-1"><?= lang('Reports.to') ?></label>
            <input type="date" class="form-control" id="to" name="to" value="<?= esc($to, 'attr') ?>">
        </div>
        <div class="col-12 col-md-3">
            <label for="q" class="form-label small mb-1"><?= lang('App.search') ?></label>
            <input type="search" class="form-control" id="q" name="q" value="<?= esc($filters['q']) ?>" placeholder="<?= esc(lang('Reports.reconSearchHint'), 'attr') ?>">
        </div>
        <div class="col-6 col-md-2">
            <label for="kind" class="form-label small mb-1"><?= lang('App.tax') ?></label>
            <?= form_dropdown('kind', ['' => lang('App.all')] + $kinds, $filters['kind'], ['class' => 'form-select', 'id' => 'kind']) ?>
        </div>
        <div class="col-6 col-md-2">
            <label for="state" class="form-label small mb-1"><?= lang('Reports.state') ?></label>
            <?= form_dropdown('state', ['' => lang('App.all'), 'open' => lang('Reports.stateOpen')] + array_combine(array_keys(Reports::STATES), array_map(static fn (string $k): string => code_label('reconState', $k), array_keys(Reports::STATES))), $filters['state'], ['class' => 'form-select', 'id' => 'state']) ?>
        </div>
        <div class="col-12 col-md-1 d-grid">
            <button type="submit" class="btn btn-outline-primary"><i class="bi bi-search"></i><span class="visually-hidden"><?= lang('App.filter') ?></span></button>
        </div>
    </div>
</form>

<div id="list-results">
    <div class="row g-3 mb-3">
        <?php foreach ($report['summary'] as $kind => $s) : ?>
            <div class="col-6 col-lg-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body py-2">
                        <div class="small text-body-secondary"><?= esc($kinds[$kind] ?? $kind) ?></div>
                        <div class="fw-semibold"><?= esc(format_idr($s['withheld'], 0)) ?> <span class="small fw-normal text-body-secondary"><?= esc(lang('Reports.ofExpected', [format_idr($s['expected'], 0)])) ?></span></div>
                        <div class="small<?= $s['open'] > 0 ? ' text-warning-emphasis' : ' text-success-emphasis' ?>"><?= esc($s['open'] > 0 ? lang('Reports.xOfYOpen', [$s['open'], $s['count']]) : lang('Reports.allReconciled')) ?></div>
                    </div>
                </div>
            </div>
        <?php endforeach ?>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-dark">
                    <tr>
                        <th><?= lang('Payments.invoice') ?></th>
                        <th><?= lang('App.school') ?></th>
                        <th><?= lang('App.tax') ?></th>
                        <th class="text-end"><?= lang('Invoices.base') ?></th>
                        <th class="text-end"><?= lang('Invoices.expected') ?></th>
                        <th class="text-end"><?= lang('Invoices.withheld') ?></th>
                        <th><?= lang('Reports.proof') ?></th>
                        <th><?= lang('Reports.state') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report['rows'] as $r) : ?>
                        <tr>
                            <td class="text-nowrap">
                                <a href="<?= site_url('invoices/' . $r['invoice_id']) ?>" class="font-monospace fw-semibold text-decoration-none"><?= esc($r['invoice_number']) ?></a>
                                <div class="text-body-secondary"><?= esc(format_date($r['issue_date'])) ?></div>
                            </td>
                            <td><?= esc(up($r['customer_name'])) ?><?php if ($r['funding']) : ?><div class="text-body-secondary"><?= esc($r['funding']) ?></div><?php endif ?></td>
                            <td><?= esc($kinds[$r['tax_kind']] ?? $r['tax_kind']) ?><div class="text-body-secondary text-truncate" style="max-width: 16rem"><?= esc($r['description']) ?></div></td>
                            <td class="text-end text-nowrap"><?= esc(format_idr($r['base_amount'], 0)) ?></td>
                            <td class="text-end text-nowrap"><?= esc(format_idr($r['expected'], 0)) ?></td>
                            <td class="text-end text-nowrap fw-semibold"><?= esc(format_idr($r['withheld'], 0)) ?></td>
                            <td><?= $r['proofs'] !== [] ? esc(implode(', ', $r['proofs'])) : '<span class="text-body-secondary">-</span>' ?></td>
                            <td><span class="badge <?= $badges[$r['state']] ?>"><?= esc(code_label('reconState', $r['state'])) ?></span></td>
                        </tr>
                    <?php endforeach ?>
                    <?php if ($report['rows'] === []) : ?>
                        <tr><td colspan="8" class="text-center text-body-secondary py-4"><?= lang('Reports.noWithholding') ?></td></tr>
                    <?php endif ?>
                </tbody>
                <?php if ($report['rows'] !== []) : ?>
                    <tfoot class="table-light fw-semibold">
                        <tr>
                            <td colspan="4"><?= lang('App.total') ?></td>
                            <td class="text-end text-nowrap"><?= esc(format_idr($report['totals']['expected'], 0)) ?></td>
                            <td class="text-end text-nowrap"><?= esc(format_idr($report['totals']['withheld'], 0)) ?></td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                <?php endif ?>
            </table>
        </div>
    </div>
    <p class="small text-body-secondary mt-2 mb-0"><?= lang('Reports.reconFooter') ?></p>
</div>

<?= $this->endSection() ?>
