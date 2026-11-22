<?php
/**
 * Edit an activity / follow-up.
 *
 * @var array<string, mixed>       $activity
 * @var list<array<string, mixed>> $contacts People of the school (links with person fields).
 * @var array<int, string>         $users
 * @var string                     $returnTo
 */

use App\Models\ActivityModel;
use App\Models\ContactPersonModel;
use App\Models\CustomerContactModel;

$aid      = (int) $activity['id'];
$occurred = form_value('occurred_at', $activity);
$occurred = $occurred !== '' ? date('Y-m-d\TH:i', strtotime($occurred)) : '';
?>
<?= $this->extend('layouts/app_layout') ?>

<?= $this->section('title') ?><?= lang('Activities.edit') ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<?= view('components/page_header', [
    'title'      => lang('Activities.edit'),
    'breadcrumb' => [[lang('Activities.activities'), site_url('activities')], [lang('App.edit'), '']],
]) ?>

<div class="card shadow-sm" style="max-width: 860px">
    <div class="card-body">
        <form action="<?= site_url('activities/' . $aid) ?>" method="post" class="row g-3" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="return_to" value="<?= esc($returnTo, 'attr') ?>">
            <input type="hidden" name="proposal_id" value="<?= esc((string) $activity['proposal_id'], 'attr') ?>">
            <input type="hidden" name="invoice_id" value="<?= esc((string) $activity['invoice_id'], 'attr') ?>">
            <div class="col-6 col-md-3">
                <label for="activity_type" class="form-label"><?= lang('Activities.type') ?></label>
                <select class="form-select" id="activity_type" name="activity_type">
                    <?php foreach (ActivityModel::TYPES as $t) : ?>
                        <option value="<?= $t ?>"<?= form_value('activity_type', $activity) === $t ? ' selected' : '' ?>><?= esc(lang('Activities.type_' . $t)) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-12 col-md-9">
                <label for="subject" class="form-label"><?= lang('Activities.subject') ?> <span class="text-danger">*</span></label>
                <input type="text" class="form-control<?= invalid('subject') ?>" id="subject" name="subject" maxlength="200" required value="<?= esc(form_value('subject', $activity)) ?>">
                <?= field_error('subject') ?>
            </div>
            <div class="col-6 col-md-4">
                <label for="occurred_at" class="form-label"><?= lang('Activities.when') ?></label>
                <input type="datetime-local" class="form-control<?= invalid('occurred_at') ?>" id="occurred_at" name="occurred_at" value="<?= esc($occurred) ?>">
                <?= field_error('occurred_at') ?>
            </div>
            <div class="col-6 col-md-4">
                <label for="due_on" class="form-label"><?= lang('Activities.dueOn') ?></label>
                <input type="date" class="form-control<?= invalid('due_on') ?>" id="due_on" name="due_on" value="<?= esc(form_value('due_on', $activity)) ?>">
                <?= field_error('due_on') ?>
            </div>
            <div class="col-12 col-md-4">
                <label for="assigned_to" class="form-label"><?= lang('Activities.assignedTo') ?></label>
                <select class="form-select" id="assigned_to" name="assigned_to">
                    <?php foreach ($users as $uid => $uname) : ?>
                        <option value="<?= $uid ?>"<?= form_value('assigned_to', $activity) === (string) $uid ? ' selected' : '' ?>><?= esc($uname) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-12">
                <label for="contact_id" class="form-label"><?= lang('Activities.person') ?></label>
                <select class="form-select<?= invalid('contact_id') ?>" id="contact_id" name="contact_id">
                    <option value=""><?= lang('Activities.noPerson') ?></option>
                    <?php foreach ($contacts as $c) : ?>
                        <?php $role = CustomerContactModel::roleLabel($c) ?>
                        <option value="<?= (int) $c['contact_id'] ?>"<?= form_value('contact_id', $activity) === (string) $c['contact_id'] ? ' selected' : '' ?>>
                            <?= esc(ContactPersonModel::displayName($c)) ?><?= $role !== '' ? ' (' . esc($role) . ')' : '' ?><?= $c['ended_on'] ? ' · ' . esc(lang('Contacts.left')) : '' ?>
                        </option>
                    <?php endforeach ?>
                </select>
                <?= field_error('contact_id') ?>
            </div>
            <div class="col-12">
                <label for="notes" class="form-label"><?= lang('Activities.notes') ?></label>
                <textarea class="form-control" id="notes" name="notes" rows="4" maxlength="5000"><?= esc(form_value('notes', $activity)) ?></textarea>
            </div>
            <div class="col-12 d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i><?= lang('App.save') ?></button>
                <a href="<?= site_url($returnTo) ?>" class="btn btn-outline-secondary"><?= lang('App.cancel') ?></a>
            </div>
        </form>
        <hr>
        <form action="<?= site_url('activities/' . $aid . '/delete') ?>" method="post" class="d-inline" data-confirm="<?= esc(lang('Activities.deleteConfirm'), 'attr') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="return_to" value="<?= esc($returnTo, 'attr') ?>">
            <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash me-1"></i><?= lang('App.delete') ?></button>
        </form>
    </div>
</div>

<?= $this->endSection() ?>
