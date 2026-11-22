<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CustomerAddressModel;
use App\Models\DeliveryEventModel;
use App\Models\DeliveryNoteItemModel;
use App\Models\DeliveryNoteModel;
use App\Models\InvoiceItemModel;
use App\Models\ShipmentModel;
use CodeIgniter\Database\BaseConnection;
use RuntimeException;

/**
 * Delivery notes (surat jalan) for issued invoices, database.md §9 / §11.14:
 * - goods lines only; partial quantities; per line: shipped on non-cancelled notes <= invoiced quantity
 * - number from document_sequence (DO), shipping address copied (editable while open)
 * - THIRD_PARTY shipment requires the courier name
 * - PREPARING → SHIPPED (markShipped) → DELIVERED (receiver + date), tracking updates while open;
 *   open notes can be CANCELLED (reason); never deleted, lines fixed; every step is logged in delivery_note_event
 */
final class DeliveryService
{
    /**
     * Header fields accepted from the form.
     *
     * @var list<string>
     */
    public const FIELDS = ['shipment_id', 'ship_date', 'shipping_address', 'courier_name', 'tracking_number', 'note'];

    /**
     * Invoice statuses that allow new delivery notes.
     *
     * @var list<string>
     */
    public const DELIVERABLE = [InvoiceWorkflow::ISSUED, InvoiceWorkflow::PARTIALLY_PAID, InvoiceWorkflow::OVERDUE, InvoiceWorkflow::PAID];

    public function __construct(
        private readonly DeliveryNoteModel $notes = new DeliveryNoteModel(),
        private readonly DeliveryNoteItemModel $items = new DeliveryNoteItemModel(),
    ) {
    }

    /**
     * Goods lines of an invoice with shipped / delivered / remaining quantities.
     *
     * @param int $invoiceId Invoice id.
     *
     * @return list<array<string, mixed>> Invoice item rows plus shipped, delivered, remaining.
     */
    public function deliverableLines(int $invoiceId): array
    {
        $shipped = $this->items->shippedByInvoiceItem($invoiceId);
        $rows    = [];

        foreach ((new InvoiceItemModel())->forInvoice($invoiceId) as $item) {
            if ($item['item_type'] !== 'GOODS') {
                continue;
            }

            $s         = $shipped[(int) $item['id']] ?? ['shipped' => '0', 'delivered' => '0'];
            $remaining = bcsub((string) $item['quantity'], $s['shipped'], 3);

            $rows[] = $item + [
                'shipped'   => $s['shipped'],
                'delivered' => $s['delivered'],
                'remaining' => bccomp($remaining, '0', 3) > 0 ? $remaining : '0.000',
            ];
        }

        return $rows;
    }

    /**
     * Default shipping address of a customer: default SHIPPING, else default BILLING, else any address.
     *
     * @param int $customerId Customer id.
     *
     * @return string Empty when the customer has no address.
     */
    public function defaultAddress(int $customerId): string
    {
        $addresses = (new CustomerAddressModel())->forCustomer($customerId); // ordered by type, default first
        $shipping  = array_values(array_filter($addresses, static fn (array $a): bool => $a['address_type'] === 'SHIPPING'));
        $billing   = array_values(array_filter($addresses, static fn (array $a): bool => $a['address_type'] === 'BILLING'));
        $address   = $shipping[0] ?? $billing[0] ?? null;

        return $address !== null ? CustomerAddressModel::format($address) : '';
    }

