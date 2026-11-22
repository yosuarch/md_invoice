<?php

declare(strict_types=1);

use App\Models\DocumentNumberingModel;
use App\Services\DocumentSequence;
use Tests\Support\AppTestCase;

/**
 * Gap-free document numbers from document_sequence with the formats of document_numbering.
 *
 * @internal
 */
final class NumberingTest extends AppTestCase
{
    public function testSequentialPerTypeAndPreviewDoesNotConsume(): void
    {
        $seq  = new DocumentSequence();
        $date = new DateTimeImmutable('2030-01-15');

        $this->assertSame('QUO/2030/01/0001', $seq->preview('QUO', $date));
        $this->assertSame('QUO/2030/01/0001', $seq->next('QUO', $date));
        $this->assertSame('QUO/2030/01/0002', $seq->next('QUO', $date));
        $this->assertSame('INV/2030/01/0001', $seq->next('INV', $date), 'each type has its own counter');
        $this->assertSame('QUO/2030/01/0003', $seq->preview('QUO', $date));
    }

    public function testMonthlyCounterRestarts(): void
    {
        $seq = new DocumentSequence();

        $seq->next('CN', new DateTimeImmutable('2030-02-27'));
        $this->assertSame('CN/2030/03/0001', $seq->next('CN', new DateTimeImmutable('2030-03-01')));
    }

    public function testSkuFormat(): void
    {
        $this->assertSame('ws30040001', (new DocumentSequence())->next('SKU', new DateTimeImmutable('2030-04-10')));
    }

    public function testCustomFormatAndYearlyCounter(): void
    {
        $model = new DocumentNumberingModel();
        $row   = $model->forType('DO');
        $model->update((int) $row['id'], ['pattern' => 'SJ-{Y}-{seq:5}', 'period' => 'yearly']);
        DocumentSequence::clearCache();

        $seq = new DocumentSequence();
        $this->assertSame('SJ-2031-00001', $seq->next('DO', new DateTimeImmutable('2031-01-31')));
        $this->assertSame('SJ-2031-00002', $seq->next('DO', new DateTimeImmutable('2031-07-01')), 'yearly: no restart per month');
    }

    public function testExistingNumberIsSkipped(): void
    {
        $date = new DateTimeImmutable('2032-05-05');
        $seq  = new DocumentSequence();
        $sku  = $seq->next('SKU', $date); // ws32050001

        // A product holds that number, then the counter is rewound: the next number would repeat it -> skipped.
        $productId = $this->createProduct();
        db_connect()->table('product_list')->where('id', $productId)->update(['pl_sku' => $sku]);
        db_connect()->table('document_sequence')->where(['doc_type' => 'SKU', 'period' => '2032-05'])->update(['last_number' => 0]);

        $this->assertSame('ws32050002', $seq->next('SKU', $date));
    }
}
