<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\ActivityModel;
use App\Models\CustomerContactModel;
use App\Services\ActivityService;
use App\Services\UserService;
use App\Services\ValidationFailed;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use RuntimeException;
use Throwable;

/**
 * Activities & follow-ups (permissions crm.view / crm.manage). Forms post back to the page they came from
 * (return_to: a path inside the app).
 */
class Activities extends BaseController
{
    /** List filters: view => label key. */
    public const VIEWS = ['open' => 'Activities.viewOpen', 'overdue' => 'Activities.viewOverdue', 'today' => 'Activities.viewToday', 'done' => 'Activities.viewDone', 'all' => 'Activities.viewAll'];

    /**
     * Follow-ups and activity log with filters (view, mine/all, type, search).
     *
     * @return string
     */
    public function index(): string
    {
        $filters = [
            'view' => array_key_exists((string) $this->request->getGet('view'), self::VIEWS) ? (string) $this->request->getGet('view') : 'open',
            'who'  => $this->request->getGet('who') === 'all' ? 'all' : 'mine',
            'type' => in_array($this->request->getGet('type'), ActivityModel::TYPES, true) ? (string) $this->request->getGet('type') : '',
            'q'    => trim((string) $this->request->getGet('q')),
        ];
        $model = new ActivityModel();
        $query = $this->scope()->activities($model->withDetails());
        $today = date('Y-m-d');

        match ($filters['view']) {
            'open'    => $query->where('crm_activity.due_on IS NOT NULL', null, false)->where('crm_activity.done_at', null),
            'overdue' => $query->where('crm_activity.due_on <', $today)->where('crm_activity.done_at', null),
            'today'   => $query->where('crm_activity.due_on', $today)->where('crm_activity.done_at', null),
            'done'    => $query->where('crm_activity.done_at IS NOT NULL', null, false),
            default   => $query,
        };

        if ($filters['who'] === 'mine') {
            $query->where('crm_activity.assigned_to', $this->userId());
        }

        if ($filters['type'] !== '') {
            $query->where('crm_activity.activity_type', $filters['type']);
        }

        if ($filters['q'] !== '') {
            $query->groupStart()->like('crm_activity.subject', $filters['q'])->orLike('crm_activity.notes', $filters['q'])
                ->orLike('c.cl_organization_name', $filters['q'])->orLike('p.cp_first_name', $filters['q'])->groupEnd();
        }

        $in = $filters['view'] === 'done' || $filters['view'] === 'all';
        $query->orderBy($in ? 'COALESCE(crm_activity.done_at, crm_activity.occurred_at, crm_activity.created_at)' : 'crm_activity.due_on', $in ? 'DESC' : 'ASC', false);

        return view('activities/index', [
            'activities' => $query->paginate(30),
            'pager'      => $model->pager,
            'filters'    => $filters,
            'users'      => UserService::activeOptions(),
        ]);
    }

    /**
     * Log an activity (and optionally plan the next follow-up) or plan a follow-up.
     *
     * @return RedirectResponse
     */
    public function create(): RedirectResponse
    {
        $data = $this->postFields(ActivityService::FIELDS);
        $back = $this->returnTo((int) $data['customer_id']);

        if (! $this->scope()->canCustomer((int) $data['customer_id'])) {
            throw PageNotFoundException::forPageNotFound();
        }

        try {
            (new ActivityService())->save(null, $data, $this->userId(), $this->nextFollowUp());
        } catch (ValidationFailed $e) {
            return redirect()->to($back)->withInput()->with('errors', $e->errors)->with('activity_form', 'new');
        } catch (Throwable $e) {
            return $this->failed($e, 'Activity save');
        }

        return redirect()->to($back)->with('message', lang('Activities.saved'));
    }

    /**
     * Edit form.
     *
     * @param int $id Activity id.
     *
     * @return string
     */
    public function edit(int $id): string
    {
        $activity = $this->findEditable($id);

        return view('activities/form', [
            'activity' => $activity,
            'contacts' => (new CustomerContactModel())->forCustomer((int) $activity['customer_id']),
            'users'    => UserService::activeOptions(),
            'returnTo' => $this->returnTo((int) $activity['customer_id']),
        ]);
    }

