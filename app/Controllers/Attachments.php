<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\AttachmentModel;
use App\Services\AttachmentService;
use App\Services\ValidationFailed;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

/**
 * Files attached to proposals, invoices, payments and delivery notes (see AttachmentService for the access rules).
 */
class Attachments extends BaseController
{
    /**
     * Upload a file. Posted: entity ("type:id"), file, return_to.
     *
     * @return RedirectResponse
     */
    public function create(): RedirectResponse
    {
        [$type, $id] = array_pad(explode(':', (string) $this->request->getPost('entity'), 2), 2, '0');
        $id          = (int) $id;
        $service     = new AttachmentService();
        $back        = $this->returnTo();

        if (! isset(AttachmentService::TYPES[$type]) || ! $service->canSee($this->scope(), $type, $id)) {
            throw PageNotFoundException::forPageNotFound();
        }

        if (! AttachmentService::canUpload($type)) {
            return redirect()->to($back)->with('error', lang('Messages.noFilesHere'));
        }

        try {
            $service->store($type, $id, $this->request->getFile('file'), $this->userId());

            return redirect()->to($back . '#files')->with('message', lang('Messages.fileUploaded'));
        } catch (ValidationFailed $e) {
            return redirect()->to($back . '#files')->with('error', implode(' ', $e->errors));
        } catch (Throwable $e) {
            return $this->failed($e, 'Attachment upload');
        }
    }

    /**
     * Show (PDF/images, inline) or download a file. ?download=1 always downloads.
     *
     * @param int $id Attachment id.
     *
     * @return ResponseInterface
     */
    public function show(int $id): ResponseInterface
    {
        $attachment = $this->visible($id);
        $path       = AttachmentService::absolutePath($attachment);

        if ($path === null) {
            log_message('error', 'Attachment {id}: file missing ({path})', ['id' => $id, 'path' => $attachment['file_path']]);

            throw PageNotFoundException::forPageNotFound();
        }

        $inline = AttachmentService::isInline($attachment['mime_type']) && $this->request->getGet('download') === null;
        $name   = str_replace(['"', "\r", "\n"], '', $attachment['file_name']);

        return $this->response
            ->setHeader('Content-Type', $inline ? $attachment['mime_type'] : 'application/octet-stream')
            ->setHeader('Content-Disposition', ($inline ? 'inline' : 'attachment') . '; filename="' . $name . '"; filename*=UTF-8\'\'' . rawurlencode($name))
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setHeader('Content-Security-Policy', "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'")
            ->setHeader('Cache-Control', 'private, max-age=0')
            ->setBody((string) file_get_contents($path));
    }

    /**
     * Remove a file (soft delete; uploader or admin).
     *
     * @param int $id Attachment id.
     *
     * @return RedirectResponse
     */
    public function delete(int $id): RedirectResponse
    {
        $attachment = $this->visible($id);
        $back       = $this->returnTo();

        if (! AttachmentService::canDelete($attachment)) {
            return redirect()->to($back . '#files')->with('error', lang('Messages.fileRemoveDenied'));
        }

        try {
            (new AttachmentModel())->delete($id);

            return redirect()->to($back . '#files')->with('message', lang('Messages.fileRemoved'));
        } catch (Throwable $e) {
            return $this->failed($e, 'Attachment removal');
        }
    }

    /**
     * The attachment if it exists and its document is visible to the user, else 404.
     *
     * @param int $id Attachment id.
     *
     * @return array<string, mixed>
     */
    private function visible(int $id): array
    {
        $attachment = (new AttachmentModel())->find($id);

        if ($attachment === null || ! (new AttachmentService())->canSee($this->scope(), $attachment['entity_type'], (int) $attachment['entity_id'])) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $attachment;
    }

    /**
     * Safe local return path from the form (only "module/123" style paths), defaulting to the dashboard.
     *
     * @return string Absolute site URL.
     */
    private function returnTo(): string
    {
        $path = (string) $this->request->getPost('return_to');

        return preg_match('#^(proposals|invoices|deliveries)/\d+$#', $path) === 1 ? site_url($path) : site_url('/');
    }
}
