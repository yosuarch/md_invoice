<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ContactPersonModel;
use App\Models\CustomerContactModel;
use RuntimeException;

/**
 * Shared contacts: a person (contact_person) is stored once and linked to schools (customer_contact_person) with a
 * role, job title, primary flag and the date they left (ended_on).
 *
 * Rules:
 * - a person is linked at most once to the same school (current or former link);
 * - each school has exactly one primary contact among the people still there (the first one is primary automatically;
 *   when the primary leaves or is removed, the next current contact becomes primary);
 * - a link used on a proposal is never removed: mark the person as having left instead (documents keep the link).
 */
final class ContactService
{
    /** Person fields accepted from forms. */
    public const PERSON_FIELDS = ['cp_first_name', 'cp_last_name', 'cp_phone', 'cp_whatsapp', 'cp_email', 'cp_nip', 'cp_note'];

    /** Link fields accepted from forms. */
    public const LINK_FIELDS = ['role_id', 'ccp_position', 'is_primary', 'ended_on'];

    public function __construct(
        private readonly ContactPersonModel $people = new ContactPersonModel(),
        private readonly CustomerContactModel $links = new CustomerContactModel(),
    ) {
    }

    /**
     * Link a person to a school: an existing person ($contactId) or a new one created from $person.
     *
     * @param int                  $customerId School id.
     * @param int|null             $contactId  Existing person, or null to create one from $person.
     * @param array<string, mixed> $person     Person fields (ignored when $contactId is given).
     * @param array<string, mixed> $link       Link fields (role_id, ccp_position, is_primary, ended_on).
     *
     * @return int Link id.
     *
     * @throws ValidationFailed Field errors (person fields keep their names, link errors too).
     */
    public function addToCustomer(int $customerId, ?int $contactId, array $person, array $link): int
    {
        return Transaction::run(function () use ($customerId, $contactId, $person, $link): int {
            if ($contactId !== null) {
                if ($this->people->find($contactId) === null) {
                    throw new ValidationFailed(['contact_id' => lang('Contacts.personNotFound')]);
                }

                if ($this->links->where(['customer_id' => $customerId, 'contact_id' => $contactId])->countAllResults() > 0) {
                    throw new ValidationFailed(['contact_id' => lang('Contacts.alreadyLinked')]);
                }
            } else {
                $contactId = $this->savePerson(null, $person);
            }

            $data = $this->linkData($link) + ['customer_id' => $customerId, 'contact_id' => $contactId];
            $id   = $this->links->insert($data);

            if ($id === false) {
                throw new ValidationFailed($this->links->errors());
            }

            $this->fixPrimary($customerId, (int) $id, $data);

            return (int) $id;
        });
    }

    /**
     * Update a school link and (optionally) the linked person.
     *
     * @param int                       $customerId School id.
     * @param int                       $linkId     Link id.
     * @param array<string, mixed>      $link       Link fields.
     * @param array<string, mixed>|null $person     Person fields, null = leave the person unchanged.
     *
     * @return void
     *
     * @throws ValidationFailed
     */
    public function updateLink(int $customerId, int $linkId, array $link, ?array $person): void
    {
        Transaction::run(function () use ($customerId, $linkId, $link, $person): void {
            $current = $this->links->where('customer_id', $customerId)->find($linkId) ?? throw new RuntimeException(lang('Contacts.notFound'));

            if ($person !== null) {
                $this->savePerson((int) $current['contact_id'], $person);
            }

            $data = $this->linkData($link) + ['id' => $linkId, 'customer_id' => $customerId, 'contact_id' => (int) $current['contact_id']];

            if (! $this->links->update($linkId, $data)) {
                throw new ValidationFailed($this->links->errors());
            }

            $this->fixPrimary($customerId, $linkId, $data);
        });
    }

    /**
     * Create or update a person.
     *
     * @param int|null             $contactId Null to create.
     * @param array<string, mixed> $data      Person fields.
     *
     * @return int Person id.
     *
     * @throws ValidationFailed
     */
    public function savePerson(?int $contactId, array $data): int
    {
        $data = array_intersect_key($data, array_flip([...self::PERSON_FIELDS, 'is_active']));
        $data['cp_whatsapp'] = empty($data['cp_whatsapp']) ? 0 : 1;

        if ($contactId === null) {
            $data += ['is_active' => 1];
            $id    = $this->people->insert($data);

            if ($id === false) {
                throw new ValidationFailed($this->people->errors());
            }

            return (int) $id;
        }

        if (! $this->people->update($contactId, $data + ['id' => $contactId])) {
            throw new ValidationFailed($this->people->errors());
        }

        return $contactId;
    }

    /**
     * Remove a person from a school (soft delete). A link used on a proposal is kept: mark the person as having
     * left instead.
     *
     * @param int $customerId School id.
     * @param int $linkId     Link id.
     *
     * @return void
     *
     * @throws RuntimeException When proposals use the link.
     */
    public function removeLink(int $customerId, int $linkId): void
    {
        if ($this->links->isInUse($linkId)) {
            throw new RuntimeException(lang('Contacts.linkInUse'));
        }

        Transaction::run(function () use ($customerId, $linkId): void {
            $link = $this->links->where('customer_id', $customerId)->find($linkId);

            if ($link === null) {
                return;
            }

            $this->links->delete($linkId);

            if ((int) $link['is_primary'] === 1) {
                $this->promoteNext($customerId);
            }
        });
    }

    /**
     * Link fields from form input: empty values to NULL, primary only for people still at the school.
     *
     * @param array<string, mixed> $link Raw link fields.
     *
     * @return array<string, mixed>
     */
    private function linkData(array $link): array
    {
        $data = array_intersect_key($link, array_flip(self::LINK_FIELDS));
        $data['is_primary'] = empty($data['is_primary']) ? 0 : 1;

        if (! empty($data['ended_on'])) {
            $data['is_primary'] = 0;
        }

        return $data;
    }

    /**
     * Keep exactly one primary among the current contacts of a school.
     *
     * @param int                  $customerId School id.
     * @param int                  $linkId     Link just saved.
     * @param array<string, mixed> $data       Its data.
     *
     * @return void
     */
    private function fixPrimary(int $customerId, int $linkId, array $data): void
    {
        $current = empty($data['ended_on']);

        if ($current && $data['is_primary'] === 1) {
            foreach ($this->links->where(['customer_id' => $customerId, 'is_primary' => 1])->where('id !=', $linkId)->findColumn('id') ?? [] as $other) {
                $this->links->update((int) $other, ['is_primary' => 0]);
            }

            return;
        }

        $hasPrimary = $this->links->where(['customer_id' => $customerId, 'is_primary' => 1])->where('ended_on', null)->countAllResults() > 0;

        if (! $hasPrimary) {
            $this->promoteNext($customerId);
        }
    }

    /**
     * Make the oldest current contact of a school primary (when none is).
     *
     * @param int $customerId School id.
     *
     * @return void
     */
    private function promoteNext(int $customerId): void
    {
        $next = $this->links->where('customer_id', $customerId)->where('ended_on', null)->orderBy('id')->first();

        if ($next !== null) {
            $this->links->update((int) $next['id'], ['is_primary' => 1]);
        }
    }
}
