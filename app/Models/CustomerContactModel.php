<?php

declare(strict_types=1);

namespace App\Models;

/**
 * customer_contact_person: link between a person (contact_person) and a school, with the person's role, job title
 * (position), primary flag and the date they left (ended_on). One primary current contact per school (enforced by
 * App\Services\ContactService). Proposals reference this link row, so old documents keep the person and role of that
 * time even after the person left the school.
 *
 * Reads through withPerson() also return the person's fields under the historic ccp_* names (ccp_first_name,
 * ccp_last_name, ccp_phone_number, ccp_email) used by views and PDFs.
 */
class CustomerContactModel extends BaseModel
{
    protected $table           = 'customer_contact_person';
    protected array $lowercase = ['ccp_position']; // stored lower case, shown with up()
    protected $allowedFields   = ['customer_id', 'contact_id', 'role_id', 'ccp_position', 'is_primary', 'ended_on'];
    protected array $nullable  = ['role_id', 'ccp_position', 'ended_on'];

    protected array $referencedBy = ['client_proposal_list' => 'contact_person_id'];

    protected $validationRules = [
        'customer_id'  => 'required|is_natural_no_zero|is_not_unique[customer_list.id]',
        'contact_id'   => 'required|is_natural_no_zero|is_not_unique[contact_person.id]',
        'role_id'      => 'permit_empty|is_natural_no_zero|is_not_unique[contact_role.id]',
        'ccp_position' => 'permit_empty|max_length[64]',
        'is_primary'   => 'permit_empty|in_list[0,1]',
        'ended_on'     => 'permit_empty|valid_date[Y-m-d]',
    ];

    /**
     * Select the link with the person's fields (ccp_* aliases) and the role.
     *
     * @return $this
     */
    public function withPerson(): self
    {
        return $this->select('customer_contact_person.*, p.cp_first_name AS ccp_first_name, p.cp_last_name AS ccp_last_name,'
                . ' p.cp_phone AS ccp_phone_number, p.cp_email AS ccp_email, p.cp_whatsapp, p.cp_nip, p.cp_note, p.is_active AS person_active,'
                . ' r.code AS role_code, r.name AS role_name')
            ->join('contact_person p', 'p.id = customer_contact_person.contact_id')
            ->join('contact_role r', 'r.id = customer_contact_person.role_id', 'left');
    }

    /**
     * Contacts of a school: current ones first (primary first), then the ones who left.
     *
     * @param int  $customerId  Customer id.
     * @param bool $currentOnly Only people still at the school.
     *
     * @return list<array<string, mixed>>
     */
    public function forCustomer(int $customerId, bool $currentOnly = false): array
    {
        $this->withPerson()->where('customer_contact_person.customer_id', $customerId);

        if ($currentOnly) {
            $this->where('customer_contact_person.ended_on', null);
        }

        return $this->orderBy('customer_contact_person.ended_on IS NULL', 'DESC', false)
            ->orderBy('customer_contact_person.is_primary', 'DESC')
            ->orderBy('p.cp_first_name')
            ->findAll();
    }

    /**
     * Schools of a person (links with school name and role).
     *
     * @param int $contactId Person id.
     *
     * @return list<array<string, mixed>>
     */
    public function forPerson(int $contactId): array
    {
        return $this->select('customer_contact_person.*, c.cl_organization_name, c.npsn, r.code AS role_code, r.name AS role_name')
            ->join('customer_list c', 'c.id = customer_contact_person.customer_id')
            ->join('contact_role r', 'r.id = customer_contact_person.role_id', 'left')
            ->where('customer_contact_person.contact_id', $contactId)
            ->orderBy('customer_contact_person.ended_on IS NULL', 'DESC', false)
            ->orderBy('c.cl_organization_name')
            ->findAll();
    }

    /**
     * One link with the person's fields (any status, also deleted: used by documents).
     *
     * @param int $id Link id.
     *
     * @return array<string, mixed>|null
     */
    public function findWithPerson(int $id): ?array
    {
        return $this->withDeleted()->withPerson()->where('customer_contact_person.id', $id)->first();
    }

    /**
     * Role / position label of a link: the job title, else the role in the screen language.
     *
     * @param array<string, mixed> $link   Link row (with role_code, role_name).
     * @param string|null          $locale Locale for the role name (PDFs: 'id'); default the screen language.
     *
     * @return string
     */
    public static function roleLabel(array $link, ?string $locale = null): string
    {
        if (! empty($link['ccp_position'])) {
            return up($link['ccp_position']);
        }

        return ! empty($link['role_code']) ? code_label('contactRole', $link['role_code'], $link['role_name'] ?? null, $locale) : '';
    }
}