    /**
     * Save changes.
     *
     * @param int $id Activity id.
     *
     * @return RedirectResponse
     */
    public function update(int $id): RedirectResponse
    {
        $activity = $this->findEditable($id);
        $data     = $this->postFields(ActivityService::FIELDS);
        $data['customer_id'] = $activity['customer_id']; // the school of an activity does not change

        try {
            (new ActivityService())->save($id, $data, $this->userId());
        } catch (ValidationFailed $e) {
            return $this->backWithErrors($e->errors);
        } catch (Throwable $e) {
            return $this->failed($e, 'Activity update');
        }

        return redirect()->to($this->returnTo((int) $activity['customer_id']))->with('message', lang('Activities.saved'));
    }

    /**
     * Mark a follow-up done (outcome; optional next follow-up).
     *
     * @param int $id Activity id.
     *
     * @return RedirectResponse
     */
    public function complete(int $id): RedirectResponse
    {
        $activity = $this->findVisible($id);
        $back     = $this->returnTo((int) $activity['customer_id']);

        try {
            (new ActivityService())->complete($id, (string) $this->request->getPost('outcome'), $this->userId(), $this->nextFollowUp());
        } catch (ValidationFailed $e) {
            return redirect()->to($back)->with('errors', $e->errors);
        } catch (RuntimeException $e) {
            return redirect()->to($back)->with('error', $e->getMessage());
        } catch (Throwable $e) {
            return $this->failed($e, 'Activity complete');
        }

        return redirect()->to($back)->with('message', lang('Activities.completed'));
    }

    /**
     * Delete (soft) an activity.
     *
     * @param int $id Activity id.
     *
     * @return RedirectResponse
     */
    public function delete(int $id): RedirectResponse
    {
        $activity = $this->findEditable($id);

        try {
            (new ActivityModel())->delete($id);
        } catch (Throwable $e) {
            return $this->failed($e, 'Activity delete');
        }

        return redirect()->to($this->returnTo((int) $activity['customer_id']))->with('message', lang('Activities.deleted'));
    }

    /**
     * Optional "next follow-up" fields posted with a log / completion.
     *
     * @return array<string, mixed>|null
     */
    private function nextFollowUp(): ?array
    {
        $next = $this->postFields(['next_due_on', 'next_subject', 'next_assigned_to']);

        return $next['next_due_on'] !== ''
            ? ['due_on' => $next['next_due_on'], 'subject' => $next['next_subject'], 'assigned_to' => (int) $next['next_assigned_to'] ?: null]
            : null;
    }

    /**
     * Safe path to return to (posted return_to inside the app), default the school page.
     *
     * @param int $customerId School id.
     *
     * @return string
     */
    private function returnTo(int $customerId): string
    {
        $to = (string) ($this->request->getPost('return_to') ?? $this->request->getGet('return_to') ?? '');

        if ($to === '/') {
            return '/';
        }

        return preg_match('#^[a-z][a-z0-9/_\-]*(\?[a-z0-9=&_%\-]*)?(\#[a-z\-]+)?$#i', $to) === 1 ? $to : "customers/{$customerId}#activities";
    }

    /**
     * Activity visible to the user, or 404.
     *
     * @param int $id Activity id.
     *
     * @return array<string, mixed>
     */
    private function findVisible(int $id): array
    {
        $activity = (new ActivityModel())->find($id);

        if ($activity === null || ! $this->scope()->canCustomer((int) $activity['customer_id'])) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $activity;
    }

    /**
     * Activity the user may edit, or 404.
     *
     * @param int $id Activity id.
     *
     * @return array<string, mixed>
     */
    private function findEditable(int $id): array
    {
        $activity = $this->findVisible($id);

        if (! ActivityService::canEdit($activity, $this->userId())) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $activity;
    }
}
