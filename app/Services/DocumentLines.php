<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BaseModel;
use App\Models\CompanyProfileModel;
use App\Models\ProductModel;
use App\Models\TaxRateModel;

/**
 * Item lines shared by proposals and invoices: validate posted rows and store them.
 *
 * New lines copy unit, goods/service type and tax rate from the product or its variant (ProductModel::lineDefaults);
 * existing lines keep their copied values. Only title, description, quantity, unit price and discount come from the form.
 */
final class DocumentLines
{
    /**
     * Item fields accepted from the form (unit, type and tax rate are never taken from the form).
     *
     * @var list<string>
     */
    public const ITEM_FIELDS = ['id', 'product_id', 'variant_id', 'item_title', 'item_description', 'quantity', 'unit_price', 'discount_amount', 'tax_code', 'price_base'];

    public const MAX_DESCRIPTION = 2000;

    public const MAX_QUANTITY = '999999999.999';
    public const MAX_MONEY    = '9999999999999.99';

    /**
     * Validate posted item rows and build the line data to store.
     *
     * @param list<array<string, mixed>>       $posted   Posted rows ("items[n][field]").
     * @param array<int, array<string, mixed>> $existing Current lines of the document by id.
     * @param string                           $date     Date for the product tax rate (Y-m-d).
     * @param bool|null                        $allowBelowMin Allow prices below the minimum price (null = the user's prices.override permission).
     *
     * @return list<array{id: int|null, product_id: int, variant_id: int|null, unit_code: string, item_type: string, tax_rate: string, item_title: string, item_description: string|null, quantity: string, unit_price: string, discount_amount: string}>
     *
     * @throws ValidationFailed Errors keyed "items.N" ("Line N: ...").
     */
    public static function prepare(array $posted, array $existing, string $date, ?bool $allowBelowMin = null): array
    {
        $rows = [];

        foreach ($posted as $row) {
            if (! is_array($row)) {
                continue;
            }

            $row = array_map(static fn ($v) => is_string($v) ? trim($v) : $v, array_intersect_key($row, array_flip(self::ITEM_FIELDS)));

            if ((int) ($row['id'] ?? 0) === 0 && (int) ($row['product_id'] ?? 0) === 0) {
                continue; // empty row
            }

            $rows[] = $row;
        }

        $newProductIds = [];

        foreach ($rows as $row) {
            if (! isset($existing[(int) ($row['id'] ?? 0)])) {
                $newProductIds[] = (int) ($row['product_id'] ?? 0);
            }
        }

        $products    = []; // "productId:variantId" (variant 0 = the product itself)
        $hasVariants = [];

        foreach ((new ProductModel())->lineDefaults(['ids' => array_values(array_unique($newProductIds))], $date) as $product) {
            $products[$product['id'] . ':' . (int) $product['variant_id']] = $product;

            if ($product['variant_id'] !== null && $product['is_active']) {
                $hasVariants[$product['id']] = true;
            }
        }

        $profile       = (new CompanyProfileModel())->getProfile();
        $isPkp         = $profile === null || (int) $profile['is_pkp'] === 1;
        $taxes         = new TaxRateModel();
        $validTaxes    = $taxes->validOn($date);
        $minPrices     = self::minPrices(array_values(array_filter($existing, static fn ($e): bool => is_array($e))));
        $allowBelowMin = $allowBelowMin ?? (auth()->user()?->can('prices.override') === true);
        $calc          = CalcSettings::current();
        $lines         = [];
        $errors        = [];

        foreach ($rows as $index => $row) {
            $n    = $index + 1;
            $id   = (int) ($row['id'] ?? 0);
            $base = $existing[$id] ?? null;

            if ($base !== null) {
                $line = [
                    'id'         => $id,
                    'product_id' => (int) $base['product_id'],
                    'variant_id' => $base['variant_id'] !== null ? (int) $base['variant_id'] : null,
                    'unit_code'  => $base['unit_code'],
                    'item_type'  => $base['item_type'],
                    'tax_rate'   => $base['tax_rate'],
                    'tax_code'   => $base['tax_code'] ?? null,
                    'price_base' => $base['price_base'] ?? 'EXCL',
                    'title'      => $base['item_title'],
                    'min_price'  => $minPrices[(int) $base['product_id'] . ':' . (int) ($base['variant_id'] ?? 0)] ?? null,
                ];
            } else {
                $productId = (int) ($row['product_id'] ?? 0);
                $variantId = (int) ($row['variant_id'] ?? 0);
                $product   = $products[$productId . ':' . $variantId] ?? null;

                if ($product === null || ! $product['is_active']) {
                    $errors["items.{$n}"] = lang('Messages.line', [$n, lang('Messages.lineSelectProduct')]);

                    continue;
                }

                if ($variantId === 0 && isset($hasVariants[$productId])) {
                    $errors["items.{$n}"] = lang('Messages.line', [$n, lang('Messages.lineSelectVariant')]);

                    continue;
                }

                $line = [
                    'id'         => null,
                    'product_id' => $product['id'],
                    'variant_id' => $product['variant_id'],
                    'unit_code'  => $product['unit_code'],
                    'item_type'  => $product['item_type'],
                    'tax_rate'   => $product['tax_rate'],
                    'tax_code'   => $product['tax_code'],
                    'price_base' => $product['price_incl_tax'] ? 'INCL' : 'EXCL',
                    'title'      => $product['title'],
                    'min_price'  => $product['min_price'],
                ];
            }

            // Tax chosen on the line (Dolibarr style); without a posted choice the copied / product default stays.
            if (! $isPkp) {
                $line['tax_code'] = null;
                $line['tax_rate'] = '0.00';
            } elseif (array_key_exists('tax_code', $row) && (string) $row['tax_code'] !== (string) ($line['tax_code'] ?? '')) {
                $code = (string) $row['tax_code'];

                if ($code === '') {
                    $line['tax_code'] = null;
                    $line['tax_rate'] = '0.00';
                } elseif (isset($validTaxes[$code])) {
                    $line['tax_code'] = $code;
                    $line['tax_rate'] = $validTaxes[$code];
                } else {
                    $errors["items.{$n}"] = lang('Messages.line', [$n, lang('Messages.lineTaxInvalid')]);

                    continue;
                }
            }

            if (in_array($row['price_base'] ?? null, ['EXCL', 'INCL'], true)) {
                $line['price_base'] = $row['price_base'];
            }

            $title    = (string) ($row['item_title'] ?? '');
            $quantity = (string) ($row['quantity'] ?? '');
            $price    = (string) ($row['unit_price'] ?? '');
            $discount = (string) ($row['discount_amount'] ?? '');
            $discount = $discount === '' ? '0' : $discount;
            $title    = $title === '' ? $line['title'] : $title;
            $text     = str_replace("\r\n", "\n", (string) ($row['item_description'] ?? ''));

            $error = match (true) {
                mb_strlen($title) > 200                                            => lang('Messages.lineTitleLong'),
                mb_strlen($text) > self::MAX_DESCRIPTION                           => lang('Messages.lineDescLong', [self::MAX_DESCRIPTION]),
                ! self::isDecimal($quantity, 3) || bccomp($quantity, '0', 3) <= 0  => lang('Messages.lineQtyPositive'),
                bccomp($quantity, self::MAX_QUANTITY, 3) > 0                       => lang('Messages.lineQtyLarge'),
                ! self::isDecimal($price, $line['price_base'] === 'INCL' ? 2 : $calc->unitDecimals) => lang('Messages.linePrice'),
                bccomp($price, self::MAX_MONEY, 2) > 0                             => lang('Messages.linePriceLarge'),
                ! self::isDecimal($discount, 2)                                    => lang('Messages.lineDiscount'),
                bccomp($discount, DocumentTotals::gross($quantity, $price), 2) > 0 => lang('Messages.lineDiscountMax'),
                default                                                            => null,
            };

            if ($error !== null) {
                $errors["items.{$n}"] = lang('Messages.line', [$n, $error]);

                continue;
            }

            // Prices typed incl. tax: the typed values drive the totals (DocumentTotals, Dolibarr "TTC"); the
            // unit price / discount excl. tax are stored for display, the minimum price check and withholding.
            $priceIncl    = null;
            $discountIncl = null;

            if ($line['price_base'] === 'INCL') {
                $priceIncl    = DocumentTotals::round2($price);
                $discountIncl = DocumentTotals::round2($discount);
                $price        = DocumentTotals::exclFromIncl($price, $line['tax_rate'], $calc);
                $discount     = DocumentTotals::excl($discount, $line['tax_rate'], $calc);
            }

            // Minimum price (excl. tax, per unit after discount): only users with prices.override may go below,
            // and only changed or new lines are checked (an approved low price stays when other lines change).
            $changed = $base === null || bccomp($price, (string) $base['unit_price'], 6) !== 0 || bccomp($discount, (string) $base['discount_amount'], 2) !== 0;

            if ($changed && ! $allowBelowMin && $line['min_price'] !== null && bccomp($line['min_price'], '0', 2) > 0) {
                $net = bcdiv(bcsub(bcmul($quantity, $price, 5), $discount, 5), $quantity, 5);

                if (bccomp($net, (string) $line['min_price'], 2) < 0) {
                    $errors["items.{$n}"] = lang('Messages.line', [$n, lang('Messages.lineBelowMin', [format_idr($line['min_price'])])]);

                    continue;
                }
            }

            unset($line['title'], $line['min_price']);
            $lines[] = $line + [
                'item_title'       => $title,
                'item_description' => $text === '' ? null : $text,
                'quantity'         => $quantity,
                'unit_price'       => $price,
                'unit_price_incl'  => $priceIncl,
                'discount_amount'  => $discount,
                'discount_incl'    => $discountIncl,
            ];
        }
        if ($errors !== []) {
            throw new ValidationFailed($errors);
        }

        return $lines;
    }

