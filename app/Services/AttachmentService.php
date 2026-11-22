<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AttachmentModel;
use App\Models\DeliveryNoteModel;
use App\Models\PaymentModel;
use CodeIgniter\HTTP\Files\UploadedFile;
use Config\Documents;
use RuntimeException;

/**
 * Files attached to the documents of an order (proposal, invoice, payment, delivery note).
 *
 * Access follows the documents: whoever may see the proposal/invoice (AccessScope) may see and download its files.
 * Uploading needs the module's manage permission (TYPES); removing is allowed for the uploader and admins
 * (settings.manage). Removal is a soft delete and keeps the file, so the audit trail stays complete.
 */
final class AttachmentService
{
    /**
     * Entity types: label and the permission needed to add files.
     *
     * @var array<string, array{label: string, permission: string}>
     */
    public const TYPES = [
        'proposal'      => ['label' => 'Proposal', 'permission' => 'proposals.manage'],
        'invoice'       => ['label' => 'Invoice', 'permission' => 'invoices.manage'],
        'payment'       => ['label' => 'Payment', 'permission' => 'payments.record'],
        'delivery_note' => ['label' => 'Delivery note', 'permission' => 'deliveries.manage'],
    ];

    /** Directory under WRITEPATH . 'uploads/'. */
    private const DIR = 'attachments';

    private AttachmentModel $model;

    public function __construct(?AttachmentModel $model = null)
    {
        $this->model = $model ?? new AttachmentModel();
    }

    /**
     * Whether the user may see the record (and therefore its files).
     *
     * @param AccessScope $scope Access scope of the user.
     * @param string      $type  Entity type (TYPES key).
     * @param int         $id    Entity id.
     *
     * @return bool
     */
    public function canSee(AccessScope $scope, string $type, int $id): bool
    {
        return match ($type) {
            'proposal'      => $scope->canProposal($id),
            'invoice'       => $scope->canInvoice($id),
            'payment'       => $scope->canInvoice((int) ((new PaymentModel())->find($id)['invoice_id'] ?? 0)),
            'delivery_note' => $scope->canInvoice((int) ((new DeliveryNoteModel())->find($id)['invoice_id'] ?? 0)),
            default         => false,
        };
    }

    /**
     * Whether the current user may add files to records of this type.
     *
     * @param string $type Entity type.
     *
     * @return bool
     */
    public static function canUpload(string $type): bool
    {
        return isset(self::TYPES[$type]) && auth()->user()?->can(self::TYPES[$type]['permission']) === true;
    }

    /**
     * Whether the current user may remove the attachment (uploader or admin).
     *
     * @param array<string, mixed> $attachment Attachment row.
     *
     * @return bool
     */
    public static function canDelete(array $attachment): bool
    {
        $user = auth()->user();

        return $user !== null && ((int) $attachment['uploaded_by'] === (int) $user->id || $user->can('settings.manage'));
    }

    /**
     * Effective upload limit in bytes: the app setting capped by php.ini.
     *
     * @return int
     */
    public static function maxBytes(): int
    {
        $limits = [config(Documents::class)->attachmentMaxKb * 1024];

        foreach (['upload_max_filesize', 'post_max_size'] as $key) {
            $bytes = self::iniBytes((string) ini_get($key));

            if ($bytes > 0) {
                $limits[] = $bytes;
            }
        }

        return min($limits);
    }

    /**
     * Allowed extensions for the file input's accept attribute (".pdf,.jpg,...").
     *
     * @return string
     */
    public static function acceptList(): string
    {
        return implode(',', array_map(static fn (string $e): string => '.' . $e, array_keys(config(Documents::class)->attachmentTypes)));
    }

    /**
     * Validate and store an uploaded file for a record.
     *
     * @param string            $type     Entity type.
     * @param int               $entityId Entity id.
     * @param UploadedFile|null $file     The upload.
     * @param int               $userId   Uploader.
     *
     * @return int New attachment id.
     *
     * @throws ValidationFailed  Invalid file.
     * @throws RuntimeException  The file could not be stored.
     */
    public function store(string $type, int $entityId, ?UploadedFile $file, int $userId): int
    {
        if (! isset(self::TYPES[$type])) {
            throw new ValidationFailed(['entity' => lang('Messages.chooseDocument')]);
        }

        $ext  = $this->validateFile($file);
        $mime = (string) $file->getMimeType();
        $dir  = self::DIR . '/' . date('Y/m');
        $name = bin2hex(random_bytes(16)) . '.' . $ext;

        if (! is_dir(WRITEPATH . 'uploads/' . $dir) && ! mkdir(WRITEPATH . 'uploads/' . $dir, 0775, true) && ! is_dir(WRITEPATH . 'uploads/' . $dir)) {
            throw new RuntimeException(lang('Messages.uploadDirFailed'));
        }

        $file->move(WRITEPATH . 'uploads/' . $dir, $name);

        try {
            return (int) $this->model->insert([
                'entity_type' => $type,
                'entity_id'   => $entityId,
                'file_name'   => self::cleanName($file->getClientName(), $ext),
                'file_path'   => $dir . '/' . $name,
                'mime_type'   => $mime,
                'uploaded_by' => $userId,
            ], true);
        } catch (\Throwable $e) {
            @unlink(WRITEPATH . 'uploads/' . $dir . '/' . $name);

            throw $e;
        }
    }