    /**
     * Create a delivery note.
     *
     * @param int                                     $invoiceId Invoice id.
     * @param array<string, mixed>                    $header    FIELDS.
     * @param array<int|string, array<string, mixed>> $lines     Keyed by invoice_item_id: [quantity].
     * @param int                                     $userId    Creating user.
     *
     * @return int New delivery note id.
     *
     * @throws ValidationFailed Field errors (FIELDS, "lines.<invoice_item_id>", lines).
     * @throws RuntimeException When the invoice cannot be delivered.
     */
    public function create(int $invoiceId, array $header, array $lines, int $userId): int
    {
        $header = $this->clean($header);

        return Transaction::run(function (BaseConnection $db) use ($invoiceId, $header, $lines, $userId): int {
            $invoice = (new InvoiceService())->lockInvoice($db, $invoiceId);

            if (! in_array($invoice['status_code'], self::DELIVERABLE, true)) {
                throw new RuntimeException(lang('Messages.deliveryOnlyIssued', [InvoiceWorkflow::label($invoice['status_code'])]));
            }

            $errors = $this->headerErrors($header);
            $rows   = [];

            foreach ($this->deliverableLines($invoiceId) as $n => $item) {
                $qty = trim((string) ($lines[$item['id']]['quantity'] ?? ''));

                if ($qty === '' || (DocumentLines::isDecimal($qty, 3) && bccomp($qty, '0', 3) === 0)) {
                    continue;
                }

                $error = match (true) {
                    ! DocumentLines::isDecimal($qty, 3)                => lang('Messages.lineQtyNumber'),
                    bccomp($qty, (string) $item['remaining'], 3) > 0   => lang('Messages.leftToShip', [format_qty($item['remaining']), $item['unit_code']]),
                    default                                            => null,
                };

                if ($error !== null) {
                    $errors['lines.' . $item['id']] = lang('Messages.lineNamed', [$n + 1, up($item['item_title']), $error]);

                    continue;
                }

                $rows[] = ['invoice_item_id' => (int) $item['id'], 'quantity' => $qty];
            }

            if ($errors === [] && $rows === []) {
                $errors['lines'] = lang('Messages.enterShipQty');
            }

            if ($errors !== []) {
                throw new ValidationFailed($errors);
            }

            $id = $this->notes->insert($header + [
                'delivery_number' => (new DocumentSequence())->next('DO'),
                'handover_number' => (new DocumentSequence())->next('BAST'),
                'invoice_id'      => $invoiceId,
                'created_by'      => $userId,
            ]);

            if ($id === false) {
                throw new ValidationFailed($this->notes->errors());
            }

            foreach ($rows as $row) {
                $this->items->insert($row + ['delivery_note_id' => (int) $id]);
            }

            $this->logEvent((int) $id, 'CREATED', date('Y-m-d H:i:s'), $userId, null, $header['note']);

            return (int) $id;
        });
    }

    /**
     * Goods left: set shipped_at (status SHIPPED), optionally the courier / tracking number.
     *
     * @param int                  $id     Delivery note id.
     * @param array<string, mixed> $data   shipped_date (Y-m-d), courier_name, tracking_number, location, note.
     * @param int                  $userId Current user.
     *
     * @return void
     *
     * @throws ValidationFailed
     * @throws RuntimeException When the note is not PREPARING.
     */
    public function markShipped(int $id, array $data, int $userId): void
    {
        $data = array_map(static fn ($v) => trim((string) $v), $data + ['shipped_date' => '', 'courier_name' => '', 'tracking_number' => '', 'location' => '', 'note' => '']);

        Transaction::run(function (BaseConnection $db) use ($id, $data, $userId): void {
            $note = $this->lockNote($db, $id);

            if (DeliveryNoteModel::status($note) !== DeliveryNoteModel::PREPARING) {
                throw new RuntimeException(lang('Messages.shipOnlyPreparing'));
            }

            $errors  = self::dateErrors($data['shipped_date'], null, 'shipped_date', lang('Messages.whatShipDate'));
            $courier = $data['courier_name'] !== '' ? $data['courier_name'] : (string) $note['courier_name'];
            $code    = (new ShipmentModel())->find((int) $note['shipment_id'])['code'] ?? '';

            if ($code === 'THIRD_PARTY' && $courier === '') {
                $errors['courier_name'] = lang('Messages.enterCourier');
            }

            foreach (['courier_name' => 64, 'tracking_number' => 64, 'location' => 128] as $field => $max) {
                if (mb_strlen($data[$field]) > $max) {
                    $errors[$field] = lang('Messages.tooLong', [$max]);
                }
            }

            if ($errors !== []) {
                throw new ValidationFailed($errors);
            }

            $at     = self::eventTime($data['shipped_date']);
            $update = ['shipped_at' => $at, 'ship_date' => $data['shipped_date'], 'courier_name' => $courier];

            if ($data['tracking_number'] !== '') {
                $update['tracking_number'] = $data['tracking_number'];
            }

            $this->notes->update($id, $update);

            $tracking = $data['tracking_number'] !== '' ? $data['tracking_number'] : (string) $note['tracking_number'];
            $detail   = trim(implode(' · ', array_filter([$courier, $tracking !== '' ? 'tracking ' . $tracking : null, $data['note']])));
            $this->logEvent($id, 'SHIPPED', $at, $userId, $data['location'], $detail);
        });
    }