    /**
     * Make the stored lines equal to $lines: update kept lines, insert new ones, soft-delete removed ones.
     *
     * @param BaseModel                        $model          Item model (client_proposal_item, invoice_item).
     * @param string                           $parentColumn   Parent FK column, e.g. 'invoice_id'.
     * @param string                           $quantityColumn Quantity column, e.g. 'cpi_quantity' or 'quantity'.
     * @param int                              $parentId       Parent document id.
     * @param list<array<string, mixed>>       $lines          Prepared lines (see prepare()).
     * @param array{lines: list<array{line_total: string, tax_amount: string}>} $totals Computed amounts (same order).
     * @param array<int, array<string, mixed>> $existing       Current lines by id.
     *
     * @return void
     */
    public static function sync(BaseModel $model, string $parentColumn, string $quantityColumn, int $parentId, array $lines, array $totals, array $existing): void
    {
        $kept = [];

        foreach ($lines as $i => $line) {
            $data = [
                $parentColumn     => $parentId,
                'product_id'      => $line['product_id'],
                'variant_id'      => $line['variant_id'] ?? null,
                'item_title'      => $line['item_title'],
                'item_description' => $line['item_description'] ?? null,
                'unit_code'       => $line['unit_code'],
                'item_type'       => $line['item_type'],
                $quantityColumn   => $line['quantity'],
                'unit_price'      => $line['unit_price'],
                'discount_amount' => $totals['lines'][$i]['discount_amount'] ?? $line['discount_amount'],
                'discount_incl'   => $line['discount_incl'] ?? null,
                'tax_rate'        => $line['tax_rate'],
                'tax_code'        => $line['tax_code'] ?? null,
                'price_base'      => $line['price_base'] ?? 'EXCL',
                'unit_price_incl' => $line['unit_price_incl'] ?? null,
                'tax_amount'      => $totals['lines'][$i]['tax_amount'],
                'line_total'      => $totals['lines'][$i]['line_total'],
            ];

            if ($line['id'] !== null) {
                $kept[] = $line['id'];
                $model->update($line['id'], $data);
            } else {
                $model->insert($data);
            }
        }

        foreach (array_diff(array_keys($existing), $kept) as $removedId) {
            $model->delete($removedId);
        }
    }

