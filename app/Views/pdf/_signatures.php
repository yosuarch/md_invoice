<?php
/**
 * Signature row. The place/date line is printed above the last block (Indonesian convention).
 * With $qr, a narrow first column shows the "scan to verify" QR code (as on the store's former documents).
 *
 * @var list<array{title?: string, name?: string, position?: string, extra?: string, stamp?: bool, spacer?: bool}> $blocks
 *      spacer: an empty column (to push a single signature to the right).
 * @var string      $place Place (city) for "Place, date".
 * @var string|null $date  Y-m-d of the signature date.
 * @var string|null $qr    Verification QR (data URI) or null.
 */
$qr ??= null;
$qrWidth = $qr !== null ? 20 : 0;
$width   = (int) floor((100 - $qrWidth) / max(1, count($blocks)));
$when    = trim(($place !== '' ? $place . ', ' : '') . format_date_id($date ?? date('Y-m-d')));
?>
<table class="signatures">
    <tr>
        <?php if ($qr !== null) : ?>
            <td style="width: <?= $qrWidth ?>%; vertical-align: bottom" class="small muted">
                <img src="<?= $qr ?>" alt="" style="width: 54pt; height: 54pt"><br>
                Pindai untuk verifikasi keaslian dokumen
            </td>
        <?php endif ?>
        <?php foreach ($blocks as $i => $b) : ?>
            <?php if (! empty($b['spacer'])) : ?><td style="width: <?= $width ?>%"></td><?php continue ?><?php endif ?>
            <td class="sign" style="width: <?= $width ?>%">
                <div class="small"><?= $i === array_key_last($blocks) ? esc($when) : '&nbsp;' ?></div>
                <div class="bold"><?= esc($b['title']) ?></div>
                <?php if (! empty($b['position'])) : ?><div class="small"><?= esc($b['position']) ?></div><?php endif ?>
                <div class="sign-space">
                    <?php if (! empty($b['stamp'])) : ?><div class="stamp">Meterai<br>Rp10.000</div><?php endif ?>
                </div>
                <div class="sign-name"><?= ! empty($b['name']) ? esc($b['name']) : '(..................................)' ?></div>
                <?php if (! empty($b['extra'])) : ?><div class="small"><?= esc($b['extra']) ?></div><?php endif ?>
            </td>
        <?php endforeach ?>
    </tr>
</table>
