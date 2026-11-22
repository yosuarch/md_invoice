<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CompanyProfileModel;
use RoundingMode;
use Throwable;

/**
 * How document amounts are calculated (Settings → Calculation, stored on company_profile):
 * - taxMode: LINE = tax rounded on every line; TOTAL = tax computed once per tax rate on the document total
 *   (e-Faktur style) and spread over the lines
 * - rounding: HALF_UP (half away from zero) or DOWN (truncate)
 * - amountDecimals: 2, or 0 for whole rupiah (line totals, tax, discounts, shipping, totals)
 * - unitDecimals: 2–6, decimals of the stored unit price excl. tax
 * The same rules are mirrored in document-items.js (toJs()).
 */
final class CalcSettings
{
    public const TAX_MODES        = ['LINE', 'TOTAL'];
    public const ROUNDING_MODES   = ['HALF_UP', 'DOWN'];
    public const AMOUNT_DECIMALS  = [2, 0];
    public const UNIT_DECIMALS    = [2, 3, 4, 5, 6];
    public const SCALE            = 12;

    private static ?self $current = null;

    /**
     * @param string $taxMode        LINE or TOTAL.
     * @param string $rounding       HALF_UP or DOWN.
     * @param int    $amountDecimals 2 or 0.
     * @param int    $unitDecimals   2–6.
     */
    public function __construct(
        public readonly string $taxMode = 'LINE',
        public readonly string $rounding = 'HALF_UP',
        public readonly int $amountDecimals = 2,
        public readonly int $unitDecimals = 2,
    ) {
    }

    /**
     * Default settings (tax per line, half up, 2 decimals everywhere).
     *
     * @return self
     */
    public static function defaults(): self
    {
        return new self();
    }

    /**
     * Settings from a company_profile row (missing / invalid values fall back to the defaults).
     *
     * @param array<string, mixed>|null $profile Profile row.
     *
     * @return self
     */
    public static function fromProfile(?array $profile): self
    {
        $taxMode  = (string) ($profile['calc_tax_mode'] ?? 'LINE');
        $rounding = (string) ($profile['calc_rounding'] ?? 'HALF_UP');
        $amount   = (int) ($profile['calc_amount_decimals'] ?? 2);
        $unit     = (int) ($profile['calc_unit_decimals'] ?? 2);

        return new self(
            in_array($taxMode, self::TAX_MODES, true) ? $taxMode : 'LINE',
            in_array($rounding, self::ROUNDING_MODES, true) ? $rounding : 'HALF_UP',
            in_array($amount, self::AMOUNT_DECIMALS, true) ? $amount : 2,
            in_array($unit, self::UNIT_DECIMALS, true) ? $unit : 2,
        );
    }

    /**
     * The company's settings (cached for the request; defaults when the profile cannot be read).
     *
     * @return self
     */
    public static function current(): self
    {
        if (self::$current === null) {
            try {
                self::$current = self::fromProfile((new CompanyProfileModel())->getProfile());
            } catch (Throwable) {
                return self::defaults();
            }
        }

        return self::$current;
    }

    /**
     * Forget the cached settings (after saving them, and in tests).
     *
     * @return void
     */
    public static function clearCache(): void
    {
        self::$current = null;
    }

    /**
     * Round an amount to the amount decimals; the result always has 2 decimals ("28500000.00").
     *
     * @param string $value Decimal string.
     *
     * @return string
     */
    public function round(string $value): string
    {
        return bcadd(bcround($value, $this->amountDecimals, $this->mode()), '0', 2);
    }

    /**
     * Round a unit price to the unit decimals; the result has 6 decimals (DECIMAL(19,6)).
     *
     * @param string $value Decimal string.
     *
     * @return string
     */
    public function roundUnit(string $value): string
    {
        return bcadd(bcround($value, $this->unitDecimals, $this->mode()), '0', 6);
    }

    /**
     * Smallest amount step as a decimal string ("0.01" or "1").
     *
     * @return string
     */
    public function step(): string
    {
        return $this->amountDecimals === 0 ? '1' : '0.01';
    }

    /**
     * Settings for document-items.js.
     *
     * @return array{taxMode: string, rounding: string, amountDecimals: int, unitDecimals: int}
     */
    public function toJs(): array
    {
        return ['taxMode' => $this->taxMode, 'rounding' => $this->rounding, 'amountDecimals' => $this->amountDecimals, 'unitDecimals' => $this->unitDecimals];
    }

    /**
     * bcround mode for the rounding setting.
     *
     * @return RoundingMode
     */
    private function mode(): RoundingMode
    {
        return $this->rounding === 'DOWN' ? RoundingMode::TowardsZero : RoundingMode::HalfAwayFromZero;
    }
}
