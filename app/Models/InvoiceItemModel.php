<?php

declare(strict_types=1);

namespace App\Models;

/**
 * invoice_item: invoice lines, copied from the proposal (or from the product for lines added to a draft).
 * Locked once the invoice is issued.
 */
class InvoiceItemModel extends BaseModel
{
    protected $table         = 'invoice_item';
    protected array $lowercase = ['item_title'];
    protected $allowedFields = [
        'invoice_id', 'product_id', 'variant_id', 'item_title', 'item_description', 'unit_code', 'item_type', 'quantity', 'unit_price',
        'discount_amount', 'tax_rate', 'tax_code', 'price_base', 'unit_price_incl', 'discount_incl', 'tax_amount', 'line_total',
    ];

    protected array $referencedBy = ['credit_note_item' => 'invoice_item_id', 'delivery_note_item' => 'invoice_item_id'];

    /**
     * Lines of an invoice in entry order, with the product SKU.
     *
     * @param int $invoiceId Invoice id.
     *
     * @return list<array<string, mixed>>
     */
    public function forInvoice(int $invoiceId): array
    {
        return $this->select('invoice_item.*, COALESCE(product_variant.pv_sku, product_list.pl_sku) AS pl_sku')
            ->join('product_list', 'product_list.id = invoice_item.product_id', 'left')
            ->join('product_variant', 'product_variant.id = invoice_item.variant_id', 'left')
            ->where('invoice_id', $invoiceId)
            ->orderBy('invoice_item.id')
            ->findAll();
    }
}
