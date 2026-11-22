<?php
/**
 * Flash messages set by controllers:
 *   ->with('message', '...')  success
 *   ->with('error', '...')    error
 *   ->with('errors', [...])   validation errors
 */
?>
<?php if (session('message') !== null) : ?>
    <?php /* Success: toast that hides itself (shown by app.js); errors below stay on the page. */ ?>
    <div class="toast-container position-fixed top-0 end-0 p-3">
        <div class="toast align-items-center text-bg-success border-0" role="status" aria-live="polite" data-autoshow data-bs-delay="4000">
            <div class="d-flex">
                <div class="toast-body"><i class="bi bi-check-circle me-1"></i><?= esc(session('message')) ?></div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="<?= esc(lang('App.close'), 'attr') ?>"></button>
            </div>
        </div>
    </div>
<?php endif ?>

<?php if (session('error') !== null) : ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle me-1"></i> <?= esc(session('error')) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?= esc(lang('App.close'), 'attr') ?>"></button>
    </div>
<?php endif ?>

<?php if (is_array(session('errors')) && session('errors') !== []) : ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <div class="fw-semibold mb-1"><i class="bi bi-exclamation-triangle me-1"></i><?= lang('App.correctErrors') ?></div>
        <ul class="mb-0 ps-3">
            <?php foreach (session('errors') as $err) : ?>
                <li><?= esc($err) ?></li>
            <?php endforeach ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?= esc(lang('App.close'), 'attr') ?>"></button>
    </div>
<?php endif ?>
