<?php

declare(strict_types=1);

namespace App\Models;

/**
 * invoice_withholding: taxes the customer's treasurer is expected to withhold/collect,
 * computed by App\Services\WithholdingCalculator when the invoice is issued (copied, never recomputed).
 */
class InvoiceWithholdingModel extends BaseModel
{
    /**
     * Display labels per tax kind.
     *
     * @var array<string, string>
     */
    public const KINDS = [
        'PPN'       => 'PPN collected',
        'PPH22'     => 'PPh 22',
        'PPH23'     => 'PPh 23',
        'PPH_FINAL' => 'PPh final UMKM',
    ];

    protected $table         = 'invoice_withholding';
    protected $allowedFields = ['invoice_id', 'rule_id', 'tax_kind', 'description', 'base_amount', 'rate', 'amount', 'regulation'];
    protected array $nullable = ['rule_id', 'regulation'];

    /**
     * Expected withholdings of an invoice with the amount already withheld per row (reversed payments excluded).
     *
     * @param int $invoiceId Invoice id.
     *
     * @return list<array<string, mixed>> Rows with an extra 'withheld' column.
     */
    public function forInvoice(int $invoiceId): array
    {
        return $this->select('invoice_withholding.*, COALESCE(SUM(pw.amount), 0) AS withheld')
            ->join('payment_withholding pw', 'pw.invoice_withholding_id = invoice_withholding.id AND pw.deleted_at IS NULL'
                . ' AND pw.payment_id IN (SELECT p.id FROM payment p WHERE p.reversed_at IS NULL)', 'left', false)
            ->where('invoice_withholding.invoice_id', $invoiceId)
            ->groupBy('invoice_withholding.id')
            ->orderBy('invoice_withholding.id')
            ->findAll();
    }
}
