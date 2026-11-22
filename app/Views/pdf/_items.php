<?php
/**
 * Priced item table (proposal, agreement, invoice). Amounts are the stored (copied) line values.
 *
 * @var list<array<string, mixed>> $items   Lines (quantity in 'quantity' or 'cpi_quantity').
 * @var bool|null                  $minRows Pad to 4 rows (default false).
 */
$minRows ??= false;
$hasDiscount = array_filter($items, static fn (array $i): bool => (float) $i['discount_amount'] > 0) !== [];
$cols        = $hasDiscount ? 8 : 7;
?>
<table class="items">
    <thead>
        <tr>
            <th style="width: 16pt">No</th>
            <th>Uraian</th>
            <th class="num" style="width: 34pt">Qty</th>
            <th style="width: 34pt">Satuan</th>
            <th class="num" style="width: 70pt">Harga Satuan (Rp)</th>
            <?php if ($hasDiscount) : ?><th class="num" style="width: 58pt">Diskon</th><?php endif ?>
            <th class="num" style="width: 30pt">PPN</th>
            <th class="num" style="width: 76pt">Jumlah (Rp)</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($items as $i => $item) : ?>
            <tr>
                <td class="center"><?= $i + 1 ?></td>
                <td><?= esc(up($item['item_title'])) ?><?php if (! empty($item['item_description'])) : ?><div class="small"><?= nl2br(esc($item['item_description'])) ?></div><?php endif ?><?php if (! empty($item['pl_sku'])) : ?><div class="muted small"><?= esc($item['pl_sku']) ?></div><?php endif ?></td>
                <td class="num"><?= esc(format_qty($item['quantity'] ?? $item['cpi_quantity'])) ?></td>
                <td><?= esc($item['unit_code']) ?></td>
                <td class="num"><?= esc(format_amount($item['unit_price'])) ?></td>
                <?php if ($hasDiscount) : ?><td class="num"><?= (float) $item['discount_amount'] > 0 ? esc(format_amount($item['discount_amount'])) : '-' ?></td><?php endif ?>
                <td class="num"><?= esc(rtrim(rtrim((string) $item['tax_rate'], '0'), '.')) ?>%</td>
                <td class="num"><?= esc(format_amount($item['line_total'])) ?></td>
            </tr>
        <?php endforeach ?>
        <?php if ($minRows) : ?>
            <?php for ($i = count($items); $i < 4; $i++) : ?>
                <tr><td>&nbsp;</td><td colspan="<?= $cols - 1 ?>"></td></tr>
            <?php endfor ?>
        <?php endif ?>
    </tbody>
</table>