    /**
     * Add a tracking update to an open delivery note (e.g. "arrived at the courier hub in Makassar").
     *
     * @param int                  $id     Delivery note id.
     * @param array<string, mixed> $data   event_date (Y-m-d), location, note (location or note required).
     * @param int                  $userId Current user.
     *
     * @return void
     *
     * @throws ValidationFailed
     * @throws RuntimeException When the note is not open.
     */
    public function addUpdate(int $id, array $data, int $userId): void
    {
        $data = array_map(static fn ($v) => trim((string) $v), $data + ['event_date' => '', 'location' => '', 'note' => '']);

        Transaction::run(function (BaseConnection $db) use ($id, $data, $userId): void {
            $note = $this->lockNote($db, $id);
            $this->assertOpen($note);

            $errors = self::dateErrors($data['event_date'], $note['shipped_at'] !== null ? $note['ship_date'] : null, 'event_date', lang('Messages.whatDate'));

            if ($data['location'] === '' && $data['note'] === '') {
                $errors['note'] = lang('Messages.enterLocationOrNote');
            } elseif (mb_strlen($data['location']) > 128 || mb_strlen($data['note']) > 2000) {
                $errors['note'] = lang('Messages.locationNoteLong');
            }

            if ($errors !== []) {
                throw new ValidationFailed($errors);
            }

            $this->logEvent($id, 'UPDATE', self::eventTime($data['event_date']), $userId, $data['location'], $data['note']);
        });
    }

    /**
     * Update the header of an open delivery note (lines are fixed; cancel and create a new note to change them).
     *
     * @param int                  $id     Delivery note id.
     * @param array<string, mixed> $header FIELDS.
     *
     * @return void
     *
     * @throws ValidationFailed
     * @throws RuntimeException When the note is not open.
     */
    public function update(int $id, array $header): void
    {
        $header = $this->clean($header);
        $errors = $this->headerErrors($header);

        if ($errors !== []) {
            throw new ValidationFailed($errors);
        }

        Transaction::run(function (BaseConnection $db) use ($id, $header): void {
            $this->assertOpen($this->lockNote($db, $id));

            if (! $this->notes->update($id, $header)) {
                throw new ValidationFailed($this->notes->errors());
            }
        });
    }

    /**
     * Confirm delivery: who received the goods and when.
     *
     * Works from PREPARING too (e.g. picked up at the store); shipped_at is then set to the delivery time.
     *
     * @param int      $id         Delivery note id.
     * @param string   $date       Delivery date (Y-m-d), not in the future, not before the ship date.
     * @param string   $receivedBy Receiver name (required).
     * @param int|null $userId     Current user.
     *
     * @return void
     *
     * @throws ValidationFailed
     * @throws RuntimeException When the note is not open.
     */
    public function markDelivered(int $id, string $date, string $receivedBy, ?int $userId = null): void
    {
        $date       = trim($date);
        $receivedBy = trim($receivedBy);

        Transaction::run(function (BaseConnection $db) use ($id, $date, $receivedBy, $userId): void {
            $note = $this->lockNote($db, $id);
            $this->assertOpen($note);

            // While preparing, ship_date is only planned: delivery (e.g. pickup) may be earlier.
            $errors = self::dateErrors($date, $note['shipped_at'] !== null ? $note['ship_date'] : null, 'delivered_date', lang('Messages.whatDeliveryDate'));

            if ($receivedBy === '' || mb_strlen($receivedBy) > 128) {
                $errors['received_by_name'] = lang('Messages.enterReceiver');
            }

            if ($errors !== []) {
                throw new ValidationFailed($errors);
            }

            $at = self::eventTime($date);
            $this->notes->update($id, ['delivered_at' => $at, 'received_by_name' => $receivedBy, 'shipped_at' => $note['shipped_at'] ?? $at]);
            $this->logEvent($id, 'DELIVERED', $at, $userId, null, 'Received by ' . $receivedBy);
        });
    }

