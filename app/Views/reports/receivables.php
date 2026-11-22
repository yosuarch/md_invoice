<?php
/**
 * Receivables aging report.
 *
 * @var array{rows: list<array<string, mixed>>, customers: list<array<string, mixed>>, totals: array<string, string>} $report
 * @var array{q: string, rep: string, funding: string, view: string} $filters
 * @var string             $today
 * @var array<int, string> $reps    Sales rep options (empty for users who only see their own records).
 * @var array<int, string> $funding Funding source options.
 */

use App\Services\Reports;

$buckets = Reports::BUCKETS;
$query   = array_filter(['q' => $filters['q'], 'rep' => $filters['rep'], 'funding' => $filters['funding']]);
$csvUrl  = site_url('reports/receivables') . '?' . http_build_query($query + ['export' => 'csv']);
$tabUrl  = static fn (string $view): string => site_url('reports/receivables') . '?' . http_build_query($query + ['view' => $view]);
$amount  = static fn (string $v): string => bccomp($v, '0', 2) === 0 ? '<span class="text-body-secondary">-</span>' : esc(format_idr($v, 0));
?>
<?= $this->extend('layouts/app_layout') ?>

<?= $this->section('title') ?><?= lang('Reports.aging') ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('components/page_header', [
    'title'      => lang('Reports.aging'),
    'subtitle'   => lang('Reports.agingSubtitle', [format_date($today)]),
    'breadcrumb' => [[lang('Reports.reports'), site_url('reports')], [lang('Reports.receivables'), '']],
    'actions'    => '<a href="' . esc($csvUrl, 'attr') . '" class="btn btn-outline-secondary" hx-boost="false"><i class="bi bi-filetype-csv me-1"></i>' . esc(lang('Reports.exportCsv')) . '</a>',
]) ?>

<form method="get" class="card shadow-sm mb-3" data-live-filter>
    <input type="hidden" name="view" value="<?= esc($filters['view'], 'attr') ?>">
    <div class="card-body row g-2 align-items-end">
        <div class="col-12 col-md-5">
            <label for="q" class="form-label small mb-1"><?= lang('App.search') ?></label>
            <input type="search" class="form-control" id="q" name="q" value="<?= esc($filters['q']) ?>" placeholder="<?= esc(lang('Reports.searchHint'), 'attr') ?>">
        </div>
        <?php if ($reps !== []) : ?>
            <div class="col-6 col-md-3">
                <label for="rep" class="form-label small mb-1"><?= lang('Proposals.salesRep') ?></label>
                <?= form_dropdown('rep', ['' => lang('App.all')] + array_map('up', $reps), $filters['rep'], ['class' => 'form-select', 'id' => 'rep']) ?>
            </div>
        <?php endif ?>
        <div class="col-6 col-md-3">
            <label for="funding" class="form-label small mb-1"><?= lang('Proposals.fundingSource') ?></label>
            <?= form_dropdown('funding', ['' => lang('App.all')] + $funding, $filters['funding'], ['class' => 'form-select', 'id' => 'funding']) ?>
        </div>
        <div class="col-12 col-md-1 d-grid">
            <button type="submit" class="btn btn-outline-primary"><i class="bi bi-search"></i><span class="visually-hidden"><?= lang('App.filter') ?></span></button>
        </div>
    </div>
</form>

