<?php

declare(strict_types=1);

namespace App\Controllers\Settings;

use App\Controllers\BaseController;
use App\Models\BankAccountModel;
use App\Models\CompanyProfileModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

/**
 * Company profile (single row) and its logo.
 * The logo is stored outside public/ in writable/uploads/company/ and streamed by logo().
 */
class Company extends BaseController
{
    private const UPLOAD_DIR = WRITEPATH . 'uploads/company/';

    /**
     * Profile form.
     *
     * @return string
     */
    public function edit(): string
    {
        return view('settings/company', [
            'profile'      => (new CompanyProfileModel())->getProfile() ?? [],
            'bankAccounts' => (new BankAccountModel())->options(),
        ]);
    }

    /**
     * Save the profile and an optional new logo.
     *
     * @return RedirectResponse
     */
    public function update(): RedirectResponse
    {
        $data = $this->postFields([
            'company_name', 'tax_id', 'is_pkp', 'umkm_certificate_number', 'umkm_certificate_valid_until',
            'address', 'phone', 'email', 'default_bank_account_id', 'website', 'document_city',
            'signatory_name', 'signatory_position', 'invoice_terms', 'agreement_terms',
        ]);

        $logo    = $this->request->getFile('logo');
        $hasLogo = $logo !== null && $logo->getError() !== UPLOAD_ERR_NO_FILE;
        $logoExt = null;

        if ($hasLogo) {
            $logoExt = $this->checkLogo($logo);

            if (is_array($logoExt)) {
                return $this->backWithErrors($logoExt);
            }
        }

        try {
            $model = new CompanyProfileModel();
            $old   = $model->getProfile();

            if (! $model->validate($data)) {
                return $this->backWithErrors($model->errors());
            }

            if ($hasLogo) {
                $name = 'logo_' . date('YmdHis') . '.' . $logoExt;
                $logo->move(self::UPLOAD_DIR, $name, true);
                $data['logo_path'] = 'company/' . $name;
            }

            if (! $model->saveProfile($data)) {
                return $this->backWithErrors($model->errors());
            }

            if ($hasLogo && ! empty($old['logo_path']) && is_file(WRITEPATH . 'uploads/' . $old['logo_path'])) {
                @unlink(WRITEPATH . 'uploads/' . $old['logo_path']);
            }
        } catch (Throwable $e) {
            return $this->failed($e, 'Company profile update');
        }

        return redirect()->to('settings/company')->with('message', lang('Messages.companySaved'));
    }

    /**
     * Stream the uploaded logo (any logged-in user; used on documents).
     *
     * @return ResponseInterface
     *
     * @throws PageNotFoundException When no logo is set.
     */
    public function logo(): ResponseInterface
    {
        $profile = (new CompanyProfileModel())->getProfile();
        $path    = $profile['logo_path'] ?? null;
        $file    = $path ? realpath(WRITEPATH . 'uploads/' . $path) : false;

        // Only serve files inside the company upload folder.
        if ($file === false || ! str_starts_with($file, (string) realpath(self::UPLOAD_DIR))) {
            throw PageNotFoundException::forPageNotFound();
        }

        $info = @getimagesize($file);

        return $this->response
            ->setHeader('Content-Type', $info['mime'] ?? 'application/octet-stream')
            ->setHeader('Cache-Control', 'private, max-age=3600')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setBody((string) file_get_contents($file));
    }

    /**
     * Validate an uploaded logo by its real content (getimagesize works without the fileinfo/GD extensions).
     *
     * @param \CodeIgniter\HTTP\Files\UploadedFile $logo Uploaded file.
     *
     * @return array<string, string>|string Field errors, or the safe file extension.
     */
    private function checkLogo(\CodeIgniter\HTTP\Files\UploadedFile $logo): array|string
    {
        $allowed = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_WEBP => 'webp'];

        if (! $logo->isValid()) {
            return ['logo' => lang('Messages.logoUploadFailed', [$logo->getErrorString()])];
        }

        if ($logo->getSize() > 1024 * 1024) {
            return ['logo' => lang('Messages.logoTooLarge')];
        }

        $info = @getimagesize($logo->getTempName());

        if ($info === false || ! isset($allowed[$info[2]])) {
            return ['logo' => lang('Messages.logoType')];
        }

        return $allowed[$info[2]];
    }
}
