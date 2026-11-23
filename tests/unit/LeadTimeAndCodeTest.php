<?php

declare(strict_types=1);

use App\Services\DocumentVerification;
use App\Services\LeadTime;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Lead-time calculation (pure) and the human-readable verification codes.
 *
 * @internal
 */
final class LeadTimeAndCodeTest extends CIUnitTestCase
{
    public function testDurationsOfAFinishedOrder(): void
    {
        $d = LeadTime::durations([
            'created'   => '2026-11-01 08:00:00',
            'sent'      => '2026-11-01 14:00:00',
            'approved'  => '2026-11-03 08:00:00',
            'issued'    => '2026-11-03 10:00:00',
            'paid'      => '2026-11-10 10:00:00',
            'noted'     => '2026-11-04 10:00:00',
            'shipped'   => '2026-11-05 10:00:00',
            'delivered' => '2026-11-08 10:00:00',
            'has_goods' => true,
        ]);

        $this->assertSame(0.3, $d['preparation']['days']);
        $this->assertSame(21600, $d['preparation']['seconds']);
        $this->assertSame(1.8, $d['approval']['days']);
        $this->assertSame(7.0, $d['payment']['days']);
        $this->assertSame(3.0, $d['transit']['days']);
        $this->assertSame('2026-11-10 10:00:00', $d['total']['to'], 'finished = later of paid and delivered');
        $this->assertSame(9.1, $d['total']['days']);
    }

    public function testMissingStepsAndServicesOnly(): void
    {
        $open = LeadTime::durations(['created' => '2026-11-01 08:00:00', 'sent' => null, 'has_goods' => true]);
        $this->assertNull($open['approval']['days']);
        $this->assertNull($open['total']['days'], 'not finished');

        $ts = ['created' => '2026-11-01 08:00:00', 'issued' => '2026-11-02 08:00:00', 'paid' => '2026-11-04 08:00:00', 'has_goods' => false];
        $this->assertSame('2026-11-04 08:00:00', LeadTime::finished($ts), 'services only: finished when paid');
        $this->assertNull(LeadTime::finished(['paid' => '2026-11-04 08:00:00', 'has_goods' => true, 'delivered' => null]), 'goods not delivered yet');

        // A payment dated before the invoice was issued never gives a negative duration.
        $back = LeadTime::durations(['issued' => '2026-11-03 10:00:00', 'paid' => '2026-11-03 00:00:00']);
        $this->assertSame(0, $back['payment']['seconds']);
    }

    public function testStatsAndParts(): void
    {
        $s = LeadTime::stats([4.0, null, 1.0, 3.0, 2.0]);
        $this->assertSame(['count' => 4, 'avg' => 2.5, 'median' => 2.5, 'min' => 1.0, 'max' => 4.0], $s);
        $this->assertSame(0, LeadTime::stats([null])['count']);

        $this->assertSame(['2,5', 'day'], LeadTime::parts(216000));
        $this->assertSame(['3,0', 'hour'], LeadTime::parts(10800));
        $this->assertSame(['1', 'minute'], LeadTime::parts(5));
        $this->assertNull(LeadTime::parts(null));
    }

    public function testVerificationCodes(): void
    {
        $code = DocumentVerification::randomCode();
        $this->assertSame(8, strlen($code));
        $this->assertSame(8, strspn($code, DocumentVerification::CODE_ALPHABET));
        $this->assertDoesNotMatchRegularExpression('/[ILOU]/', DocumentVerification::CODE_ALPHABET);

        $this->assertSame('K7Q2M9XD', DocumentVerification::normalizeCode('k7q2-m9xd'));
        $this->assertSame('K7Q2M9XD', DocumentVerification::normalizeCode(' K7Q2 M9XD '));
        $this->assertSame('10Q2M9XD', DocumentVerification::normalizeCode('IOQ2M9XD'), 'I → 1, O → 0');
        $this->assertSame('11Q2M9XD', DocumentVerification::normalizeCode('lIQ2M9XD'), 'L → 1');
        $this->assertNull(DocumentVerification::normalizeCode('K7Q2M9X'));
        $this->assertNull(DocumentVerification::normalizeCode('K7Q2M9XU'), 'U is not in the alphabet');
        $this->assertNull(DocumentVerification::normalizeCode("K7Q2'M9X"));

        $this->assertSame('K7Q2-M9XD', DocumentVerification::formatCode('K7Q2M9XD'));
    }
}
