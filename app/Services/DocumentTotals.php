<?php

declare(strict_types=1);

namespace App\Services;

use RoundingMode;

/**
 * Line and header totals for item-based documents (proposals, invoices, credit notes).
 * Pure decimal arithmetic with bcmath (never float); rounding R() follows CalcSettings (decimals and mode).
 * Rules (database.md §11.4):
 *
 *   price typed excl. tax:  line_total = R(qty × unit_price − discount),        tax = R(line_total × rate / 100)
 *   price typed incl. tax:  total_incl = R(qty × unit_price_incl − discount_incl)   (Dolibarr "TTC": exact)
 *                           line_total = R(total_incl × 100 / (100 + rate)),    tax = total_incl − line_total
 *   tax mode TOTAL:         per (rate, price base) group the tax is computed once on the group total
 *                           (excl.: R(Σ line_total × rate / 100); incl.: Σ total_incl − R(Σ total_incl × 100 / (100 + rate)))
 *                           and spread over the lines in proportion (remainder to the largest line)
 *   subtotal = Σ line_total, discount_total = Σ discount (excl. tax), tax_total = Σ tax,
 *   grand_total = subtotal + tax_total (+ R(shipping_cost))
 *
 * Mirrored by App.lineAmounts() / documentItems() in document-items.js.
 */
final class DocumentTotals
{
    private const SCALE = CalcSettings::SCALE;

    /**
     * Compute line amounts and header totals.
     *
     * @param list<array<string, mixed>> $lines        Lines with quantity, unit_price, discount_amount, tax_rate and
     *                                                 optionally price_base, unit_price_incl, discount_incl.
     * @param numeric-string|int|float   $shippingCost Added to the grand total (invoices, credit notes).
     * @param CalcSettings|null          $settings     Calculation settings (null = the company's settings).
     *
     * @return array{lines: list<array{line_total: string, tax_amount: string, discount_amount: string}>, subtotal: string, discount_total: string, tax_total: string, grand_total: string}
     */
    public static function calculate(array $lines, string|int|float $shippingCost = '0', ?CalcSettings $settings = null): array
    {
        $s      = $settings ?? CalcSettings::current();
        $rows   = [];
        $groups = [];

        foreach (array_values($lines) as $i => $line) {
            $row      = self::lineAmounts($line, $s);
            $rows[$i] = $row;

            if ($s->taxMode === 'TOTAL' && bccomp($row['rate'], '0', 2) !== 0) {
                $groups[$row['rate'] . '|' . ($row['incl'] ? 'INCL' : 'EXCL')][] = $i;
            }
        }

        foreach ($groups as $key => $indexes) {
            $incl = str_ends_with($key, 'INCL');
            $rate = $rows[$indexes[0]]['rate'];
            $sum  = '0';

            foreach ($indexes as $i) {
                $sum = bcadd($sum, $incl ? $rows[$i]['total_incl'] : $rows[$i]['line_total'], 2);
            }

            $tax     = $incl ? bcsub($sum, self::excl($sum, $rate, $s), 2) : $s->round(bcdiv(bcmul($sum, $rate, self::SCALE), '100', self::SCALE));
            $weights = array_map(static fn (int $i): string => $incl ? $rows[$i]['total_incl'] : $rows[$i]['line_total'], $indexes);

            foreach (self::spread($tax, $weights, $s) as $k => $share) {
                $i                      = $indexes[$k];
                $rows[$i]['tax_amount'] = $share;

                if ($incl) {
                    $rows[$i]['line_total'] = bcsub($rows[$i]['total_incl'], $share, 2);
                }
            }
        }

        $result   = ['lines' => []];
        $subtotal = $discounts = $taxes = '0';

        foreach ($rows as $row) {
            $result['lines'][] = ['line_total' => $row['line_total'], 'tax_amount' => $row['tax_amount'], 'discount_amount' => $row['discount']];
            $subtotal          = bcadd($subtotal, $row['line_total'], 2);
            $discounts         = bcadd($discounts, $row['discount'], 2);
            $taxes             = bcadd($taxes, $row['tax_amount'], 2);
        }

        $result['subtotal']       = bcadd($subtotal, '0', 2);
        $result['discount_total'] = bcadd($discounts, '0', 2);
        $result['tax_total']      = bcadd($taxes, '0', 2);
        $result['grand_total']    = bcadd(bcadd($subtotal, $taxes, 2), $s->round(self::num($shippingCost)), 2);

        return $result;
    }

