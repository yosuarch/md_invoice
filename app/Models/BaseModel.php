<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\AuditLogger;
use CodeIgniter\Model;

/**
 * Base for all business-table models (see .guidelines/database.md §0):
 * array results, timestamps, soft deletes, automatic audit_log rows,
 * empty-string-to-NULL conversion and "is this row referenced?" checks.
 *
 * Always update/delete by primary key (update($id, ...), delete($id)) so changes are audited.
 */
abstract class BaseModel extends Model
{
    protected $returnType     = 'array';
    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;
    protected $dateFormat     = 'datetime';
    protected $allowCallbacks = true;

    /**
     * Write audit_log rows for insert/update/delete.
     */
    protected bool $audited = true;

    /**
     * Nullable columns where an empty form value must be stored as NULL.
     *
     * @var list<string>
     */
    protected array $nullable = [];

    /**
     * Name/title columns stored in lower case (trimmed, inner whitespace collapsed); views show them in upper case
     * with up(). Never list emails, codes, numbers or paragraphs here.
     *
     * @var list<string>
     */
    protected array $lowercase = [];

    /**
     * Tables that reference this one: table => foreign key column.
     * Used by isInUse() to block deleting rows that documents point to.
     *
     * @var array<string, string>
     */
    protected array $referencedBy = [];

    /**
     * Human labels for validation messages; fields not listed are derived from the column name
     * (prefix like cl_/ccp_/pl_ and a trailing _id removed: cl_organization_name -> "Organization name").
     *
     * @var array<string, string>
     */
    protected array $fieldLabels = [];

    /**
     * Rows captured before update/delete, keyed by id, for the audit diff.
     *
     * @var array<int|string, array<string, mixed>>
     */
    private array $auditBefore = [];

    /**
     * Register the shared callbacks in front of any model-specific ones.
     *
     * @return void
     */
    protected function initialize(): void
    {
        parent::initialize();

        // CodeIgniter 4.7 needs a rule for every field used as a placeholder ({id} in is_unique rules).
        if (is_array($this->validationRules) && ! isset($this->validationRules['id'])
            && str_contains(json_encode($this->validationRules) ?: '', '{id}')) {
            $this->validationRules['id'] = ['label' => 'Id', 'rules' => 'permit_empty|is_natural_no_zero'];
        }

        // Give every rule a readable label for error messages. Labels and custom messages are language keys when
        // app/Language/<locale>/Fields.php has them ("<table>__<field>", "msg__<table>__<field>__<rule>"); the
        // validator passes both through lang(), so errors appear in the user's language.
        if (is_array($this->validationRules)) {
            foreach ($this->validationRules as $field => $rules) {
                if (is_string($rules)) {
                    $rules = ['label' => $this->labelFor($field), 'rules' => $rules];
                }

                if (self::hasFieldKey($this->table . '__' . $field)) {
                    $rules['label'] = 'Fields.' . $this->table . '__' . $field;
                }

                $this->validationRules[$field] = $rules;
            }
        }

        if (is_array($this->validationMessages)) {
            foreach ($this->validationMessages as $field => $messages) {
                foreach ((array) $messages as $rule => $message) {
                    $key = 'msg__' . $this->table . '__' . $field . '__' . $rule;

                    if (self::hasFieldKey($key)) {
                        $this->validationMessages[$field][$rule] = 'Fields.' . $key;
                    }
                }
            }
        }

        $this->beforeInsert = ['normalizeCase', 'convertEmptyToNull', ...$this->beforeInsert];
        $this->beforeUpdate = ['normalizeCase', 'convertEmptyToNull', ...$this->beforeUpdate];

        if ($this->audited) {
            $this->beforeUpdate[] = 'auditCapture';
            $this->beforeDelete[] = 'auditCapture';
            $this->afterInsert[]  = 'auditInsert';
            $this->afterUpdate[]  = 'auditUpdate';
            $this->afterDelete[]  = 'auditDelete';
        }
    }

    /**
     * Whether app/Language/en/Fields.php (the reference file; every locale has the same keys) defines $key.
     *
     * @param string $key Key without the "Fields." prefix.
     *
     * @return bool
     */
    public static function hasFieldKey(string $key): bool
    {
        static $keys = null;
        $keys ??= is_file(APPPATH . 'Language/en/Fields.php') ? (require APPPATH . 'Language/en/Fields.php') : [];

        return isset($keys[$key]);
    }

