<?php
/**
 * "Activities & follow-ups" card (App\Services\ActivityPanel): timeline plus "Log activity" and "Plan follow-up"
 * forms (modals). Used on the school, proposal and invoice pages.
 *
 * @var array<string, mixed> $panel customerId, activities, people, proposals, invoices, users, preset, returnTo.
 */

use App\Models\ActivityModel;

$canManage = user_can('crm.manage');
$preset    = $panel['preset'];
$me        = (int) auth()->id();
$open      = count(array_filter($panel['activities'], [ActivityModel::class, 'isOpen']));

/**
 * <option>s with a selected value.
 *
 * @param array<int, string> $options
 */
$opts = static function (array $options, ?int $selected): string {
    $html = '';

    foreach ($options as $id => $label) {
        $html .= '<option value="' . (int) $id . '"' . ($selected === (int) $id ? ' selected' : '') . '>' . esc($label) . '</option>';
    }

    return $html;
};

/** Shared fields: person, proposal, invoice (hidden when preset by the page). */
$links = static function (string $prefix) use ($panel, $preset, $opts): string {
    $html = '<div class="col-12 col-md-6"><label class="form-label small mb-1" for="' . $prefix . '-person">' . esc(lang('Activities.person')) . '</label>'
        . '<select class="form-select form-select-sm" id="' . $prefix . '-person" name="contact_id"><option value="">' . esc(lang('Activities.noPerson')) . '</option>'
        . $opts($panel['people'], $preset['contact_id'] ?? null) . '</select></div>';

    if (! empty($preset['proposal_id']) && empty($preset['invoice_id'])) {
        $html .= '<input type="hidden" name="proposal_id" value="' . (int) $preset['proposal_id'] . '">';
    } elseif ($panel['proposals'] !== []) {
        $html .= '<div class="col-6 col-md-3"><label class="form-label small mb-1" for="' . $prefix . '-proposal">' . esc(lang('Activities.proposal')) . '</label>'
            . '<select class="form-select form-select-sm" id="' . $prefix . '-proposal" name="proposal_id"><option value="">' . esc(lang('Activities.noDoc')) . '</option>'
            . $opts($panel['proposals'], $preset['proposal_id'] ?? null) . '</select></div>';
    }

    if (! empty($preset['invoice_id'])) {
        $html .= '<input type="hidden" name="invoice_id" value="' . (int) $preset['invoice_id'] . '">';
    } elseif ($panel['invoices'] !== []) {
        $html .= '<div class="col-6 col-md-3"><label class="form-label small mb-1" for="' . $prefix . '-invoice">' . esc(lang('Activities.invoice')) . '</label>'
            . '<select class="form-select form-select-sm" id="' . $prefix . '-invoice" name="invoice_id"><option value="">' . esc(lang('Activities.noDoc')) . '</option>'
            . $opts($panel['invoices'], null) . '</select></div>';
    }

    return $html;
};

$types = static fn (string $id, string $default): string => '<select class="form-select form-select-sm" id="' . $id . '" name="activity_type">'
    . implode('', array_map(static fn (string $t): string => '<option value="' . $t . '"' . ($t === $default ? ' selected' : '') . '>' . esc(lang('Activities.type_' . $t)) . '</option>', ActivityModel::TYPES))
    . '</select>';
?>
<div class="card shadow-sm mb-3" id="activities">
    <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span class="fw-semibold"><i class="bi bi-calendar-check me-1"></i><?= esc(lang('Activities.activities')) ?>
            <?php if ($open > 0) : ?><span class="badge rounded-pill bg-primary ms-1"><?= $open ?></span><?php endif ?></span>
        <?php if ($canManage) : ?>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#activity-log"><i class="bi bi-plus-lg me-1"></i><?= esc(lang('Activities.logActivity')) ?></button>
                <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#activity-plan"><i class="bi bi-calendar-plus me-1"></i><?= esc(lang('Activities.planFollowUp')) ?></button>
            </div>
        <?php endif ?>
    </div>
    <div class="list-group list-group-flush">
        <?php foreach ($panel['activities'] as $a) : ?>
            <?= view('components/activity_item', ['a' => $a, 'returnTo' => $panel['returnTo'], 'users' => $panel['users']], ['saveData' => false]) ?>
        <?php endforeach ?>
        <?php if ($panel['activities'] === []) : ?>
            <div class="list-group-item text-center text-body-secondary small py-3"><?= esc(lang('Activities.none')) ?></div>
        <?php endif ?>
    </div>
</div>

