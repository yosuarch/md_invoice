<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Read access to audit_log (rows are written by App\Services\AuditLogger). Append-only.
 */
class AuditLogModel extends Model
{
    protected $table          = 'audit_log';
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = false;
    protected $allowedFields  = [];

    /**
     * Filtered query for the audit log viewer, newest first, with the username.
     *
     * @param array{table?: string, action?: string, user_id?: string, record_id?: string} $filters
     *
     * @return $this
     */
    public function filtered(array $filters): self
    {
        $this->select('audit_log.*, users.username')
            ->join('users', 'users.id = audit_log.user_id', 'left')
            ->orderBy('audit_log.id', 'DESC');

        if (($filters['table'] ?? '') !== '') {
            $this->where('audit_log.table_name', $filters['table']);
        }

        if (($filters['action'] ?? '') !== '') {
            $this->where('audit_log.action', $filters['action']);
        }

        if (($filters['user_id'] ?? '') !== '') {
            $this->where('audit_log.user_id', (int) $filters['user_id']);
        }

        if (($filters['record_id'] ?? '') !== '') {
            $this->where('audit_log.record_id', (int) $filters['record_id']);
        }

        return $this;
    }

    /**
     * Distinct table names present in the log (for the filter dropdown).
     *
     * @return list<string>
     */
    public function tableNames(): array
    {
        $rows = $this->db->table($this->table)->distinct()->select('table_name')->orderBy('table_name')->get()->getResultArray();

        return array_column($rows, 'table_name');
    }
}
