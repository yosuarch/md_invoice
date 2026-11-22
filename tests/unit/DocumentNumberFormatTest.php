<?php

declare(strict_types=1);

use App\Services\DocumentSequence;
use App\Services\ProposalRevisions;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Number formats (Settings → Document numbering) and proposal revision comparison.
 *
 * @internal
 */
final class DocumentNumberFormatTest extends CIUnitTestCase
{
    public function testFormat(): void
    {
        $date = new DateTimeImmutable('2026-08-07');

        $this->assertSame('INV/2026/08/0012', DocumentSequence::format('INV/{Y}/{m}/{seq:4}', 12, $date));
        $this->assertSame('ws26080001', DocumentSequence::format('ws{y}{m}{seq:4}', 1, $date));
        $this->assertSame('WS-07082026-123456', DocumentSequence::format('WS-{d}{m}{Y}-{seq:4}', 123456, $date), 'counter grows beyond its digits');
    }

    public function testValidation(): void
    {
        $this->assertSame([], DocumentSequence::validate('INV/{Y}/{m}/{seq:4}', 'monthly'));
        $this->assertSame([], DocumentSequence::validate('INV/{Y}/{seq:5}', 'yearly'));
        $this->assertSame([], DocumentSequence::validate('C-{seq:6}', 'never'));
        $this->assertNotSame([], DocumentSequence::validate('INV/{Y}/{seq:4}', 'monthly'), 'monthly counter needs the month');
        $this->assertNotSame([], DocumentSequence::validate('INV/{seq:4}', 'yearly'), 'yearly counter needs the year');
        $this->assertNotSame([], DocumentSequence::validate('INV/{Y}/{m}', 'monthly'), 'counter required');
        $this->assertNotSame([], DocumentSequence::validate('INV {Y}{m}{seq:4}', 'monthly'), 'no spaces');
        $this->assertNotSame([], DocumentSequence::validate('{seq:4}-{seq:4}-{Y}{m}', 'monthly'), 'only one counter');
    }

    public function testRevisionDiff(): void
    {
        $line = static fn (int $id, string $qty, string $price): array => [
            'line_id' => $id, 'product_id' => $id, 'title' => 'Item ' . $id, 'unit' => 'PCS', 'type' => 'GOODS',
            'quantity' => $qty, 'unit_price' => $price, 'discount' => '0.00', 'tax_rate' => '11.00', 'line_total' => '0',
        ];

        $old = [$line(1, '10.000', '100000.00'), $line(2, '1.000', '5000.00')];
        $new = [$line(1, '12.000', '90000.00'), $line(3, '1.000', '7000.00')];

        $diff = ProposalRevisions::diff($old, $new);

        $this->assertSame(['changed', 'added', 'removed'], array_column($diff, 'kind'));
        $this->assertSame(['quantity', 'unit_price'], array_column($diff[0]['changes'], 'field'));
        $this->assertTrue(ProposalRevisions::sameContent($old, [$line(1, '10', '100000'), $line(2, '1', '5000')]), 'same values, other scale');
    }
}
