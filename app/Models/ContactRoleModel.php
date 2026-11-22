<?php

declare(strict_types=1);

namespace App\Models;

/**
 * contact_role: role of a contact person at a school (HEADMASTER, TREASURER, ...). Screen labels via
 * code_label('contactRole', code, name).
 */
class ContactRoleModel extends LookupModel
{
    protected $table              = 'contact_role';
    protected $allowedFields      = ['code', 'name', 'sort_order'];
    protected array $referencedBy = ['customer_contact_person' => 'role_id'];

    protected $validationRules = [
        'code'       => 'required|max_length[32]|regex_match[/^[A-Z][A-Z0-9_]*$/]|is_unique[contact_role.code,id,{id}]',
        'name'       => 'required|max_length[64]',
        'sort_order' => 'permit_empty|is_natural|less_than_equal_to[255]',
    ];

    protected $validationMessages = [
        'code' => [
            'regex_match' => 'Use UPPER_SNAKE_CASE (A-Z, 0-9, _), starting with a letter.',
            'is_unique'   => 'This code already exists.',
        ],
    ];

    /**
     * Options id => label in the screen language, in sort order.
     *
     * @param string $labelColumn Unused (kept for the parent signature).
     *
     * @return array<int, string>
     */
    public function options(string $labelColumn = 'name'): array
    {
        $options = [];

        foreach ($this->orderBy('sort_order')->findAll() as $row) {
            $options[(int) $row['id']] = code_label('contactRole', $row['code'], $row['name']);
        }

        return $options;
    }
}
