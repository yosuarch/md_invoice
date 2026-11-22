<?php
/**
 * @var list<array<string, mixed>>      $stats   Stat cards (see components/stat_card).
 * @var list<array<string, mixed>>|null $dueSoon Unpaid invoices by due date (null without invoices.view).
 * @var string|null                     $error
 * @var list<array<string, mixed>>      $pipeline Order pipeline work queues (stat cards with links).
 */

use App\Models\InvoiceModel;
use App\Services\InvoiceWorkflow;
use App\Services\ProposalWorkflow;
?>
<?= $this->extend('layouts/app_layout') ?>

<?= $this->section('title') ?><?= lang('Dashboard.dashboard') ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<?php if (isset($error)) : ?>
    <div class="alert alert-danger"><?= esc($error) ?></div>
<?php endif ?>
<div class="row g-3 mb-4">
    <?php foreach ($stats as $stat) : ?>
        <div class="col-12 col-sm-6 col-xl-3">
            <?= view('components/stat_card', ['stat' => $stat]) ?>
        </div>
    <?php endforeach ?>
</div>

<?php if ($pipeline !== []) : ?>
    <h2 class="h6 text-body-secondary text-uppercase small fw-semibold mb-2"><?= lang('Dashboard.pipeline') ?></h2>
    <div class="row g-3 mb-4">
        <?php foreach ($pipeline as $card) : ?>
            <div class="col-12 col-sm-6 col-xl-3">
                <?= view('components/stat_card', ['stat' => array_merge($card, $card['value'] === 0 ? ['color' => 'secondary'] : [])]) ?>
            </div>
        <?php endforeach ?>
    </div>
<?php endif ?>

<div class="row g-3">
    <?php if ($dueSoon !== null) : ?>
        <div class="col-12 col-xl-8">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-semibold"><?= lang('Dashboard.unpaidByDue') ?></span>
                    <a href="<?= site_url('invoices?status=unpaid') ?>" class="btn btn-outline-primary btn-sm"><?= lang('Dashboard.viewAll') ?></a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped align-middle mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th><?= lang('Payments.invoice') ?></th>
                                    <th><?= lang('App.customer') ?></th>
                                    <th><?= lang('Invoices.dueDate') ?></th>
                                    <th class="text-end"><?= lang('Invoices.balance') ?></th>
                                    <th><?= lang('App.status') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dueSoon as $inv) : ?>
                                    <?php $pastDue = InvoiceModel::isPastDue($inv) ?>
                                    <tr class="position-relative">
                                        <td class="text-nowrap"><a href="<?= site_url('invoices/' . $inv['id']) ?>" class="font-monospace fw-semibold text-decoration-none stretched-link"><?= esc($inv['invoice_number']) ?></a></td>
                                        <td><?= esc(up($inv['customer_name'])) ?></td>
                                        <td class="small text-nowrap<?= $pastDue ? ' text-danger fw-semibold' : '' ?>"><?= esc(format_date($inv['due_date'])) ?></td>
                                        <td class="text-end text-nowrap"><?= esc(format_idr(InvoiceModel::balance($inv))) ?></td>
                                        <td><?= invoice_status_badge($inv['status_code']) ?></td>
                                    </tr>
                                <?php endforeach ?>
                                <?php if ($dueSoon === []) : ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-body-secondary py-4">
                                            <i class="bi bi-check2-circle fs-3 d-block mb-1"></i>
                                            <?= lang('Dashboard.noUnpaid') ?>
                                        </td>
                                    </tr>
                                <?php endif ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    <?php endif ?>

    <div class="col-12 col-xl-4">
        <?php if ($followUps !== null) : ?>
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-semibold"><i class="bi bi-calendar-check me-1"></i><?= lang('Activities.myFollowUps') ?></span>
                    <a href="<?= site_url('activities') ?>" class="btn btn-link btn-sm"><?= lang('Activities.viewAllLink') ?></a>
                </div>
                <div class="list-group list-group-flush">
                    <?php $users = \App\Services\UserService::activeOptions() ?>
                    <?php foreach ($followUps as $a) : ?>
                        <?= view('components/activity_item', ['a' => $a, 'showSchool' => true, 'returnTo' => '/', 'users' => $users], ['saveData' => false]) ?>
                    <?php endforeach ?>
                    <?php if ($followUps === []) : ?><div class="list-group-item small text-body-secondary"><?= lang('Activities.nothingDue') ?></div><?php endif ?>
                </div>
            </div>
        <?php endif ?>
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold"><?= lang('Dashboard.legend') ?></div>
            <div class="card-body">
                <div class="small text-body-secondary mb-1"><?= lang('Proposals.proposal') ?></div>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <?php foreach (array_keys(ProposalWorkflow::LABELS) as $code) : ?><?= proposal_status_badge($code) ?><?php endforeach ?>
                </div>
                <div class="small text-body-secondary mb-1"><?= lang('Payments.invoice') ?></div>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach (array_keys(InvoiceWorkflow::LABELS) as $code) : ?><?= invoice_status_badge($code) ?><?php endforeach ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
