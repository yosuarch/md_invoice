<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ActivityModel;
use App\Models\CustomerContactModel;
use App\Models\InvoiceModel;
use App\Models\ProposalModel;
use RuntimeException;

/**
 * Lean CRM: activities with a school (what happened) and follow-ups (what to do next).
 *
 * - An activity has a type, subject and notes; "occurred" = it happened (occurred_at), "follow-up" = due_on set and
 *   not done yet. Logging can plan the next follow-up in the same step.
 * - The person must be linked to the school; a proposal / invoice must belong to the school.
 * - Visibility follows the school (AccessScope). Editing and deleting: the creator, the assignee or an admin
 *   (records.view_all with crm.manage); completing a follow-up: anyone who may see the school.
 */
final class ActivityService
{
    /** Fields accepted from forms. */
    public const FIELDS = ['customer_id', 'contact_id', 'proposal_id', 'invoice_id', 'activity_type', 'subject', 'notes', 'occurred_at', 'due_on', 'assigned_to'];

    public function __construct(private readonly ActivityModel $activities = new ActivityModel())
    {
    }

    /**
     * Save an activity (new or existing). With 'occurred_at' it is a logged activity; with 'due_on' and no
     * 'occurred_at' it is a follow-up. A logged activity may plan a follow-up at the same time ($next).
     *
     * @param int|null                  $id     Activity id (null = new).
     * @param array<string, mixed>      $data   Fields (see FIELDS); occurred_at as Y-m-d or Y-m-d\TH:i.
     * @param int                       $userId Current user.
     * @param array<string, mixed>|null $next   Optional follow-up: [due_on, subject, assigned_to, activity_type].
     *
     * @return int Activity id.
     *
     * @throws ValidationFailed
     */
    public function save(?int $id, array $data, int $userId, ?array $next = null): int
    {
        $data = $this->clean($data);

        return Transaction::run(function () use ($id, $data, $userId, $next): int {
            if ($id === null) {
                $data['created_by']  = $userId;
                $data['assigned_to'] = $data['assigned_to'] ?? $userId;

                if ($data['occurred_at'] !== null) {
                    $data['done_at'] = $data['occurred_at'];
                }

                $id = $this->activities->insert($data);

                if ($id === false) {
                    throw new ValidationFailed($this->activities->errors());
                }

                $id = (int) $id;
            } else {
                $existing = $this->activities->find($id) ?? throw new RuntimeException(lang('Activities.notFound'));

                // A logged activity (no follow-up date) is done when it happened.
                if ($data['due_on'] === null && $data['occurred_at'] !== null) {
                    $data['done_at'] = $data['occurred_at'];
                } elseif ($data['due_on'] !== null && $existing['due_on'] === null && $existing['done_at'] !== null) {
                    $data['done_at'] = null; // a logged activity turned into a follow-up is open again
                }

                if (! $this->activities->update($id, $data + ['id' => $id])) {
                    throw new ValidationFailed($this->activities->errors());
                }
            }

            if ($next !== null && trim((string) ($next['due_on'] ?? '')) !== '') {
                $this->save(null, [
                    'customer_id'   => $data['customer_id'],
                    'contact_id'    => $data['contact_id'],
                    'proposal_id'   => $data['proposal_id'],
                    'invoice_id'    => $data['invoice_id'],
                    'activity_type' => $next['activity_type'] ?? $data['activity_type'],
                    'subject'       => trim((string) ($next['subject'] ?? '')) !== '' ? $next['subject'] : $data['subject'],
                    'due_on'        => $next['due_on'],
                    'assigned_to'   => $next['assigned_to'] ?? $userId,
                ], $userId);
            }

            return $id;
        });
    }