    /**
     * Whether a line was priced incl. tax (and has the typed price).
     *
     * @param array<string, mixed> $line Line data.
     *
     * @return bool
     */
    public static function isIncl(array $line): bool
    {
        return ($line['price_base'] ?? 'EXCL') === 'INCL' && ($line['unit_price_incl'] ?? null) !== null && $line['unit_price_incl'] !== '';
    }

    /**
     * Amounts of one line (tax per line).
     *
     * @param array<string, mixed> $line Line data.
     * @param CalcSettings         $s    Settings.
     *
     * @return array{incl: bool, rate: string, total_incl: string, line_total: string, tax_amount: string, discount: string}
     */
    private static function lineAmounts(array $line, CalcSettings $s): array
    {
        $qty  = self::num($line['quantity'] ?? 0);
        $rate = bcadd(self::num($line['tax_rate'] ?? 0), '0', 2);

        if (self::isIncl($line)) {
            $discountIncl = ($line['discount_incl'] ?? null) !== null && $line['discount_incl'] !== ''
                ? self::num($line['discount_incl'])
                : self::inclFromExcl(self::num($line['discount_amount'] ?? 0), $rate, $s); // lines saved before discount_incl existed
            $totalIncl = $s->round(bcsub(bcmul($qty, self::num($line['unit_price_incl']), self::SCALE), $discountIncl, self::SCALE));
            $lineTotal = self::excl($totalIncl, $rate, $s);

            return [
                'incl'       => true,
                'rate'       => $rate,
                'total_incl' => $totalIncl,
                'line_total' => $lineTotal,
                'tax_amount' => bcsub($totalIncl, $lineTotal, 2),
                'discount'   => self::excl($discountIncl, $rate, $s),
            ];
        }

        $discount  = $s->round(self::num($line['discount_amount'] ?? 0));
        $lineTotal = $s->round(bcsub(bcmul($qty, self::num($line['unit_price'] ?? 0), self::SCALE), $discount, self::SCALE));
        $taxAmount = $s->round(bcdiv(bcmul($lineTotal, $rate, self::SCALE), '100', self::SCALE));

        return [
            'incl'       => false,
            'rate'       => $rate,
            'total_incl' => bcadd($lineTotal, $taxAmount, 2),
            'line_total' => $lineTotal,
            'tax_amount' => $taxAmount,
            'discount'   => $discount,
        ];
    }

    /**
     * Spread an amount over lines in proportion to their weights, in steps of the amount decimals; the remainder
     * goes to the line with the largest weight. The shares add up exactly to $amount.
     *
     * @param string       $amount  Amount to spread (rounded to the amount decimals).
     * @param list<string> $weights Weights (>= 0).
     * @param CalcSettings $s       Settings.
     *
     * @return list<string> Shares (2 decimals), same order as $weights.
     */
    public static function spread(string $amount, array $weights, CalcSettings $s): array
    {
        $step   = $s->step();
        $units  = bcdiv($amount, $step, 0);
        $total  = '0';
        $max    = 0;
        $shares = [];

        foreach ($weights as $k => $w) {
            $total = bcadd($total, $w, 2);

            if (bccomp($w, $weights[$max], 2) > 0) {
                $max = $k;
            }
        }

        $given = '0';

        foreach ($weights as $k => $w) {
            $share      = bccomp($total, '0', 2) === 0 ? '0' : bcdiv(bcmul($units, $w, 2), $total, 0);
            $shares[$k] = $share;
            $given      = bcadd($given, $share, 0);
        }

        if ($shares !== []) {
            $shares[$max] = bcadd($shares[$max], bcsub($units, $given, 0), 0);
        }

        return array_map(static fn (string $u): string => bcmul($u, $step, 2), $shares);
    }

