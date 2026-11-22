<?php

declare(strict_types=1);

namespace App\Controllers\Settings;

use App\Controllers\BaseController;
use App\Models\LookupModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use Throwable;

/**
 * Generic CRUD for lookup tables defined in Config\Lookups.
 * Codes are set on create and locked afterwards, because application logic refers to them.
 */
class Lookups extends BaseController
{
    /**
     * List rows of one lookup type.
     *
     * @param string $type URL slug, e.g. 'payment-terms'.
     *
     * @return string
     */
    public function index(string $type): string
    {
        [$def, $model] = $this->resolve($type);

        $model->orderBy($def['order'] ?? 'code');

        if (array_key_exists('valid_from', $def['columns'])) {
            $model->orderBy('code')->orderBy('valid_from', 'DESC');
        }

        return view('settings/lookups/index', [
            'type' => $type,
            'def'  => $def,
            'rows' => $model->findAll(),
        ]);
    }

    /**
     * Create form.
     *
     * @param string $type Lookup slug.
     *
     * @return string
     */
    public function new(string $type): string
    {
        [$def] = $this->resolve($type, true);

        return view('settings/lookups/form', ['type' => $type, 'def' => $def, 'row' => []]);
    }

    /**
     * Store a new row.
     *
     * @param string $type Lookup slug.
     *
     * @return RedirectResponse
     */
    public function create(string $type): RedirectResponse
    {
        [$def, $model] = $this->resolve($type, true);
        $data          = $this->postFields(array_keys($def['fields']));

        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }

        $errors = $model->extraErrors($data, null);

        if ($errors !== []) {
            return $this->backWithErrors($errors);
        }

        try {
            if ($model->insert($data) === false) {
                return $this->backWithErrors($model->errors());
            }
        } catch (Throwable $e) {
            return $this->failed($e, "Lookup {$type} create");
        }

        return redirect()->to("settings/lookups/{$type}")->with('message', lang('Messages.lookupAdded', [$def['title']]));
    }

    /**
     * Edit form.
     *
     * @param string $type Lookup slug.
     * @param int    $id   Row id.
     *
     * @return string
     */
    public function edit(string $type, int $id): string
    {
        [$def, $model] = $this->resolve($type, true);

        return view('settings/lookups/form', ['type' => $type, 'def' => $def, 'row' => $this->findOr404($model, $id)]);
    }

    /**
     * Update a row (code stays unchanged).
     *
     * @param string $type Lookup slug.
     * @param int    $id   Row id.
     *
     * @return RedirectResponse
     */
    public function update(string $type, int $id): RedirectResponse
    {
        [$def, $model] = $this->resolve($type, true);
        $row           = $this->findOr404($model, $id);

        $data         = $this->postFields(array_keys($def['fields']));
        $data['code'] = $row['code']; // locked
        $data['id']   = $id;

        $errors = $model->extraErrors($data, $id);

        if ($errors !== []) {
            return $this->backWithErrors($errors);
        }

        try {
            if (! $model->update($id, $data)) {
                return $this->backWithErrors($model->errors());
            }
        } catch (Throwable $e) {
            return $this->failed($e, "Lookup {$type} update");
        }

        return redirect()->to("settings/lookups/{$type}")->with('message', lang('Messages.lookupUpdated', [$def['title']]));
    }

    /**
     * Delete a row that nothing references.
     *
     * @param string $type Lookup slug.
     * @param int    $id   Row id.
     *
     * @return RedirectResponse
     */
    public function delete(string $type, int $id): RedirectResponse
    {
        [$def, $model] = $this->resolve($type, true);
        $this->findOr404($model, $id);

        if ($model->isInUse($id)) {
            return redirect()->back()->with('error', lang('Messages.inUseCannotDelete'));
        }

        try {
            $model->delete($id);
        } catch (Throwable $e) {
            return $this->failed($e, "Lookup {$type} delete");
        }

        return redirect()->to("settings/lookups/{$type}")->with('message', lang('Messages.lookupDeleted', [$def['title']]));
    }

    /**
     * Definition and model for a slug.
     *
     * @param string $type     Lookup slug.
     * @param bool   $writable Reject read-only (system) lookups.
     *
     * @return array{0: array<string, mixed>, 1: LookupModel}
     *
     * @throws PageNotFoundException
     */
    private function resolve(string $type, bool $writable = false): array
    {
        $def = config('Lookups')->types[$type] ?? null;

        if ($def === null || ($writable && ! empty($def['readonly']))) {
            throw PageNotFoundException::forPageNotFound();
        }

        return [localize_lookup($type, $def), new $def['model']()];
    }
}