    /**
     * Mark a follow-up done, with the outcome; optionally plan the next one.
     *
     * @param int                       $id      Activity id.
     * @param string                    $outcome What happened.
     * @param int                       $userId  Current user.
     * @param array<string, mixed>|null $next    Optional next follow-up (see save()).
     *
     * @return void
     *
     * @throws RuntimeException When it is not an open follow-up.
     */
    public function complete(int $id, string $outcome, int $userId, ?array $next = null): void
    {
        Transaction::run(function () use ($id, $outcome, $userId, $next): void {
            $activity = $this->activities->find($id) ?? throw new RuntimeException(lang('Activities.notFound'));

            if (! ActivityModel::isOpen($activity)) {
                throw new RuntimeException(lang('Activities.notOpen'));
            }

            if (mb_strlen($outcome) > 5000) {
                throw new ValidationFailed(['outcome' => lang('Messages.noteTooLong')]);
            }

            $now = date('Y-m-d H:i:s');
            $this->activities->update($id, ['done_at' => $now, 'occurred_at' => $activity['occurred_at'] ?? $now, 'outcome' => trim($outcome) !== '' ? trim($outcome) : null]);

            if ($next !== null && trim((string) ($next['due_on'] ?? '')) !== '') {
                $this->save(null, [
                    'customer_id'   => $activity['customer_id'],
                    'contact_id'    => $activity['contact_id'],
                    'proposal_id'   => $activity['proposal_id'],
                    'invoice_id'    => $activity['invoice_id'],
                    'activity_type' => $next['activity_type'] ?? $activity['activity_type'],
                    'subject'       => trim((string) ($next['subject'] ?? '')) !== '' ? $next['subject'] : $activity['subject'],
                    'due_on'        => $next['due_on'],
                    'assigned_to'   => $next['assigned_to'] ?? $activity['assigned_to'] ?? $userId,
                ], $userId);
            }
        });
    }

    /**
     * Whether the user may edit / delete an activity (creator, assignee or admin).
     *
     * @param array<string, mixed> $activity Activity row.
     * @param int                  $userId   Current user.
     *
     * @return bool
     */
    public static function canEdit(array $activity, int $userId): bool
    {
        return (int) $activity['created_by'] === $userId || (int) ($activity['assigned_to'] ?? 0) === $userId
            || (auth()->user()?->can('records.view_all') === true && auth()->user()?->inGroup('app_admin', 'admin') === true);
    }

    /**
     * Validate and normalise form input; checks that person / proposal / invoice belong to the school.
     *
     * @param array<string, mixed> $data Raw input.
     *
     * @return array<string, mixed>
     *
     * @throws ValidationFailed
     */
    private function clean(array $data): array
    {
        $data = array_intersect_key($data, array_flip(self::FIELDS));

        foreach (['contact_id', 'proposal_id', 'invoice_id', 'assigned_to'] as $key) {
            $data[$key] = (int) ($data[$key] ?? 0) > 0 ? (int) $data[$key] : null;
        }

        foreach (['subject', 'notes'] as $key) {
            $data[$key] = trim((string) ($data[$key] ?? ''));
        }

        $data['notes'] = $data['notes'] !== '' ? str_replace("\r\n", "\n", $data['notes']) : null;
        $occurred      = trim((string) ($data['occurred_at'] ?? ''));
        $due           = trim((string) ($data['due_on'] ?? ''));
        $errors        = [];

        $data['occurred_at'] = null;

        if ($occurred !== '') {
            $time = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $occurred) ?: \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $occurred) ?: \DateTimeImmutable::createFromFormat('!Y-m-d', $occurred);

            if ($time === false) {
                $errors['occurred_at'] = lang('Activities.invalidDate');
            } elseif ($time->format('Y-m-d H:i:s') > date('Y-m-d H:i:s', time() + 300)) {
                $errors['occurred_at'] = lang('Activities.occurredFuture');
            } else {
                $data['occurred_at'] = $time->format('Y-m-d H:i:s');
            }
        }

        $data['due_on'] = $due !== '' ? $due : null;

        if ($data['occurred_at'] === null && $data['due_on'] === null) {
            $errors['occurred_at'] = lang('Activities.whenRequired');
        }

        $customerId = (int) ($data['customer_id'] ?? 0);

        if ($data['contact_id'] !== null && (new CustomerContactModel())->where(['customer_id' => $customerId, 'contact_id' => $data['contact_id']])->countAllResults() === 0) {
            $errors['contact_id'] = lang('Activities.contactNotOfSchool');
        }

        if ($data['proposal_id'] !== null && (int) ((new ProposalModel())->find($data['proposal_id'])['customer_id'] ?? 0) !== $customerId) {
            $errors['proposal_id'] = lang('Activities.docNotOfSchool');
        }

        if ($data['invoice_id'] !== null && (int) ((new InvoiceModel())->find($data['invoice_id'])['customer_id'] ?? 0) !== $customerId) {
            $errors['invoice_id'] = lang('Activities.docNotOfSchool');
        }

        if ($errors !== []) {
            throw new ValidationFailed($errors);
        }

        return $data;
    }
}
