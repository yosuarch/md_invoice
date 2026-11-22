<?php
/**
 * Modal with a required reason textarea that POSTs to an action (void, reverse, ...).
 *
 * @var string      $id     Modal id (target of data-bs-target="#id").
 * @var string      $action Form action URL.
 * @var string      $title  Modal title.
 * @var string      $help   Explanation shown above the field.
 * @var string      $button Submit button text.
 * @var string|null $field  Textarea name (default 'reason').
 */
$field ??= 'reason';
?>
<div class="modal fade" id="<?= esc($id, 'attr') ?>" tabindex="-1" aria-labelledby="<?= esc($id, 'attr') ?>-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" action="<?= esc($action, 'attr') ?>" method="post">
            <?= csrf_field() ?>
            <div class="modal-header">
                <h5 class="modal-title" id="<?= esc($id, 'attr') ?>-title"><?= esc($title) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= esc(lang('App.close'), 'attr') ?>"></button>
            </div>
            <div class="modal-body">
                <p class="small"><?= esc($help) ?></p>
                <label for="<?= esc($id, 'attr') ?>-reason" class="form-label"><?= lang('App.reason') ?> <span class="text-danger">*</span></label>
                <textarea class="form-control" id="<?= esc($id, 'attr') ?>-reason" name="<?= esc($field, 'attr') ?>" rows="3" maxlength="2000" required></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= lang('App.close') ?></button>
                <button type="submit" class="btn btn-danger"><?= esc($button) ?></button>
            </div>
        </form>
    </div>
</div>
