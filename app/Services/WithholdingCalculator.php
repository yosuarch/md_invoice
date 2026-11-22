<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Computes the taxes a customer's treasurer is expected to withhold/collect on an invoice,
 * from data-driven rules (tax_withholding_rule). Pure: no database access.
 *
 * Rule evaluation (per active rule valid on the invoice date):
 *  1. requires_pkp and the company is not PKP          -> skip
 *  2. customer_status (ALL/PUBLIC/PRIVATE) does not match -> skip (non-school customers count as PRIVATE)
 *  3. skip_for_bos and the funding source is BOS        -> skip (e.g. PPh 22, PMK 81/2024 Article 219)
 *  4. base = sum of matching lines (item_type ALL/GOODS/SERVICE):
 *       base 'DPP' -> line_total (excl. PPN), base 'PPN' -> tax_amount
 *  5. threshold: invoice amount must be > threshold, measured on DPP or GROSS (DPP + PPN) of the whole invoice
 *  6. rate: umkm_rate (PPh final 0.5%, kind PPH_FINAL) if the company has a valid SK PP 55 and the rule has one,
 *     else rate; amount = round(base * rate / 100, 2)
 */
final class WithholdingCalculator
{
    /**
     * @param list<array<string, mixed>> $rules       Rows of tax_withholding_rule valid on the invoice date.
     * @param list<array{item_type: string, line_total: string|float, tax_amount: string|float}> $lines Invoice lines.
     * @param array{school_status: string|null}                  $customer
     * @param array{is_bos: bool}                                 $funding  Funding source flags (is_bos false when unknown).
     * @param array{is_pkp: bool, has_umkm_certificate: bool}     $company
     *
     * @return list<array{rule_id: int, rule_code: string, tax_kind: string, description: string, base_amount: string, rate: string, amount: string, regulation: ?string}>
     */
    public function calculate(array $rules, array $lines, array $customer, array $funding, array $company): array
    {
        // Exact decimal arithmetic (bcmath), same rounding as DocumentTotals.
        $dppTotal = '0';
        $ppnTotal = '0';

        foreach ($lines as $line) {
            $dppTotal = bcadd($dppTotal, DocumentTotals::num($line['line_total']), 2);
            $ppnTotal = bcadd($ppnTotal, DocumentTotals::num($line['tax_amount']), 2);
        }

        $customerStatus = ($customer['school_status'] ?? null) ?: 'PRIVATE';
        $results        = [];

        foreach ($rules as $rule) {
            if ($rule['requires_pkp'] && ! $company['is_pkp']) {
                continue;
            }

            if ($rule['customer_status'] !== 'ALL' && $rule['customer_status'] !== $customerStatus) {
                continue;
            }

            if ($rule['skip_for_bos'] && $funding['is_bos']) {
                continue;
            }

            $thresholdValue = $rule['threshold_basis'] === 'GROSS' ? bcadd($dppTotal, $ppnTotal, 2) : $dppTotal;

            if (bccomp($thresholdValue, DocumentTotals::num($rule['threshold']), 2) <= 0) {
                continue;
            }

            $base = '0';

            foreach ($lines as $line) {
                if ($rule['item_type'] === 'ALL' || $rule['item_type'] === $line['item_type']) {
                    $base = bcadd($base, DocumentTotals::num($rule['base'] === 'PPN' ? $line['tax_amount'] : $line['line_total']), 2);
                }
            }

            if (bccomp($base, '0', 2) <= 0) {
                continue;
            }

            $useUmkm = $company['has_umkm_certificate'] && $rule['umkm_rate'] !== null && $rule['tax_kind'] !== 'PPN';
            $rate    = bcadd(DocumentTotals::num($useUmkm ? $rule['umkm_rate'] : $rule['rate']), '0', 2);
            $amount  = DocumentTotals::round2(bcdiv(bcmul($base, $rate, 6), '100', 6));

            $results[] = [
                'rule_id'     => (int) $rule['id'],
                'rule_code'   => (string) $rule['code'],
                'tax_kind'    => $useUmkm ? 'PPH_FINAL' : (string) $rule['tax_kind'],
                'description' => $useUmkm ? 'UMKM final income tax 0.5% (PP 55/2022) replacing ' . $rule['name'] : (string) $rule['name'],
                'base_amount' => $base,
                'rate'        => $rate,
                'amount'      => $amount,
                'regulation'  => $rule['regulation'] ?? null,
            ];
        }

        return $results;
    }

    /**
     * Sum of withholding amounts.
     *
     * @param list<array{amount: string}> $withholdings Output of calculate().
     *
     * @return string Decimal string.
     */
    public static function total(array $withholdings): string
    {
        $total = '0.00';

        foreach ($withholdings as $w) {
            $total = bcadd($total, DocumentTotals::num($w['amount']), 2);
        }

        return $total;
    }
}
