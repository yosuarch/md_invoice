<?php

declare(strict_types=1);

namespace App\Models;

/**
 * credit_note_item: credited lines, each linked to the invoice line it corrects (values copied from it).
 */
class CreditNoteItemModel extends BaseModel
{
    protected $table         = 'credit_note_item';
    protected array $lowercase = ['item_title'];
    protected $allowedFields = [
        'credit_note_id', 'invoice_item_id', 'product_id', 'variant_id', 'item_title', 'item_description', 'unit_code', 'item_type', 'quantity', 'unit_price',
        'discount_amount', 'tax_rate', 'tax_code', 'price_base', 'unit_price_incl', 'discount_incl', 'tax_amount', 'line_total',
    ];

    /**
     * Lines of a credit note.
     *
     * @param int $creditNoteId Credit note id.
     *
     * @return list<array<string, mixed>>
     */
    public function forCreditNote(int $creditNoteId): array
    {
        return $this->where('credit_note_id', $creditNoteId)->orderBy('id')->findAll();
    }

    /**
     * Quantity and amount already credited per invoice line (all credit notes of the invoice).
     *
     * @param int $invoiceId Invoice id.
     *
     * @return array<int, array{quantity: string, line_total: string}> invoice_item_id => sums
     */
    public function creditedByInvoiceItem(int $invoiceId): array
    {
        $rows = $this->db->table('credit_note_item cni')
            ->select('cni.invoice_item_id, SUM(cni.quantity) AS quantity, SUM(cni.line_total) AS line_total')
            ->join('credit_note cn', 'cn.id = cni.credit_note_id')
            ->where('cn.invoice_id', $invoiceId)
            ->where('cn.deleted_at', null)
            ->where('cni.deleted_at', null)
            ->groupBy('cni.invoice_item_id')
            ->get()->getResultArray();

        $result = [];

        foreach ($rows as $row) {
            $result[(int) $row['invoice_item_id']] = ['quantity' => (string) $row['quantity'], 'line_total' => (string) $row['line_total']];
        }

        return $result;
    }
}
