<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\SalesRepModel;
use CodeIgniter\HTTP\RedirectResponse;
use Throwable;

/**
 * Sales representatives (permission customers.view / customers.manage).
 * A rep does not need a login; linking a staff account enables the "My customers" filter.
 */
class SalesReps extends BaseController
{
    private const FIELDS = ['rep_name', 'rep_phone', 'rep_email', 'user_id', 'is_active'];

    private SalesRepModel $model;

    public function __construct()
    {
        $this->model = new SalesRepModel();
    }

    /**
     * List reps with linked account and customer count.
     *
     * @return string
     */
    public function index(): string
    {
        return view('sales_reps/index', ['reps' => $this->model->withStats()]);
    }

    /**
     * Create form.
     *
     * @return string
     */
    public function new(): string
    {
        return view('sales_reps/form', ['rep' => [], 'users' => $this->userOptions(null)]);
    }

    /**
     * Store a rep.
     *
     * @return RedirectResponse
     */
    public function create(): RedirectResponse
    {
        try {
            if ($this->model->insert($this->postFields(self::FIELDS)) === false) {
                return $this->backWithErrors($this->model->errors());
            }
        } catch (Throwable $e) {
            return $this->failed($e, 'Sales rep create');
        }

        return redirect()->to('sales-reps')->with('message', lang('SalesReps.added'));
    }

    /**
     * Edit form.
     *
     * @param int $id Rep id.
     *
     * @return string
     */
    public function edit(int $id): string
    {
        $rep = $this->findOr404($this->model, $id);

        return view('sales_reps/form', [
            'rep'   => $rep,
            'users' => $this->userOptions($rep['user_id'] !== null ? (int) $rep['user_id'] : null),
            'inUse' => $this->model->isInUse($id),
        ]);
    }

    /**
     * Update a rep.
     *
     * @param int $id Rep id.
     *
     * @return RedirectResponse
     */
    public function update(int $id): RedirectResponse
    {
        $this->findOr404($this->model, $id);
        $data       = $this->postFields(self::FIELDS);
        $data['id'] = $id;

        try {
            if (! $this->model->update($id, $data)) {
                return $this->backWithErrors($this->model->errors());
            }
        } catch (Throwable $e) {
            return $this->failed($e, 'Sales rep update');
        }

        return redirect()->to('sales-reps')->with('message', lang('SalesReps.updated'));
    }

    /**
     * Delete a rep that is not assigned anywhere.
     *
     * @param int $id Rep id.
     *
     * @return RedirectResponse
     */
    public function delete(int $id): RedirectResponse
    {
        $this->findOr404($this->model, $id);

        if ($this->model->isInUse($id)) {
            return redirect()->back()->with('error', lang('SalesReps.inUse'));
        }

        try {
            $this->model->delete($id);
        } catch (Throwable $e) {
            return $this->failed($e, 'Sales rep delete');
        }

        return redirect()->to('sales-reps')->with('message', lang('SalesReps.deleted'));
    }

    /**
     * Staff accounts not yet linked to another rep: id => "Full name (@username)".
     *
     * @param int|null $current Account linked to the rep being edited (kept in the list).
     *
     * @return array<int, string>
     */
    private function userOptions(?int $current): array
    {
        $builder = db_connect()->table('users u')
            ->select('u.id, u.username, u.full_name')
            ->join('sales_representative r', 'r.user_id = u.id AND r.deleted_at IS NULL', 'left')
            ->where('u.deleted_at', null)
            ->groupStart()->where('r.id', null);

        if ($current !== null) {
            $builder->orWhere('u.id', $current);
        }

        $rows    = $builder->groupEnd()->orderBy('u.username')->get()->getResultArray();
        $options = [];

        foreach ($rows as $u) {
            $options[(int) $u['id']] = ($u['full_name'] ?: $u['username']) . ' (@' . $u['username'] . ')';
        }

        return $options;
    }
}
