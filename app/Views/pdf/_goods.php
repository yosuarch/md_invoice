<?php
/**
 * Goods table for delivery documents (no prices).
 *
 * @var list<array<string, mixed>> $items     Delivery note lines (item_title, quantity, unit_code, pl_sku).
 * @var string                     $lastTitle Title of the last column ("Keterangan" / "Kondisi").
 * @var string                     $lastValue Default value of the last column.
 */
?>
<table class="items">
    <thead>
        <tr>
            <th style="width: 16pt">No</th>
            <th>Nama barang</th>
            <th class="num" style="width: 48pt">Jumlah</th>
            <th style="width: 44pt">Satuan</th>
            <th style="width: 110pt"><?= esc($lastTitle) ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($items as $i => $item) : ?>
            <tr>
                <td class="center"><?= $i + 1 ?></td>
                <td><?= esc(up($item['item_title'])) ?><?php if (! empty($item['item_description'])) : ?><div class="small"><?= nl2br(esc($item['item_description'])) ?></div><?php endif ?><?php if (! empty($item['pl_sku'])) : ?><div class="muted small"><?= esc($item['pl_sku']) ?></div><?php endif ?></td>
                <td class="num bold"><?= esc(format_qty($item['quantity'])) ?></td>
                <td><?= esc($item['unit_code']) ?></td>
                <td><?= esc($lastValue) ?></td>
            </tr>
        <?php endforeach ?>
    </tbody>
</table>
