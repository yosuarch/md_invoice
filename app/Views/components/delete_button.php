<?php
/**
 * POST form button with a confirmation dialog (see data-confirm in assets/js/app.js).
 *
 * @var string      $action  Form action URL.
 * @var string      $label   Button text.
 * @var string|null $confirm Confirmation message.
 * @var string|null $class   Button classes.
 * @var string|null $icon    Bootstrap icon name.
 */
?>
<form action="<?= esc($action, 'attr') ?>" method="post" class="d-inline"
      data-confirm="<?= esc($confirm ?? lang('App.areYouSure'), 'attr') ?>">
    <?= csrf_field() ?>
    <button type="submit" class="<?= esc($class ?? 'btn btn-outline-danger btn-sm', 'attr') ?>"
            aria-label="<?= esc($label !== '' ? $label : ($confirm ?? lang('App.delete')), 'attr') ?>" title="<?= esc($label !== '' ? $label : lang('App.delete'), 'attr') ?>">
        <?php if (! empty($icon)) : ?><i class="bi bi-<?= esc($icon, 'attr') ?> me-1"></i><?php endif ?><?= esc($label) ?>
    </button>
</form>
