<?php

declare(strict_types=1);

namespace App\Models;

/**
 * contact_person: a person, stored once and linked to one or more schools through customer_contact_person
 * (role, position, primary flag, end date per school).
 */
class ContactPersonModel extends BaseModel
{
    protected $table           = 'contact_person';
    protected array $lowercase = ['cp_first_name', 'cp_last_name']; // stored lower case, shown with up()
    protected $allowedFields   = ['cp_first_name', 'cp_last_name', 'cp_phone', 'cp_whatsapp', 'cp_email', 'cp_nip', 'cp_note', 'is_active'];
    protected array $nullable  = ['cp_last_name', 'cp_phone', 'cp_email', 'cp_nip', 'cp_note'];

    protected array $referencedBy = ['customer_contact_person' => 'contact_id', 'crm_activity' => 'contact_id'];

    protected $validationRules = [
        'cp_first_name' => 'required|max_length[64]',
        'cp_last_name'  => 'permit_empty|max_length[64]',
        'cp_phone'      => 'required|max_length[32]|phone',
        'cp_whatsapp'   => 'permit_empty|in_list[0,1]',
        'cp_email'      => 'permit_empty|max_length[255]|valid_email',
        'cp_nip'        => 'permit_empty|max_length[32]|regex_match[/^[0-9 ]+$/]',
        'cp_note'       => 'permit_empty|max_length[2000]',
        'is_active'     => 'permit_empty|in_list[0,1]',
    ];

    /**
     * Full display name (upper case).
     *
     * @param array<string, mixed> $person Row with cp_first_name / cp_last_name (or ccp_ aliases).
     *
     * @return string
     */
    public static function displayName(array $person): string
    {
        return up(trim(($person['cp_first_name'] ?? $person['ccp_first_name'] ?? '') . ' ' . ($person['cp_last_name'] ?? $person['ccp_last_name'] ?? '')));
    }

    /**
     * wa.me link for a phone number (Indonesian numbers: leading 0 → 62), or null.
     *
     * @param string|null $phone Phone as typed.
     *
     * @return string|null
     */
    public static function whatsappUrl(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        if ($digits === '' || strlen($digits) < 8) {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            $digits = '62' . substr($digits, 1);
        }

        return 'https://wa.me/' . $digits;
    }

    /**
     * People list with search; each row carries the schools as "name (role)" text.
     *
     * @param string $q Search in name, phone, email, NIP and school name.
     *
     * @return $this
     */
    public function search(string $q): self
    {
        $this->select("contact_person.*, GROUP_CONCAT(DISTINCT CONCAT(c.id, '|', c.cl_organization_name, '|', COALESCE(r.code, ''), '|', IF(l.ended_on IS NULL, 1, 0)) ORDER BY l.ended_on IS NULL DESC, c.cl_organization_name SEPARATOR '§') AS schools")
            ->join('customer_contact_person l', 'l.contact_id = contact_person.id AND l.deleted_at IS NULL', 'left')
            ->join('customer_list c', 'c.id = l.customer_id AND c.deleted_at IS NULL', 'left')
            ->join('contact_role r', 'r.id = l.role_id', 'left')
            ->groupBy('contact_person.id')
            ->orderBy('contact_person.cp_first_name');

        if ($q !== '') {
            $this->groupStart()
                ->like('contact_person.cp_first_name', $q)
                ->orLike('contact_person.cp_last_name', $q)
                ->orLike('contact_person.cp_phone', $q)
                ->orLike('contact_person.cp_email', $q)
                ->orLike('contact_person.cp_nip', $q)
                ->orLike('c.cl_organization_name', $q)
                ->groupEnd();
        }

        return $this;
    }

    /**
     * Parse the "schools" column of search() into rows.
     *
     * @param string|null $schools GROUP_CONCAT value.
     *
     * @return list<array{id: int, name: string, role: string, current: bool}>
     */
    public static function parseSchools(?string $schools): array
    {
        if ($schools === null || $schools === '') {
            return [];
        }

        return array_map(static function (string $s): array {
            [$id, $name, $role, $current] = array_pad(explode('|', $s, 4), 4, '');

            return ['id' => (int) $id, 'name' => $name, 'role' => $role, 'current' => $current === '1'];
        }, explode('§', $schools));
    }
}
