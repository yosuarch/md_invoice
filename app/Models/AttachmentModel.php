<?php

declare(strict_types=1);

namespace App\Models;

/**
 * attachment: files added to a proposal, invoice, payment or delivery note (signed PO, transfer proof, photo of the
 * handover, bukti potong scan, ...). Files live under writable/uploads/ (file_path is relative to it) and are served
 * by the Attachments controller after an access check. Removing an attachment is a soft delete; the file stays.
 */
class AttachmentModel extends BaseModel
{
    protected $table         = 'attachment';
    protected $allowedFields = ['entity_type', 'entity_id', 'file_name', 'file_path', 'mime_type', 'uploaded_by'];

    protected $validationRules = [
        'entity_type' => 'required|in_list[proposal,invoice,payment,delivery_note]',
        'entity_id'   => 'required|is_natural_no_zero',
        'file_name'   => 'required|max_length[255]',
        'file_path'   => 'required|max_length[255]',
        'mime_type'   => 'required|max_length[100]',
    ];

    /**
     * Attachments of several records, newest first, with the uploader's name.
     *
     * @param array<string, list<int>> $entities entity_type => ids.
     *
     * @return list<array<string, mixed>>
     */
    public function forEntities(array $entities): array
    {
        $entities = array_filter($entities);

        if ($entities === []) {
            return [];
        }

        $this->select('attachment.*, users.username AS uploader_username, users.full_name AS uploader_name')
            ->join('users', 'users.id = attachment.uploaded_by', 'left')
            ->groupStart();

        foreach ($entities as $type => $ids) {
            $this->orGroupStart()
                ->where('attachment.entity_type', $type)
                ->whereIn('attachment.entity_id', array_map('intval', $ids))
                ->groupEnd();
        }

        return $this->groupEnd()->orderBy('attachment.id', 'DESC')->findAll();
    }
}
