<?php

declare(strict_types=1);

namespace App\Models;

/**
 * crm_activity: what happened with a school (call, visit, WhatsApp, email, meeting, note: occurred_at set) and
 * follow-ups to do (due_on set, done_at empty until completed, assigned_to = who does it). Optionally linked to a
 * person, a proposal and an invoice. Rules in App\Services\ActivityService.
 */
class ActivityModel extends BaseModel
{
    public const TYPES = ['CALL', 'VISIT', 'WHATSAPP', 'EMAIL', 'MEETING', 'NOTE'];

    /** Bootstrap icon per type. */
    public const ICONS = ['CALL' => 'telephone', 'VISIT' => 'geo-alt', 'WHATSAPP' => 'whatsapp', 'EMAIL' => 'envelope', 'MEETING' => 'people', 'NOTE' => 'sticky'];

    protected $table          = 'crm_activity';
    protected $allowedFields  = [
        'customer_id', 'contact_id', 'proposal_id', 'invoice_id', 'activity_type', 'subject', 'notes',
        'occurred_at', 'due_on', 'done_at', 'outcome', 'assigned_to', 'created_by',
    ];
    protected array $nullable = ['contact_id', 'proposal_id', 'invoice_id', 'notes', 'occurred_at', 'due_on', 'done_at', 'outcome', 'assigned_to'];

    protected $validationRules = [
        'customer_id'   => 'required|is_natural_no_zero|is_not_unique[customer_list.id]',
        'contact_id'    => 'permit_empty|is_natural_no_zero|is_not_unique[contact_person.id]',
        'proposal_id'   => 'permit_empty|is_natural_no_zero',
        'invoice_id'    => 'permit_empty|is_natural_no_zero',
        'activity_type' => 'required|in_list[CALL,VISIT,WHATSAPP,EMAIL,MEETING,NOTE]',
        'subject'       => 'required|max_length[200]',
        'notes'         => 'permit_empty|max_length[5000]',
        'occurred_at'   => 'permit_empty|valid_date[Y-m-d H:i:s]',
        'due_on'        => 'permit_empty|valid_date[Y-m-d]',
        'outcome'       => 'permit_empty|max_length[5000]',
        'assigned_to'   => 'permit_empty|is_natural_no_zero',
    ];

    /**
     * Query with school, person, documents and user names.
     *
     * @return $this
     */
    public function withDetails(): self
    {
        return $this->select('crm_activity.*, c.cl_organization_name AS customer_name, p.cp_first_name, p.cp_last_name, p.cp_phone,'
                . ' pr.cpl_id AS proposal_number, i.invoice_number, au.username AS assignee_username, au.full_name AS assignee_name,'
                . ' cu.username AS creator_username, cu.full_name AS creator_name')
            ->join('customer_list c', 'c.id = crm_activity.customer_id')
            ->join('contact_person p', 'p.id = crm_activity.contact_id', 'left')
            ->join('client_proposal_list pr', 'pr.id = crm_activity.proposal_id', 'left')
            ->join('invoice i', 'i.id = crm_activity.invoice_id', 'left')
            ->join('users au', 'au.id = crm_activity.assigned_to', 'left')
            ->join('users cu', 'cu.id = crm_activity.created_by', 'left');
    }

    /**
     * Timeline order: open follow-ups first (by due date), then the rest newest first.
     *
     * @return $this
     */
    public function timelineOrder(): self
    {
        return $this->orderBy('(crm_activity.done_at IS NULL AND crm_activity.due_on IS NOT NULL)', 'DESC', false)
            ->orderBy('crm_activity.due_on', 'ASC')
            ->orderBy('COALESCE(crm_activity.done_at, crm_activity.occurred_at, crm_activity.created_at)', 'DESC', false);
    }

    /**
     * Whether the row is an open follow-up.
     *
     * @param array<string, mixed> $a Activity row.
     *
     * @return bool
     */
    public static function isOpen(array $a): bool
    {
        return $a['due_on'] !== null && $a['done_at'] === null;
    }
}
