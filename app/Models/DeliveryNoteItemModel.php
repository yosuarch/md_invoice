<?php

declare(strict_types=1);

namespace App\Models;

/**
 * delivery_note_item: quantity of an invoice line shipped with a delivery note (partial deliveries allowed;
 * total shipped on non-cancelled notes <= invoiced quantity, enforced in App\Services\DeliveryService).
 */
class DeliveryNoteItemModel extends BaseModel
{
    protected $table         = 'delivery_note_item';
    protected $allowedFields = ['delivery_note_id', 'invoice_item_id', 'quantity'];

    /**
     * Lines of a delivery note with the invoice line's title, unit and SKU.
     *
     * @param int $noteId Delivery note id.
     *
     * @return list<array<string, mixed>>
     */
    public function forNote(int $noteId): array
    {
        return $this->select('delivery_note_item.*, invoice_item.item_title, invoice_item.item_description, invoice_item.unit_code, invoice_item.quantity AS invoiced_quantity, COALESCE(product_variant.pv_sku, product_list.pl_sku) AS pl_sku')
            ->join('invoice_item', 'invoice_item.id = delivery_note_item.invoice_item_id')
            ->join('product_list', 'product_list.id = invoice_item.product_id', 'left')
            ->join('product_variant', 'product_variant.id = invoice_item.variant_id', 'left')
            ->where('delivery_note_id', $noteId)
            ->orderBy('delivery_note_item.id')
            ->findAll();
    }

    /**
     * Quantity shipped per invoice line on non-cancelled delivery notes.
     *
     * @param int $invoiceId Invoice id.
     *
     * @return array<int, array{shipped: string, delivered: string}> invoice_item_id => shipped (open + delivered), delivered (confirmed)
     */
    public function shippedByInvoiceItem(int $invoiceId): array
    {
        $rows = $this->db->table('delivery_note_item dni')
            ->select('dni.invoice_item_id, SUM(dni.quantity) AS shipped,'
                . ' SUM(CASE WHEN dn.delivered_at IS NOT NULL THEN dni.quantity ELSE 0 END) AS delivered')
            ->join('delivery_note dn', 'dn.id = dni.delivery_note_id')
            ->where('dn.invoice_id', $invoiceId)
            ->where('dn.cancelled_at', null)
            ->where('dn.deleted_at', null)
            ->where('dni.deleted_at', null)
            ->groupBy('dni.invoice_item_id')
            ->get()->getResultArray();

        $result = [];

        foreach ($rows as $row) {
            $result[(int) $row['invoice_item_id']] = ['shipped' => (string) $row['shipped'], 'delivered' => (string) $row['delivered']];
        }

        return $result;
    }
}