    /**
     * Minimum prices of the products / variants of existing lines: "productId:variantId" => min price (excl. tax).
     *
     * @param list<array<string, mixed>> $lines Existing lines.
     *
     * @return array<string, string|null>
     */
    private static function minPrices(array $lines): array
    {
        $ids = array_values(array_unique(array_map(static fn (array $l): int => (int) $l['product_id'], $lines)));

        if ($ids === []) {
            return [];
        }

        $db     = db_connect();
        $result = [];

        foreach ($db->table('product_list')->select('id, pl_min_price')->whereIn('id', $ids)->get()->getResultArray() as $p) {
            $result[$p['id'] . ':0'] = $p['pl_min_price'];
        }

        foreach ($db->table('product_variant')->select('id, product_id, pv_min_price')->whereIn('product_id', $ids)->get()->getResultArray() as $v) {
            $result[$v['product_id'] . ':' . $v['id']] = $v['pv_min_price'] ?? ($result[$v['product_id'] . ':0'] ?? null);
        }

        return $result;
    }

    /**
     * Whether $value is a non-negative decimal with at most $decimals decimals.
     *
     * @param string $value    Input.
     * @param int    $decimals Allowed decimals.
     *
     * @return bool
     */
    public static function isDecimal(string $value, int $decimals): bool
    {
        return preg_match('/^\d{1,15}(\.\d{1,' . $decimals . '})?$/', $value) === 1;
    }
}
