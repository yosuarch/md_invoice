<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\AuditLogModel;

/**
 * Read-only audit trail viewer (permission audit.view).
 */
class AuditLog extends BaseController
{
    /**
     * Filtered, paginated list of changes, newest first.
     *
     * @return string
     */
    public function index(): string
    {
        $filters = [
            'table'     => (string) $this->request->getGet('table'),
            'action'    => (string) $this->request->getGet('action'),
            'user_id'   => (string) $this->request->getGet('user_id'),
            'record_id' => (string) $this->request->getGet('record_id'),
        ];

        $model = new AuditLogModel();
        $users = db_connect()->table('users')->select('id, username')->orderBy('username')->get()->getResultArray();

        return view('audit_log/index', [
            'entries' => $model->filtered($filters)->paginate(30),
            'pager'   => $model->pager,
            'filters' => $filters,
            'tables'  => $model->tableNames(),
            'users'   => array_column($users, 'username', 'id'),
        ]);
    }
}