    /**
     * Gross amount of a line before discount: qty × unit_price (2 decimals, half up), used to validate discounts.
     *
     * @param numeric-string|int|float $quantity  Quantity.
     * @param numeric-string|int|float $unitPrice Unit price.
     *
     * @return string
     */
    public static function gross(string|int|float $quantity, string|int|float $unitPrice): string
    {
        return self::round2(bcmul(self::num($quantity), self::num($unitPrice), self::SCALE));
    }

    /**
     * Unit price excl. tax from a unit price incl. tax: price × 100 / (100 + rate), rounded to the unit decimals
     * (6-decimal string). Same rule as App.exclFromIncl() in document-items.js.
     *
     * @param numeric-string|int|float $priceIncl Price including tax.
     * @param numeric-string|int|float $rate      Tax rate in percent.
     * @param CalcSettings|null        $settings  Settings (null = the company's).
     *
     * @return string
     */
    public static function exclFromIncl(string|int|float $priceIncl, string|int|float $rate, ?CalcSettings $settings = null): string
    {
        $s = $settings ?? CalcSettings::current();

        return $s->roundUnit(bcdiv(bcmul(self::num($priceIncl), '100', self::SCALE), bcadd('100', self::num($rate), self::SCALE), self::SCALE));
    }

    /**
     * Amount excl. tax from an amount incl. tax, rounded to the amount decimals (discounts, totals).
     *
     * @param numeric-string|int|float $amountIncl Amount including tax.
     * @param numeric-string|int|float $rate       Tax rate in percent.
     * @param CalcSettings|null        $settings   Settings (null = the company's).
     *
     * @return string 2-decimal string.
     */
    public static function excl(string|int|float $amountIncl, string|int|float $rate, ?CalcSettings $settings = null): string
    {
        $s = $settings ?? CalcSettings::current();

        return $s->round(bcdiv(bcmul(self::num($amountIncl), '100', self::SCALE), bcadd('100', self::num($rate), self::SCALE), self::SCALE));
    }

    /**
     * Amount incl. tax from an amount excl. tax: amount × (100 + rate) / 100, rounded to the amount decimals.
     *
     * @param numeric-string|int|float $priceExcl Amount excluding tax.
     * @param numeric-string|int|float $rate      Tax rate in percent.
     * @param CalcSettings|null        $settings  Settings (null = the company's).
     *
     * @return string 2-decimal string.
     */
    public static function inclFromExcl(string|int|float $priceExcl, string|int|float $rate, ?CalcSettings $settings = null): string
    {
        $s = $settings ?? CalcSettings::current();

        return $s->round(bcdiv(bcmul(self::num($priceExcl), bcadd('100', self::num($rate), self::SCALE), self::SCALE), '100', self::SCALE));
    }

    /**
     * Round half away from zero to 2 decimals.
     *
     * @param string $value Decimal string.
     *
     * @return string e.g. "1234.57"
     */
    public static function round2(string $value): string
    {
        return bcround($value, 2, RoundingMode::HalfAwayFromZero);
    }

    /**
     * Normalize a number to a bcmath-compatible decimal string.
     *
     * @param mixed $value Number or numeric string ('' and null become 0).
     *
     * @return string
     */
    public static function num(mixed $value): string
    {
        if (is_float($value)) {
            $value = number_format($value, self::SCALE, '.', '');
        }

        $value = trim((string) ($value ?? ''));

        return is_numeric($value) ? $value : '0';
    }
}
