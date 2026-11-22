<?php

declare(strict_types=1);

namespace App\Models;

/**
 * delivery_note_event: append-only tracking timeline of a delivery note (written by App\Services\DeliveryService).
 * It is itself a log, so no audit_log rows are written for it.
 */
class DeliveryEventModel extends BaseModel
{
    /**
     * Labels per event type.
     *
     * @var array<string, string>
     */
    public const TYPES = [
        'CREATED'   => 'Delivery note created',
        'SHIPPED'   => 'Shipped',
        'UPDATE'    => 'Tracking update',
        'DELIVERED' => 'Delivered',
        'CANCELLED' => 'Cancelled',
    ];

    protected $table          = 'delivery_note_event';
    protected array $lowercase = ['location']; // stored lower case, shown with up()
    protected $allowedFields  = ['delivery_note_id', 'event_type', 'event_at', 'location', 'note', 'created_by'];
    protected $useSoftDeletes = false;
    protected $updatedField   = '';
    protected bool $audited   = false;
    protected array $nullable = ['location', 'note', 'created_by'];

    /**
     * Timeline of a delivery note, newest first, with the user name.
     *
     * @param int $noteId Delivery note id.
     *
     * @return list<array<string, mixed>>
     */
    public function forNote(int $noteId): array
    {
        return $this->select('delivery_note_event.*, users.username, users.full_name')
            ->join('users', 'users.id = delivery_note_event.created_by', 'left')
            ->where('delivery_note_id', $noteId)
            ->orderBy('event_at', 'DESC')
            ->orderBy('delivery_note_event.id', 'DESC')
            ->findAll();
    }
}
