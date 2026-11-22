<?php

declare(strict_types=1);

use App\Services\CalcSettings;
use App\Services\DocumentTotals;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Line and header totals (database.md §11.4): round each line, then sum; tax per line; shipping on the grand total.
 *
 * @internal
 */
final class DocumentTotalsTest extends CIUnitTestCase
{
    public function testLineAndHeaderTotals(): void
    {
        $t = DocumentTotals::calculate([
            ['quantity' => '10', 'unit_price' => '75000', 'discount_amount' => '50000', 'tax_rate' => '11'],
            ['quantity' => '2.5', 'unit_price' => '1000.10', 'tax_rate' => '0'],
        ], '25000');

        $this->assertSame('700000.00', $t['lines'][0]['line_total']);
        $this->assertSame('77000.00', $t['lines'][0]['tax_amount']);
        $this->assertSame('2500.25', $t['lines'][1]['line_total']);
        $this->assertSame('0.00', $t['lines'][1]['tax_amount']);
        $this->assertSame('702500.25', $t['subtotal']);
        $this->assertSame('50000.00', $t['discount_total']);
        $this->assertSame('77000.00', $t['tax_total']);
        $this->assertSame('804500.25', $t['grand_total']);
    }

    public function testRoundsHalfAwayFromZeroPerLine(): void
    {
        // 3 x 0.335 = 1.005 -> 1.01; tax 11% of 1.01 = 0.1111 -> 0.11
        $t = DocumentTotals::calculate([['quantity' => '3', 'unit_price' => '0.335', 'tax_rate' => '11']]);
        $this->assertSame('1.01', $t['lines'][0]['line_total']);
        $this->assertSame('0.11', $t['lines'][0]['tax_amount']);

        // Each line is rounded before summing: 0.01 + 0.01
        $t = DocumentTotals::calculate([
            ['quantity' => '1', 'unit_price' => '0.005'],
            ['quantity' => '1', 'unit_price' => '0.005'],
        ]);
        $this->assertSame('0.02', $t['subtotal']);
    }

    public function testNoFloatErrorsOnLargeAmounts(): void
    {
        $t = DocumentTotals::calculate([['quantity' => '999.999', 'unit_price' => '9999999.99', 'tax_rate' => '11']]);
        $this->assertSame('9999989990.00', $t['lines'][0]['line_total']);
        $this->assertSame('1099998898.90', $t['lines'][0]['tax_amount']);
    }

    public function testEmptyDocument(): void
    {
        $t = DocumentTotals::calculate([]);
        $this->assertSame('0.00', $t['grand_total']);
        $this->assertSame([], $t['lines']);
    }

    public function testPriceInclTaxGivesTheTypedTotal(): void
    {
        // The reported case: 150 × Rp 190.000 incl. PPN 11% must total exactly Rp 28.500.000.
        $t = DocumentTotals::calculate([['quantity' => '150', 'tax_rate' => '11', 'price_base' => 'INCL', 'unit_price_incl' => '190000', 'discount_incl' => '0', 'unit_price' => '171171.17']], '0', CalcSettings::defaults());

        $this->assertSame('28500000.00', $t['grand_total']);
        $this->assertSame('25675675.68', $t['subtotal']);
        $this->assertSame('2824324.32', $t['tax_total']);
    }

    public function testInclLineDiscountIsTakenInclTax(): void
    {
        $t = DocumentTotals::calculate([['quantity' => '2', 'tax_rate' => '11', 'price_base' => 'INCL', 'unit_price_incl' => '111000', 'discount_incl' => '11100']], '0', CalcSettings::defaults());

        $this->assertSame('210900.00', $t['grand_total']); // 2 × 111.000 − 11.100
        $this->assertSame('190000.00', $t['subtotal']);
        $this->assertSame('10000.00', $t['discount_total']);
    }

