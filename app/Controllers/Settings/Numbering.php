<?php

declare(strict_types=1);

namespace App\Controllers\Settings;

use App\Controllers\BaseController;
use App\Models\DocumentNumberingModel;
use App\Services\DocumentSequence;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use DateTimeImmutable;
use Throwable;

/**
 * Settings → Document numbering: the number format of every document type and of product SKUs
 * (permission settings.manage). Existing documents keep their numbers; new ones use the new format.
 */
class Numbering extends BaseController
{
    /**
     * Overview with the next number of each type.
     *
     * @return string
     */
    public function index(): string
    {
        $sequence = new DocumentSequence();
        $rows     = [];

        foreach ($sequence->definitions() as $type => $def) {
            $rows[$type] = ['label' => code_label('docType', $type, $def['label'])] + $def + ['next' => $sequence->preview($type)];
        }

        return view('settings/numbering/index', ['rows' => $rows]);
    }

    /**
     * Edit form of one type.
     *
     * @param string $type Document type.
     *
     * @return string
     */
    public function edit(string $type): string
    {
        $def = $this->definition($type);

        return view('settings/numbering/form', [
            'type' => $type,
            'def'  => $def,
            'next' => (new DocumentSequence())->preview($type),
        ]);
    }

    /**
     * Save a format.
     *
     * @param string $type Document type.
     *
     * @return RedirectResponse
     */
    public function update(string $type): RedirectResponse
    {
        $this->definition($type);
        $pattern = trim((string) $this->request->getPost('pattern'));
        $period  = (string) $this->request->getPost('period');
        $errors  = DocumentSequence::validate($pattern, $period);

        if ($errors !== []) {
            return $this->backWithErrors(['pattern' => implode(' ', $errors)]);
        }

        try {
            $model = new DocumentNumberingModel();
            $row   = $model->forType($type);

            if ($row === null) {
                $model->insert(['doc_type' => $type, 'pattern' => $pattern, 'period' => $period]);
            } else {
                $model->update((int) $row['id'], ['pattern' => $pattern, 'period' => $period]);
            }

            DocumentSequence::clearCache();
        } catch (Throwable $e) {
            return $this->failed($e, 'Numbering update');
        }

        return redirect()->to('settings/numbering')->with('message', lang('Messages.numberingSaved', [(new DocumentSequence())->preview($type)]));
    }

    /**
     * Live example for the form (?pattern=&period=): JSON with the example number or the problems.
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function preview(): \CodeIgniter\HTTP\ResponseInterface
    {
        $pattern = (string) $this->request->getGet('pattern');
        $period  = (string) $this->request->getGet('period');
        $errors  = DocumentSequence::validate($pattern, $period);

        return $this->response->setJSON([
            'errors'  => $errors,
            'example' => $errors === [] ? DocumentSequence::format($pattern, 1, new DateTimeImmutable()) : null,
        ]);
    }

    /**
     * @param string $type Document type.
     *
     * @return array{label: string, pattern: string, period: string, table: string, column: string}
     *
     * @throws PageNotFoundException
     */
    private function definition(string $type): array
    {
        $def = (new DocumentSequence())->definitions()[$type] ?? throw PageNotFoundException::forPageNotFound();

        return ['label' => code_label('docType', $type, $def['label'])] + $def;
    }
}
