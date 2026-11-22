<?php

declare(strict_types=1);

use App\Services\WithholdingCalculator;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Expected treasurer withholding (database.md §8a) with the seeded rules.
 *
 * @internal
 */
final class WithholdingCalculatorTest extends CIUnitTestCase
{
    /** @var list<array<string, mixed>> */
    private array $rules = [
        ['id' => 1, 'code' => 'PPN_COLLECT_GOV', 'name' => 'PPN', 'tax_kind' => 'PPN', 'base' => 'PPN', 'item_type' => 'ALL', 'customer_status' => 'PUBLIC',
            'rate' => '100.00', 'umkm_rate' => null, 'threshold' => '2000000.00', 'threshold_basis' => 'GROSS', 'skip_for_bos' => 0, 'requires_pkp' => 1, 'regulation' => 'PMK 81/2024'],
        ['id' => 2, 'code' => 'PPH22_GOV', 'name' => 'PPh 22', 'tax_kind' => 'PPH22', 'base' => 'DPP', 'item_type' => 'GOODS', 'customer_status' => 'PUBLIC',
            'rate' => '1.50', 'umkm_rate' => '0.50', 'threshold' => '2000000.00', 'threshold_basis' => 'DPP', 'skip_for_bos' => 1, 'requires_pkp' => 0, 'regulation' => null],
        ['id' => 3, 'code' => 'PPH23_SERVICE', 'name' => 'PPh 23', 'tax_kind' => 'PPH23', 'base' => 'DPP', 'item_type' => 'SERVICE', 'customer_status' => 'ALL',
            'rate' => '2.00', 'umkm_rate' => '0.50', 'threshold' => '0.00', 'threshold_basis' => 'DPP', 'skip_for_bos' => 0, 'requires_pkp' => 0, 'regulation' => null],
    ];

    /** @var array{is_pkp: bool, has_umkm_certificate: bool} */
    private array $pkp = ['is_pkp' => true, 'has_umkm_certificate' => false];

    /**
     * @return array<string, string> tax_kind => amount
     */
    private function amounts(array $lines, ?string $status, bool $bos, array $company): array
    {
        $result = (new WithholdingCalculator())->calculate($this->rules, $lines, ['school_status' => $status], ['is_bos' => $bos], $company);

        return array_column($result, 'amount', 'tax_kind');
    }

    public function testPublicSchoolGoodsAboveThreshold(): void
    {
        $lines = [['item_type' => 'GOODS', 'line_total' => '3000000.00', 'tax_amount' => '330000.00']];

        $this->assertSame(['PPN' => '330000.00', 'PPH22' => '45000.00'], $this->amounts($lines, 'PUBLIC', false, $this->pkp));
    }

    public function testBosFundsSkipPph22ButNotPpnCollection(): void
    {
        $lines = [['item_type' => 'GOODS', 'line_total' => '3000000.00', 'tax_amount' => '330000.00']];

        $this->assertSame(['PPN' => '330000.00'], $this->amounts($lines, 'PUBLIC', true, $this->pkp));
    }

    public function testThresholdIsExclusive(): void
    {
        // DPP exactly 2,000,000 -> no PPh 22; gross 2,220,000 > 2,000,000 -> PPN collected
        $lines = [['item_type' => 'GOODS', 'line_total' => '2000000.00', 'tax_amount' => '220000.00']];
        $this->assertSame(['PPN' => '220000.00'], $this->amounts($lines, 'PUBLIC', false, $this->pkp));

        // Gross 1,998,000 -> nothing
        $lines = [['item_type' => 'GOODS', 'line_total' => '1800000.00', 'tax_amount' => '198000.00']];
        $this->assertSame([], $this->amounts($lines, 'PUBLIC', false, $this->pkp));
    }

    public function testPrivateSchoolOnlyServicesWithheld(): void
    {
        $lines = [
            ['item_type' => 'GOODS', 'line_total' => '5000000.00', 'tax_amount' => '550000.00'],
            ['item_type' => 'SERVICE', 'line_total' => '1000000.00', 'tax_amount' => '110000.00'],
        ];

        $this->assertSame(['PPH23' => '20000.00'], $this->amounts($lines, 'PRIVATE', false, $this->pkp));
        $this->assertSame(['PPH23' => '20000.00'], $this->amounts($lines, null, false, $this->pkp), 'non-school = PRIVATE');
    }

    public function testUmkmCertificateReplacesIncomeTaxRates(): void
    {
        $lines  = [
            ['item_type' => 'GOODS', 'line_total' => '3000000.00', 'tax_amount' => '330000.00'],
            ['item_type' => 'SERVICE', 'line_total' => '1000000.00', 'tax_amount' => '110000.00'],
        ];
        $result = (new WithholdingCalculator())->calculate($this->rules, $lines, ['school_status' => 'PUBLIC'], ['is_bos' => false], ['is_pkp' => true, 'has_umkm_certificate' => true]);

        $this->assertSame(['PPN', 'PPH_FINAL', 'PPH_FINAL'], array_column($result, 'tax_kind'));
        $this->assertSame(['440000.00', '15000.00', '5000.00'], array_column($result, 'amount'));
    }

    public function testNoPpnCollectionWhenCompanyIsNotPkp(): void
    {
        $lines = [['item_type' => 'GOODS', 'line_total' => '3000000.00', 'tax_amount' => '0.00']];

        $this->assertSame(['PPH22' => '45000.00'], $this->amounts($lines, 'PUBLIC', false, ['is_pkp' => false, 'has_umkm_certificate' => false]));
    }

    public function testTotal(): void
    {
        $this->assertSame('45000.50', WithholdingCalculator::total([['amount' => '45000.00'], ['amount' => '0.50']]));
    }
}
