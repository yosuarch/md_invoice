<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CompanyProfileModel;
use App\Models\TaxRateModel;

/**
 * Tax choices for document lines (Dolibarr style: the product gives the default, each line may choose another rate).
 */
final class LineTax
{
    /**
     * Tax codes valid on a date with their rate, for the per-line select. Empty when the company is not PKP
     * (no PPN is charged: lines are always 0%).
     *
     * @param string|null $date Y-m-d (default today).
     *
     * @return array<string, string> code => rate
     */
    public static function options(?string $date = null): array
    {
        $profile = (new CompanyProfileModel())->getProfile();

        if ($profile !== null && (int) $profile['is_pkp'] !== 1) {
            return [];
        }

        return array_filter((new TaxRateModel())->validOn($date), static fn (string $rate): bool => bccomp($rate, '0', 2) > 0);
    }
}