    /**
     * Cancel an open delivery note (goods not shipped / returned before delivery). The record and number are kept.
     *
     * @param int    $id     Delivery note id.
     * @param string $reason Why (required).
     * @param int    $userId Current user.
     *
     * @return void
     *
     * @throws ValidationFailed
     * @throws RuntimeException When the note is not open.
     */
    public function cancel(int $id, string $reason, int $userId): void
    {
        $reason = trim($reason);

        if ($reason === '' || mb_strlen($reason) > 2000) {
            throw new ValidationFailed(['cancel_reason' => lang('Messages.reasonRequired2000')]);
        }

        Transaction::run(function (BaseConnection $db) use ($id, $reason, $userId): void {
            $this->assertOpen($this->lockNote($db, $id));
            $now = date('Y-m-d H:i:s');
            $this->notes->update($id, ['cancelled_at' => $now, 'cancelled_by' => $userId, 'cancel_reason' => $reason]);
            $this->logEvent($id, 'CANCELLED', $now, $userId, null, $reason);
        });
    }

    /**
     * Header checks (model rules + courier for third-party shipments).
     *
     * @param array<string, string> $header Clean header.
     *
     * @return array<string, string>
     */
    private function headerErrors(array $header): array
    {
        $errors = $this->notes->validate($header) ? [] : $this->notes->errors();

        if (! isset($errors['shipment_id']) && $header['courier_name'] === '') {
            $shipment = (new ShipmentModel())->find((int) $header['shipment_id']);

            if (($shipment['code'] ?? '') === 'THIRD_PARTY') {
                $errors['courier_name'] = lang('Messages.enterCourier');
            }
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $header Raw values.
     *
     * @return array<string, string> Only FIELDS, trimmed.
     */
    private function clean(array $header): array
    {
        $clean = [];

        foreach (self::FIELDS as $field) {
            $clean[$field] = trim((string) ($header[$field] ?? ''));
        }

        return $clean;
    }

    /**
     * Lock the delivery note row.
     *
     * @param BaseConnection $db Connection in the running transaction.
     * @param int            $id Delivery note id.
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException When not found.
     */
    private function lockNote(BaseConnection $db, int $id): array
    {
        return $db->query('SELECT * FROM delivery_note WHERE id = ? AND deleted_at IS NULL FOR UPDATE', [$id])->getRowArray()
            ?? throw new RuntimeException(lang('Messages.notFoundDelivery'));
    }

    /**
     * @param array<string, mixed> $note Delivery note row.
     *
     * @return void
     *
     * @throws RuntimeException When the note was delivered or cancelled.
     */
    private function assertOpen(array $note): void
    {
        if (! DeliveryNoteModel::isOpen($note)) {
            throw new RuntimeException(lang('Deliveries.locked', [mb_strtolower(code_label('deliveryStatus', DeliveryNoteModel::status($note)))]));
        }
    }

    /**
     * Append a tracking event.
     *
     * @param int         $noteId   Delivery note id.
     * @param string      $type     DeliveryEventModel::TYPES key.
     * @param string      $at       Y-m-d H:i:s.
     * @param int|null    $userId   User.
     * @param string|null $location Where (optional).
     * @param string|null $note     Remark (optional).
     *
     * @return void
     */
    private function logEvent(int $noteId, string $type, string $at, ?int $userId, ?string $location = null, ?string $note = null): void
    {
        (new DeliveryEventModel())->insert([
            'delivery_note_id' => $noteId,
            'event_type'       => $type,
            'event_at'         => $at,
            'location'         => $location === '' ? null : $location,
            'note'             => $note === '' ? null : $note,
            'created_by'       => $userId,
        ]);
    }

    /**
     * Validate a date (Y-m-d) for an event: not in the future, not before $notBefore.
     *
     * @param string      $date      Input.
     * @param string|null $notBefore Earliest allowed date (Y-m-d) or null.
     * @param string      $field     Error key.
     * @param string      $what      Label for messages.
     *
     * @return array<string, string> Errors.
     */
    private static function dateErrors(string $date, ?string $notBefore, string $field, string $what): array
    {
        $valid = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        return match (true) {
            $valid === false || $valid->format('Y-m-d') !== $date => [$field => lang('Messages.enterDate', [$what])],
            $date > date('Y-m-d')                                  => [$field => ucfirst(lang('Messages.dateNotFuture', [$what]))],
            $notBefore !== null && $date < $notBefore              => [$field => ucfirst(lang('Messages.dateBefore', [$what, format_date($notBefore)]))],
            default                                                => [],
        };
    }

    /**
     * Event time for a date: now when it is today, else noon of that day.
     *
     * @param string $date Y-m-d.
     *
     * @return string Y-m-d H:i:s
     */
    private static function eventTime(string $date): string
    {
        return $date === date('Y-m-d') ? date('Y-m-d H:i:s') : "{$date} 12:00:00";
    }
}