    public function testTaxOnTheDocumentTotal(): void
    {
        $lines = [
            ['quantity' => '1', 'tax_rate' => '11', 'unit_price' => '10333.33'],
            ['quantity' => '1', 'tax_rate' => '11', 'unit_price' => '20454.55'],
            ['quantity' => '1', 'tax_rate' => '11', 'unit_price' => '5050.51'],
        ];

        $this->assertSame('3942.23', DocumentTotals::calculate($lines, '0', new CalcSettings('LINE'))['tax_total']);

        $t = DocumentTotals::calculate($lines, '0', new CalcSettings('TOTAL'));
        $this->assertSame('3942.22', $t['tax_total']);
        $this->assertSame('35838.39', $t['subtotal']);
        $this->assertSame('3942.22', array_reduce($t['lines'], static fn (string $sum, array $l): string => bcadd($sum, $l['tax_amount'], 2), '0'));
    }

    public function testTaxOnTheTotalWithInclLines(): void
    {
        $t = DocumentTotals::calculate([
            ['quantity' => '150', 'tax_rate' => '11', 'price_base' => 'INCL', 'unit_price_incl' => '190000'],
            ['quantity' => '1', 'tax_rate' => '11', 'price_base' => 'INCL', 'unit_price_incl' => '50000'],
        ], '0', new CalcSettings('TOTAL'));

        $this->assertSame('28550000.00', $t['grand_total']);
        $this->assertSame('25720720.72', $t['subtotal']);
        $this->assertSame('2829279.28', $t['tax_total']);
        $this->assertSame('28500000.00', bcadd($t['lines'][0]['line_total'], $t['lines'][0]['tax_amount'], 2), 'each line keeps its incl. total');
    }

    public function testRoundDown(): void
    {
        $t = DocumentTotals::calculate([['quantity' => '3', 'unit_price' => '0.335', 'tax_rate' => '11']], '0', new CalcSettings('LINE', 'DOWN'));

        $this->assertSame('1.00', $t['lines'][0]['line_total']);
        $this->assertSame('0.11', $t['lines'][0]['tax_amount']);
    }

    public function testWholeRupiah(): void
    {
        $s = new CalcSettings('LINE', 'HALF_UP', 0);
        $t = DocumentTotals::calculate([['quantity' => '1', 'unit_price' => '10333.33', 'tax_rate' => '11']], '1500.50', $s);

        $this->assertSame('10333.00', $t['subtotal']);
        $this->assertSame('1137.00', $t['tax_total']);
        $this->assertSame('12971.00', $t['grand_total']); // 10.333 + 1.137 + 1.501

        $t = DocumentTotals::calculate([['quantity' => '150', 'tax_rate' => '11', 'price_base' => 'INCL', 'unit_price_incl' => '190000']], '0', $s);
        $this->assertSame(['25675676.00', '2824324.00', '28500000.00'], [$t['subtotal'], $t['tax_total'], $t['grand_total']]);
    }

    public function testUnitPriceDecimals(): void
    {
        $this->assertSame('171171.170000', DocumentTotals::exclFromIncl('190000', '11', CalcSettings::defaults()));
        $this->assertSame('171171.171200', DocumentTotals::exclFromIncl('190000', '11', new CalcSettings('LINE', 'HALF_UP', 2, 4)));
        $this->assertSame('171171.171171', DocumentTotals::exclFromIncl('190000', '11', new CalcSettings('LINE', 'HALF_UP', 2, 6)));
        $this->assertSame('171171.171100', DocumentTotals::exclFromIncl('190000', '11', new CalcSettings('LINE', 'DOWN', 2, 4)));
    }

    public function testSpreadAddsUp(): void
    {
        $this->assertSame(['0.03', '0.01', '0.01'], DocumentTotals::spread('0.05', ['1', '1', '1'], CalcSettings::defaults()));
        $this->assertSame(['2.00', '1.00'], DocumentTotals::spread('3', ['1', '1'], new CalcSettings('TOTAL', 'HALF_UP', 0)));
    }
}