    /**
     * Absolute path of a stored attachment, or null when the file is missing.
     *
     * @param array<string, mixed> $attachment Attachment row.
     *
     * @return string|null
     */
    public static function absolutePath(array $attachment): ?string
    {
        $relative = (string) $attachment['file_path'];

        if (str_contains($relative, '..') || ! str_starts_with($relative, self::DIR . '/')) {
            return null;
        }

        $path = WRITEPATH . 'uploads/' . $relative;

        return is_file($path) ? $path : null;
    }

    /**
     * Whether the browser may show the file inline (PDF and images); everything else is downloaded.
     *
     * @param string $mime Stored MIME type.
     *
     * @return bool
     */
    public static function isInline(string $mime): bool
    {
        return in_array($mime, ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'], true);
    }

    /**
     * Bootstrap icon name for a file.
     *
     * @param string $fileName File name (extension decides).
     *
     * @return string
     */
    public static function icon(string $fileName): string
    {
        return match (strtolower(pathinfo($fileName, PATHINFO_EXTENSION))) {
            'pdf'                         => 'file-earmark-pdf',
            'jpg', 'jpeg', 'png', 'webp'  => 'file-earmark-image',
            'doc', 'docx'                 => 'file-earmark-word',
            'xls', 'xlsx'                 => 'file-earmark-excel',
            default                       => 'file-earmark',
        };
    }

    /**
     * Check upload status, size, extension and the content-detected MIME type.
     *
     * @param UploadedFile|null $file The upload.
     *
     * @return string Lower-case extension.
     *
     * @throws ValidationFailed
     */
    private function validateFile(?UploadedFile $file): string
    {
        $limitMb = number_format(self::maxBytes() / 1048576, 1);

        if ($file === null || $file->getError() === UPLOAD_ERR_NO_FILE) {
            throw new ValidationFailed(['file' => lang('Messages.chooseFile')]);
        }

        if (in_array($file->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || $file->getSize() > self::maxBytes()) {
            throw new ValidationFailed(['file' => lang('Messages.fileTooLarge', [$limitMb])]);
        }

        if (! $file->isValid() || $file->hasMoved()) {
            throw new ValidationFailed(['file' => lang('Messages.uploadFailed')]);
        }

        $types = config(Documents::class)->attachmentTypes;
        $ext   = strtolower(pathinfo($file->getClientName(), PATHINFO_EXTENSION));

        if (! isset($types[$ext])) {
            throw new ValidationFailed(['file' => lang('Messages.fileTypeNotAllowed', [implode(', ', array_keys($types))])]);
        }

        if (! in_array((string) $file->getMimeType(), $types[$ext], true)) {
            throw new ValidationFailed(['file' => lang('Messages.fileContentMismatch', [$ext])]);
        }

        return $ext;
    }

    /**
     * Safe display name: no path, no control characters, at most 200 characters, keeps the extension.
     *
     * @param string $name Client file name.
     * @param string $ext  Validated extension.
     *
     * @return string
     */
    private static function cleanName(string $name, string $ext): string
    {
        $base = pathinfo(basename(str_replace('\\', '/', $name)), PATHINFO_FILENAME);
        $base = trim((string) preg_replace('/[\x00-\x1F\x7F"<>|:*?\/\\\\]+/u', ' ', $base));
        $base = mb_substr($base !== '' ? $base : 'file', 0, 200);

        return $base . '.' . $ext;
    }

    /**
     * php.ini size ("2M", "512K") in bytes.
     *
     * @param string $value
     *
     * @return int
     */
    private static function iniBytes(string $value): int
    {
        $value = trim($value);
        $num   = (int) $value;

        return match (strtoupper(substr($value, -1))) {
            'G'     => $num * 1073741824,
            'M'     => $num * 1048576,
            'K'     => $num * 1024,
            default => $num,
        };
    }
}