<div id="list-results">
    <div class="row g-3 mb-3">
        <?php foreach (array_keys($buckets) as $key) : ?>
            <div class="col-6 col-md-4 col-xl">
                <div class="card shadow-sm h-100 aging-card aging-<?= esc($key, 'attr') ?>">
                    <div class="card-body py-2">
                        <div class="small text-body-secondary"><?= esc(code_label('bucket', $key)) ?></div>
                        <div class="fw-semibold"><?= esc(format_idr($report['totals'][$key], 0)) ?></div>
                    </div>
                </div>
            </div>
        <?php endforeach ?>
        <div class="col-12 col-md-4 col-xl">
            <div class="card shadow-sm h-100 border-primary">
                <div class="card-body py-2">
                    <div class="small text-body-secondary"><?= lang('Reports.totalOpen') ?></div>
                    <div class="fw-bold"><?= esc(format_idr($report['totals']['balance'], 0)) ?></div>
                    <div class="small text-body-secondary"><?= esc(lang('Reports.cashTaxes', [format_idr($report['totals']['cash_expected'], 0), format_idr($report['totals']['taxes_pending'], 0)])) ?></div>
                </div>
            </div>
        </div>
    </div>

    <ul class="nav nav-tabs mb-0">
        <li class="nav-item"><a class="nav-link<?= $filters['view'] === 'customers' ? ' active' : '' ?>" href="<?= esc($tabUrl('customers'), 'attr') ?>"><?= lang('Reports.perSchool', [count($report['customers'])]) ?></a></li>
        <li class="nav-item"><a class="nav-link<?= $filters['view'] === 'invoices' ? ' active' : '' ?>" href="<?= esc($tabUrl('invoices'), 'attr') ?>"><?= lang('Reports.perInvoice', [count($report['rows'])]) ?></a></li>
    </ul>

    <div class="card shadow-sm border-top-0 rounded-top-0">
        <div class="table-responsive">
            <?php if ($filters['view'] === 'customers') : ?>
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-dark">
                        <tr>
                            <th><?= lang('App.school') ?></th>
                            <th class="text-end"><?= lang('Reports.invoices') ?></th>
                            <?php foreach (array_keys($buckets) as $key) : ?><th class="text-end text-nowrap"><?= esc(code_label('bucket', $key)) ?></th><?php endforeach ?>
                            <th class="text-end"><?= lang('App.total') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($report['customers'] as $c) : ?>
                            <tr>
                                <td>
                                    <a href="<?= site_url('invoices') . '?' . http_build_query(['customer' => $c['customer_id'], 'status' => 'unpaid']) ?>" class="text-decoration-none fw-semibold"><?= esc(up($c['customer_name'])) ?></a>
                                    <?php if ($c['npsn']) : ?><div class="text-body-secondary">NPSN <?= esc($c['npsn']) ?></div><?php endif ?>
                                </td>
                                <td class="text-end"><?= (int) $c['invoices'] ?></td>
                                <?php foreach (array_keys($buckets) as $key) : ?>
                                    <td class="text-end text-nowrap<?= $key !== 'current' && bccomp($c[$key], '0', 2) > 0 ? ' text-danger' : '' ?>"><?= $amount($c[$key]) ?></td>
                                <?php endforeach ?>
                                <td class="text-end text-nowrap fw-semibold"><?= esc(format_idr($c['balance'], 0)) ?></td>
                            </tr>
                        <?php endforeach ?>
                        <?php if ($report['customers'] === []) : ?>
                            <tr><td colspan="<?= count($buckets) + 3 ?>" class="text-center text-body-secondary py-4"><?= lang('Reports.noOpen') ?></td></tr>
                        <?php endif ?>
                    </tbody>
                    <?php if ($report['customers'] !== []) : ?>
                        <tfoot class="table-light fw-semibold">
                            <tr>
                                <td><?= lang('App.total') ?></td>
                                <td class="text-end"><?= count($report['rows']) ?></td>
                                <?php foreach (array_keys($buckets) as $key) : ?><td class="text-end text-nowrap"><?= esc(format_idr($report['totals'][$key], 0)) ?></td><?php endforeach ?>
                                <td class="text-end text-nowrap"><?= esc(format_idr($report['totals']['balance'], 0)) ?></td>
                            </tr>
                        </tfoot>
                    <?php endif ?>
                </table>
            <?php else : ?>
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-dark">
                        <tr>
                            <th><?= lang('Payments.invoice') ?></th>
                            <th><?= lang('App.school') ?></th>
                            <th><?= lang('Invoices.dueDate') ?></th>
                            <th class="text-end"><?= lang('Reports.days') ?></th>
                            <th class="text-end"><?= lang('App.total') ?></th>
                            <th class="text-end"><?= lang('Reports.open') ?></th>
                            <th class="text-end" title="<?= esc(lang('Reports.taxesPendingTitle'), 'attr') ?>"><?= lang('Reports.taxesPending') ?></th>
                            <th class="text-end"><?= lang('Reports.cashExpected') ?></th>
                            <th><?= lang('App.status') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($report['rows'] as $r) : ?>
                            <tr>
                                <td class="text-nowrap"><a href="<?= site_url('invoices/' . (int) $r['id']) ?>" class="font-monospace fw-semibold text-decoration-none"><?= esc($r['invoice_number']) ?></a></td>
                                <td><?= esc(up($r['customer_name'])) ?><?php if ($r['funding_source_name']) : ?><div class="text-body-secondary"><?= esc($r['funding_source_name']) ?></div><?php endif ?></td>
                                <td class="text-nowrap"><?= esc(format_date($r['due_date'])) ?></td>
                                <td class="text-end<?= $r['days_past_due'] > 0 ? ' text-danger fw-semibold' : ' text-body-secondary' ?>"><?= $r['days_past_due'] > 0 ? (int) $r['days_past_due'] : '-' ?></td>
                                <td class="text-end text-nowrap"><?= esc(format_idr($r['grand_total'], 0)) ?></td>
                                <td class="text-end text-nowrap fw-semibold"><?= esc(format_idr($r['balance'], 0)) ?></td>
                                <td class="text-end text-nowrap"><?= $amount($r['taxes_pending']) ?></td>
                                <td class="text-end text-nowrap"><?= esc(format_idr($r['cash_expected'], 0)) ?></td>
                                <td><?= invoice_status_badge($r['status_code']) ?></td>
                            </tr>
                        <?php endforeach ?>
                        <?php if ($report['rows'] === []) : ?>
                            <tr><td colspan="9" class="text-center text-body-secondary py-4"><?= lang('Reports.noOpen') ?></td></tr>
                        <?php endif ?>
                    </tbody>
                </table>
            <?php endif ?>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