<?php if ($canManage) : ?>
    <div class="modal fade" id="activity-log" tabindex="-1" aria-labelledby="activity-log-title" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <form class="modal-content" action="<?= site_url('activities') ?>" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="customer_id" value="<?= (int) $panel['customerId'] ?>">
                <input type="hidden" name="return_to" value="<?= esc($panel['returnTo'], 'attr') ?>">
                <div class="modal-header">
                    <h5 class="modal-title" id="activity-log-title"><i class="bi bi-plus-lg me-1"></i><?= esc(lang('Activities.logActivity')) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= esc(lang('App.close'), 'attr') ?>"></button>
                </div>
                <div class="modal-body row g-2">
                    <div class="col-6 col-md-3">
                        <label class="form-label small mb-1" for="log-type"><?= esc(lang('Activities.type')) ?></label>
                        <?= $types('log-type', 'CALL') ?>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label small mb-1" for="log-when"><?= esc(lang('Activities.when')) ?> <span class="text-danger">*</span></label>
                        <input type="datetime-local" class="form-control form-control-sm" id="log-when" name="occurred_at" required value="<?= date('Y-m-d\TH:i') ?>" max="<?= date('Y-m-d\T23:59') ?>">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label small mb-1" for="log-subject"><?= esc(lang('Activities.subject')) ?> <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="log-subject" name="subject" maxlength="200" required placeholder="<?= esc(lang('Activities.subjectHint'), 'attr') ?>">
                    </div>
                    <?= $links('log') ?>
                    <div class="col-12">
                        <label class="form-label small mb-1" for="log-notes"><?= esc(lang('Activities.notes')) ?></label>
                        <textarea class="form-control form-control-sm" id="log-notes" name="notes" rows="3" maxlength="5000"></textarea>
                    </div>
                    <div class="col-12 small fw-semibold mt-2"><?= esc(lang('Activities.nextFollowUp')) ?></div>
                    <div class="col-6 col-md-3">
                        <label class="form-label small mb-1" for="log-next-due"><?= esc(lang('Activities.nextDue')) ?></label>
                        <input type="date" class="form-control form-control-sm" id="log-next-due" name="next_due_on" min="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-6 col-md-5">
                        <label class="form-label small mb-1" for="log-next-subject"><?= esc(lang('Activities.nextSubject')) ?></label>
                        <input type="text" class="form-control form-control-sm" id="log-next-subject" name="next_subject" maxlength="200" placeholder="<?= esc(lang('Activities.nextSubjectHint'), 'attr') ?>">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label small mb-1" for="log-next-user"><?= esc(lang('Activities.assignedTo')) ?></label>
                        <select class="form-select form-select-sm" id="log-next-user" name="next_assigned_to"><?= $opts($panel['users'], $me) ?></select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= esc(lang('App.cancel')) ?></button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i><?= esc(lang('App.save')) ?></button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="activity-plan" tabindex="-1" aria-labelledby="activity-plan-title" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <form class="modal-content" action="<?= site_url('activities') ?>" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="customer_id" value="<?= (int) $panel['customerId'] ?>">
                <input type="hidden" name="return_to" value="<?= esc($panel['returnTo'], 'attr') ?>">
                <div class="modal-header">
                    <h5 class="modal-title" id="activity-plan-title"><i class="bi bi-calendar-plus me-1"></i><?= esc(lang('Activities.planFollowUp')) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= esc(lang('App.close'), 'attr') ?>"></button>
                </div>
                <div class="modal-body row g-2">
                    <div class="col-6 col-md-3">
                        <label class="form-label small mb-1" for="plan-type"><?= esc(lang('Activities.type')) ?></label>
                        <?= $types('plan-type', 'CALL') ?>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label small mb-1" for="plan-due"><?= esc(lang('Activities.dueOn')) ?> <span class="text-danger">*</span></label>
                        <input type="date" class="form-control form-control-sm" id="plan-due" name="due_on" required min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d', strtotime('+1 day')) ?>">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label small mb-1" for="plan-user"><?= esc(lang('Activities.assignedTo')) ?></label>
                        <select class="form-select form-select-sm" id="plan-user" name="assigned_to"><?= $opts($panel['users'], $me) ?></select>
                    </div>
                    <div class="col-12">
                        <label class="form-label small mb-1" for="plan-subject"><?= esc(lang('Activities.nextSubject')) ?> <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="plan-subject" name="subject" maxlength="200" required placeholder="<?= esc(lang('Activities.nextSubjectHint'), 'attr') ?>">
                    </div>
                    <?= $links('plan') ?>
                    <div class="col-12">
                        <label class="form-label small mb-1" for="plan-notes"><?= esc(lang('Activities.notes')) ?></label>
                        <textarea class="form-control form-control-sm" id="plan-notes" name="notes" rows="2" maxlength="5000"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= esc(lang('App.cancel')) ?></button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i><?= esc(lang('App.save')) ?></button>
                </div>
            </form>
        </div>
    </div>
<?php endif ?>