    /**
     * Readable label for a column (English; see initialize() for the translated key).
     *
     * @param string $field Column name.
     *
     * @return string
     */
    public function labelFor(string $field): string
    {
        if (isset($this->fieldLabels[$field])) {
            return $this->fieldLabels[$field];
        }

        $name = preg_replace(['/^(cl|ccp|pl|cpl|cpi)_/', '/_id$/'], '', $field);

        return ucfirst(str_replace('_', ' ', (string) $name));
    }

    /**
     * Whether any non-deleted row in $referencedBy points at this id.
     *
     * @param int $id Primary key.
     *
     * @return bool
     */
    public function isInUse(int $id): bool
    {
        foreach ($this->referencedBy as $table => $column) {
            $builder = $this->db->table($table)->where($column, $id);

            if ($this->db->fieldExists('deleted_at', $table)) {
                $builder->where('deleted_at', null);
            }

            if ($builder->countAllResults() > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Options for a <select>: id => label.
     *
     * @param string $labelColumn Column used as label.
     *
     * @return array<int, string>
     */
    public function options(string $labelColumn): array
    {
        $rows = $this->select([$this->primaryKey, $labelColumn])->orderBy($labelColumn)->findAll();

        return array_column($rows, $labelColumn, $this->primaryKey);
    }

    /**
     * beforeInsert/beforeUpdate: lower-case the $lowercase columns (see normalize_name()).
     *
     * @param array{data: array<string, mixed>} $event
     *
     * @return array{data: array<string, mixed>}
     */
    protected function normalizeCase(array $event): array
    {
        foreach ($this->lowercase as $column) {
            if (isset($event['data'][$column]) && is_string($event['data'][$column])) {
                $event['data'][$column] = normalize_name($event['data'][$column]);
            }
        }

        return $event;
    }

    /**
     * beforeInsert/beforeUpdate: store '' as NULL for nullable columns.
     *
     * @param array{data: array<string, mixed>} $event
     *
     * @return array{data: array<string, mixed>}
     */
    protected function convertEmptyToNull(array $event): array
    {
        foreach ($this->nullable as $column) {
            if (array_key_exists($column, $event['data']) && $event['data'][$column] === '') {
                $event['data'][$column] = null;
            }
        }

        return $event;
    }

    /**
     * beforeUpdate/beforeDelete: remember current rows (separate query, so the model state is untouched).
     *
     * @param array{id: list<int|string>|int|string|null} $event
     *
     * @return array<string, mixed>
     */
    protected function auditCapture(array $event): array
    {
        $ids = (array) ($event['id'] ?? []);

        if ($ids !== []) {
            $rows = $this->db->table($this->table)->whereIn($this->primaryKey, $ids)->get()->getResultArray();

            foreach ($rows as $row) {
                $this->auditBefore[$row[$this->primaryKey]] = $row;
            }
        }

        return $event;
    }

    /**
     * afterInsert: log the new row.
     *
     * @param array{id: int|string, data: array<string, mixed>, result: mixed} $event
     *
     * @return array<string, mixed>
     */
    protected function auditInsert(array $event): array
    {
        if ($event['result'] && $event['id']) {
            AuditLogger::log($this->table, (int) $event['id'], AuditLogger::CREATE, null, $event['data']);
        }

        return $event;
    }

    /**
     * afterUpdate: log only the columns that actually changed.
     *
     * @param array{id: list<int|string>|null, data: array<string, mixed>, result: bool} $event
     *
     * @return array<string, mixed>
     */
    protected function auditUpdate(array $event): array
    {
        foreach ((array) ($event['id'] ?? []) as $id) {
            $before = $this->auditBefore[$id] ?? null;
            unset($this->auditBefore[$id]);

            if (! $event['result'] || $before === null) {
                continue;
            }

            [$old, $new] = AuditLogger::diff($before, $event['data']);

            if ($new !== []) {
                AuditLogger::log($this->table, (int) $id, AuditLogger::UPDATE, $old, $new);
            }
        }

        return $event;
    }

    /**
     * afterDelete: log the removed (or soft-deleted) row.
     *
     * @param array{id: list<int|string>|null, purge: bool, result: mixed} $event
     *
     * @return array<string, mixed>
     */
    protected function auditDelete(array $event): array
    {
        foreach ((array) ($event['id'] ?? []) as $id) {
            $before = $this->auditBefore[$id] ?? null;
            unset($this->auditBefore[$id]);

            if ($event['result'] && $before !== null) {
                $new = $event['purge'] ? null : ['deleted_at' => date('Y-m-d H:i:s')];
                AuditLogger::log($this->table, (int) $id, AuditLogger::DELETE, $before, $new);
            }
        }

        return $event;
    }
}
