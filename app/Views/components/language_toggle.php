<?php
/**
 * Language toggle (ID / EN). Posts to /locale (saved on the user's account or in the guest session) with a full page
 * load (hx-boost="false"), because <head> (html lang, script texts) is not swapped by htmx.
 */
$current = service('request')->getLocale();
?>
<form action="<?= site_url('locale') ?>" method="post" hx-boost="false" class="m-0">
    <?= csrf_field() ?>
    <div class="btn-group btn-group-sm language-toggle" role="group" aria-label="<?= esc(lang('App.switchLanguage'), 'attr') ?>">
        <?php foreach (app_locales() as $code => $name) : ?>
            <button type="submit" name="locale" value="<?= esc($code, 'attr') ?>" title="<?= esc($name, 'attr') ?>" lang="<?= esc($code, 'attr') ?>"
                    class="btn <?= $code === $current ? 'btn-primary' : 'btn-outline-primary' ?>"<?= $code === $current ? ' aria-pressed="true"' : '' ?>>
                <?= esc(strtoupper($code)) ?>
            </button>
        <?php endforeach ?>
    </div>
</form>
