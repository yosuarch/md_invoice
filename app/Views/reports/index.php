<?php
/**
 * Reports overview.
 *
 * @var array<string, string> $receivables Totals of the receivables report.
 * @var string                $pastDue     Open balance past its due date.
 * @var array<string, string> $withholding Expected / withheld this month.
 * @var int                   $openTaxes   Withholding lines of this month that are not reconciled.
 */
?>
<?= $this->extend('layouts/app_layout') ?>

<?= $this->section('title') ?><?= lang('Reports.reports') ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('components/page_header', ['title' => lang('Reports.reports'), 'subtitle' => lang('Reports.subtitle')]) ?>

<div class="row g-3">
    <div class="col-12 col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h2 class="h5"><i class="bi bi-hourglass-split me-1"></i><?= lang('Reports.aging') ?></h2>
                <p class="small text-body-secondary"><?= lang('Reports.agingText') ?></p>
                <dl class="row small mb-3">
                    <dt class="col-6 fw-normal text-body-secondary"><?= lang('Reports.openBalance') ?></dt><dd class="col-6 text-end fw-semibold"><?= esc(format_idr($receivables['balance'])) ?></dd>
                    <dt class="col-6 fw-normal text-body-secondary"><?= lang('Reports.pastDue') ?></dt><dd class="col-6 text-end<?= bccomp($pastDue, '0', 2) > 0 ? ' text-danger fw-semibold' : '' ?>"><?= esc(format_idr($pastDue)) ?></dd>
                    <dt class="col-6 fw-normal text-body-secondary"><?= lang('Reports.cashExpected') ?></dt><dd class="col-6 text-end mb-0"><?= esc(format_idr($receivables['cash_expected'])) ?></dd>
                </dl>
                <a href="<?= site_url('reports/receivables') ?>" class="btn btn-primary btn-sm"><?= lang('Reports.openReport') ?></a>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h2 class="h5"><i class="bi bi-percent me-1"></i><?= lang('Reports.recon') ?></h2>
                <p class="small text-body-secondary"><?= lang('Reports.reconText') ?></p>
                <dl class="row small mb-3">
                    <dt class="col-6 fw-normal text-body-secondary"><?= lang('Reports.expectedMonth') ?></dt><dd class="col-6 text-end fw-semibold"><?= esc(format_idr($withholding['expected'])) ?></dd>
                    <dt class="col-6 fw-normal text-body-secondary"><?= lang('Reports.recorded') ?></dt><dd class="col-6 text-end"><?= esc(format_idr($withholding['withheld'])) ?></dd>
                    <dt class="col-6 fw-normal text-body-secondary"><?= lang('Reports.toFollowUp') ?></dt><dd class="col-6 text-end mb-0<?= $openTaxes > 0 ? ' text-warning-emphasis fw-semibold' : '' ?>"><?= $openTaxes ?></dd>
                </dl>
                <a href="<?= site_url('reports/withholding') ?>" class="btn btn-primary btn-sm"><?= lang('Reports.openReport') ?></a>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h2 class="h5"><i class="bi bi-stopwatch me-1"></i><?= lang('Reports.leadTime') ?></h2>
                <p class="small text-body-secondary"><?= lang('Reports.leadTimeText') ?></p>
                <a href="<?= site_url('reports/lead-time') ?>" class="btn btn-primary btn-sm"><?= lang('Reports.openReport') ?></a>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
