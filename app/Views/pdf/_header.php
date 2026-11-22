<?php
/**
 * Document header: store logo left, title and meta rows right.
 *
 * @var string|null           $logo  Data URI of the store logo.
 * @var string                $title Document title, e.g. "INVOICE".
 * @var array<string, string> $meta  label => value (already formatted, escaped here).
 */
?>
<table class="head">
    <tr>
        <td style="width: 90pt"><?php if (! empty($logo)) : ?><img class="logo" src="<?= $logo ?>" alt=""><?php endif ?></td>
        <td class="right">
            <h1><?= esc($title) ?></h1>
            <table class="doc-meta" style="width: auto; margin-left: auto; margin-top: 4pt">
                <?php foreach ($meta as $label => $value) : ?>
                    <?php if ($value !== '' && $value !== null) : ?>
                        <tr><td class="label"><?= esc($label) ?></td><td class="bold"><?= esc($value) ?></td></tr>
                    <?php endif ?>
                <?php endforeach ?>
            </table>
        </td>
    </tr>
</table>
