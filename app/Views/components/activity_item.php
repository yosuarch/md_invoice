<?php
/**
 * One activity / follow-up in a timeline or list (App\Models\ActivityModel::withDetails row).
 *
 * @var array<string, mixed> $a          Activity.
 * @var bool|null            $showSchool Show the school name (lists across schools).
 * @var string               $returnTo   Path to return to after completing / editing.
 * @var array<int, string>   $users      Staff options (next follow-up).
 */

use App\Models\ActivityModel;
use App\Services\ActivityService;

$showSchool ??= false;
$open     = ActivityModel::isOpen($a);
$today    = date('Y-m-d');
$overdue  = $open && $a['due_on'] < $today;
$dueToday = $open && $a['due_on'] === $today;
$icon     = ActivityModel::ICONS[$a['activity_type']] ?? 'dot';
$canEdit  = user_can('crm.manage') && ActivityService::canEdit($a, (int) auth()->id());
$name     = static fn (?string $full, ?string $user): string => $full ? up($full) : (string) $user;
$aid      = (int) $a['id'];
?>
<div class="list-group-item activity-item<?= $open ? ' is-open' : '' ?><?= $overdue ? ' is-overdue' : '' ?>">
    <div class="d-flex gap-2">
        <span class="activity-icon<?= $open ? ' text-primary' : ' text-body-secondary' ?>" title="<?= esc(lang('Activities.type_' . $a['activity_type']), 'attr') ?>"><i class="bi bi-<?= esc($icon, 'attr') ?>"></i></span>
        <div class="flex-grow-1 min-w-0">
            <div class="d-flex flex-wrap justify-content-between gap-1">
                <div class="fw-semibold text-break">
                    <?php if ($open) : ?><span class="badge <?= $overdue ? 'bg-danger' : ($dueToday ? 'bg-warning text-dark' : 'bg-primary-subtle text-primary-emphasis border') ?> me-1"><?= esc(lang('Activities.followUp')) ?></span><?php endif ?>
                    <?= esc($a['subject']) ?>
                </div>
                <div class="small text-nowrap<?= $overdue ? ' text-danger fw-semibold' : ' text-body-secondary' ?>">
                    <?php if ($open) : ?>
                        <i class="bi bi-calendar-event me-1"></i><?= esc(lang('Activities.dueDate', [format_date($a['due_on'])])) ?><?= $overdue ? ' · ' . esc(lang('Activities.overdue')) : ($dueToday ? ' · ' . esc(lang('Activities.dueToday')) : '') ?>
                    <?php elseif ($a['occurred_at'] !== null) : ?>
                        <?= esc(format_date($a['occurred_at'], true)) ?>
                    <?php endif ?>
                </div>
            </div>
            <div class="small text-body-secondary">
                <?= esc(lang('Activities.type_' . $a['activity_type'])) ?>
                <?php if ($showSchool) : ?> · <a href="<?= site_url('customers/' . $a['customer_id']) ?>" class="text-reset"><?= esc(up($a['customer_name'])) ?></a><?php endif ?>
                <?php if ($a['contact_id']) : ?> · <a href="<?= site_url('contacts/' . $a['contact_id']) ?>" class="text-reset"><i class="bi bi-person me-1"></i><?= esc(up(trim($a['cp_first_name'] . ' ' . ($a['cp_last_name'] ?? '')))) ?></a><?php endif ?>
                <?php if ($a['proposal_id']) : ?> · <a href="<?= site_url('proposals/' . $a['proposal_id']) ?>" class="text-reset font-monospace"><?= esc($a['proposal_number']) ?></a><?php endif ?>
                <?php if ($a['invoice_id']) : ?> · <a href="<?= site_url('invoices/' . $a['invoice_id']) ?>" class="text-reset font-monospace"><?= esc($a['invoice_number']) ?></a><?php endif ?>
                · <?= esc($open ? lang('Activities.for', [$name($a['assignee_name'], $a['assignee_username'])]) : lang('Activities.by', [$name($a['creator_name'], $a['creator_username'])])) ?>
            </div>
            <?php if (! empty($a['notes'])) : ?><div class="small mt-1 text-break"><?= nl2br(esc($a['notes'])) ?></div><?php endif ?>
            <?php if (! $open && $a['due_on'] !== null && $a['done_at'] !== null) : ?>
                <div class="small mt-1 text-success-emphasis"><i class="bi bi-check2-circle me-1"></i><?= esc(lang('Activities.doneOn', [format_date($a['done_at'], true)])) ?><?= ! empty($a['outcome']) ? ': ' . nl2br(esc($a['outcome'])) : '' ?></div>
            <?php endif ?>

            <?php if ($open && user_can('crm.manage')) : ?>
                <details class="mt-2 activity-done">
                    <summary class="btn btn-sm btn-outline-success"><i class="bi bi-check2 me-1"></i><?= esc(lang('Activities.markDone')) ?></summary>
                    <form action="<?= site_url('activities/' . $aid . '/complete') ?>" method="post" class="row g-2 mt-1">
                        <?= csrf_field() ?>
                        <input type="hidden" name="return_to" value="<?= esc($returnTo, 'attr') ?>">
                        <div class="col-12">
                            <textarea class="form-control form-control-sm" name="outcome" rows="2" maxlength="5000" placeholder="<?= esc(lang('Activities.outcomeHint'), 'attr') ?>" aria-label="<?= esc(lang('Activities.outcome'), 'attr') ?>"></textarea>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label small mb-0" for="nd-<?= $aid ?>"><?= esc(lang('Activities.nextDue')) ?></label>
                            <input type="date" class="form-control form-control-sm" id="nd-<?= $aid ?>" name="next_due_on" min="<?= $today ?>">
                        </div>
                        <div class="col-6 col-md-5">
                            <label class="form-label small mb-0" for="ns-<?= $aid ?>"><?= esc(lang('Activities.nextSubject')) ?></label>
                            <input type="text" class="form-control form-control-sm" id="ns-<?= $aid ?>" name="next_subject" maxlength="200" placeholder="<?= esc(lang('Activities.nextSubjectHint'), 'attr') ?>">
                        </div>
                        <div class="col-8 col-md-3">
                            <label class="form-label small mb-0" for="na-<?= $aid ?>"><?= esc(lang('Activities.assignedTo')) ?></label>
                            <select class="form-select form-select-sm" id="na-<?= $aid ?>" name="next_assigned_to">
                                <?php foreach ($users as $uid => $uname) : ?><option value="<?= $uid ?>"<?= $uid === (int) $a['assigned_to'] ? ' selected' : '' ?>><?= esc($uname) ?></option><?php endforeach ?>
                            </select>
                        </div>
                        <div class="col-4 col-md-1 d-flex align-items-end">
                            <button type="submit" class="btn btn-sm btn-success w-100"><?= esc(lang('Activities.done')) ?></button>
                        </div>
                    </form>
                </details>
            <?php endif ?>
        </div>
        <?php if ($canEdit) : ?>
            <a href="<?= site_url('activities/' . $aid . '/edit') . '?return_to=' . rawurlencode($returnTo) ?>" class="btn btn-link btn-sm p-1 align-self-start" title="<?= esc(lang('App.edit'), 'attr') ?>"><i class="bi bi-pencil"></i></a>
        <?php endif ?>
    </div>
</div>
