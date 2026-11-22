<?php

declare(strict_types=1);

namespace App\Models;

/**
 * delivery_note: goods shipped for an issued invoice (surat jalan), through App\Services\DeliveryService.
 * Status is derived: PREPARING (created) → SHIPPED (shipped_at) → DELIVERED (delivered_at), or CANCELLED (cancelled_at).
 * PREPARING and SHIPPED are "open". Never deleted; an open note can be cancelled with a reason (its number stays used).
 * The tracking timeline is in delivery_note_event.
 */
class DeliveryNoteModel extends BaseModel
{
    public const PREPARING = 'PREPARING';
    public const SHIPPED   = 'SHIPPED';
    public const DELIVERED = 'DELIVERED';
    public const CANCELLED = 'CANCELLED';

    /**
     * Status labels.
     *
     * @var array<string, string>
     */
    public const STATUSES = [
        self::PREPARING => 'Preparing',
        self::SHIPPED   => 'On the way',
        self::DELIVERED => 'Delivered',
        self::CANCELLED => 'Cancelled',
    ];

    protected $table         = 'delivery_note';
    protected array $lowercase = ['shipping_address', 'courier_name', 'received_by_name']; // stored lower case, shown with up()
    protected $allowedFields = [
        'delivery_number', 'handover_number', 'invoice_id', 'shipment_id', 'ship_date', 'shipping_address', 'courier_name', 'tracking_number',
        'shipped_at', 'delivered_at', 'received_by_name', 'note', 'created_by', 'cancelled_at', 'cancelled_by', 'cancel_reason',
    ];
    protected array $nullable    = ['ship_date', 'courier_name', 'tracking_number', 'received_by_name', 'note'];
    protected array $fieldLabels = [
        'shipment_id'      => 'Shipment method',
        'ship_date'        => 'Ship date',
        'shipping_address' => 'Shipping address',
        'courier_name'     => 'Courier',
        'tracking_number'  => 'Tracking number',
        'received_by_name' => 'Received by',
    ];

    protected $validationRules = [
        'shipment_id'      => 'required|is_natural_no_zero|is_not_unique[shipment.id]',
        'ship_date'        => 'required|valid_date[Y-m-d]',
        'shipping_address' => 'required|max_length[1000]',
        'courier_name'     => 'permit_empty|max_length[64]',
        'tracking_number'  => 'permit_empty|max_length[64]',
        'note'             => 'permit_empty|max_length[2000]',
    ];

    /**
     * Derived status of a delivery note row.
     *
     * @param array<string, mixed> $note Delivery note row.
     *
     * @return string PREPARING | SHIPPED | DELIVERED | CANCELLED
     */
    public static function status(array $note): string
    {
        return match (true) {
            $note['cancelled_at'] !== null          => self::CANCELLED,
            $note['delivered_at'] !== null          => self::DELIVERED,
            ($note['shipped_at'] ?? null) !== null => self::SHIPPED,
            default                                 => self::PREPARING,
        };
    }

    /**
     * Whether the note is still open (preparing or on the way): editable, can be shipped/delivered/cancelled.
     *
     * @param array<string, mixed> $note Delivery note row.
     *
     * @return bool
     */
    public static function isOpen(array $note): bool
    {
        return in_array(self::status($note), [self::PREPARING, self::SHIPPED], true);
    }

    /**
     * Badge HTML for a delivery note row.
     *
     * @param array<string, mixed> $note Delivery note row.
     *
     * @return string
     */
    public static function badge(array $note): string
    {
        $status = self::status($note);
        $class  = match ($status) {
            self::DELIVERED => 'bg-success',
            self::CANCELLED => 'bg-dark',
            self::SHIPPED   => 'bg-primary',
            default         => 'bg-secondary',
        };

        return '<span class="badge ' . $class . '">' . esc(code_label('deliveryStatus', $status, self::STATUSES[$status])) . '</span>';
    }

    /**
     * Notes joined with invoice, customer and shipment method.
     *
     * @return $this
     */
    public function withDetails(): self
    {
        return $this->select(
            'delivery_note.*, invoice.invoice_number, invoice.customer_id, customer_list.cl_organization_name AS customer_name,'
            . ' shipment.code AS shipment_code, shipment.name AS shipment_name, users.username, users.full_name,'
            . ' cu.username AS cancelled_username, cu.full_name AS cancelled_full_name',
        )
            ->join('invoice', 'invoice.id = delivery_note.invoice_id')
            ->join('customer_list', 'customer_list.id = invoice.customer_id')
            ->join('shipment', 'shipment.id = delivery_note.shipment_id')
            ->join('users', 'users.id = delivery_note.created_by', 'left')
            ->join('users cu', 'cu.id = delivery_note.cancelled_by', 'left');
    }

    /**
     * List with search (number, invoice, customer, tracking) and status filter, newest first.
     *
     * @param array{q?: string, status?: string, customer?: string} $filters status: PREPARING | SHIPPED | DELIVERED | CANCELLED | open.
     *
     * @return $this
     */
    public function search(array $filters): self
    {
        $this->withDetails()->orderBy('delivery_note.id', 'DESC');

        $q = trim((string) ($filters['q'] ?? ''));

        if ($q !== '') {
            $this->groupStart()
                ->like('delivery_note.delivery_number', $q)
                ->orLike('invoice.invoice_number', $q)
                ->orLike('customer_list.cl_organization_name', $q)
                ->orLike('delivery_note.tracking_number', $q)
                ->groupEnd();
        }

        match ((string) ($filters['status'] ?? '')) {
            'open'          => $this->where('delivery_note.cancelled_at', null)->where('delivery_note.delivered_at', null),
            self::PREPARING => $this->where('delivery_note.cancelled_at', null)->where('delivery_note.delivered_at', null)->where('delivery_note.shipped_at', null),
            self::SHIPPED   => $this->where('delivery_note.cancelled_at', null)->where('delivery_note.delivered_at', null)->where('delivery_note.shipped_at IS NOT NULL', null, false),
            self::DELIVERED => $this->where('delivery_note.cancelled_at', null)->where('delivery_note.delivered_at IS NOT NULL', null, false),
            self::CANCELLED => $this->where('delivery_note.cancelled_at IS NOT NULL', null, false),
            default         => null,
        };

        if (($filters['customer'] ?? '') !== '') {
            $this->where('invoice.customer_id', (int) $filters['customer']);
        }

        return $this;
    }

    /**
     * Delivery notes of an invoice, oldest first.
     *
     * @param int $invoiceId Invoice id.
     *
     * @return list<array<string, mixed>>
     */
    public function forInvoice(int $invoiceId): array
    {
        return $this->withDetails()->where('delivery_note.invoice_id', $invoiceId)->orderBy('delivery_note.id')->findAll();
    }
}
