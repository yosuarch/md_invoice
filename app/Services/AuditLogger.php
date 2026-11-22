<?php

declare(strict_types=1);

namespace App\Services;

use CodeIgniter\HTTP\IncomingRequest;
use Throwable;

/**
 * Writes rows to `audit_log`. Used automatically by App\Models\BaseModel and
 * manually for tables managed by other code (e.g. Shield users).
 */
final class AuditLogger
{
    public const CREATE = 'CREATE';
    public const UPDATE = 'UPDATE';
    public const DELETE = 'DELETE';

    /** Columns never written to the log. */
    private const IGNORED = ['created_at', 'updated_at', 'password', 'password_hash', 'password_confirm'];

    /**
     * Record one change.
     *
     * @param string                    $table     Table name.
     * @param int                       $recordId  Primary key of the changed row.
     * @param string                    $action    CREATE, UPDATE or DELETE.
     * @param array<string, mixed>|null $oldValues Values before the change.
     * @param array<string, mixed>|null $newValues Values after the change.
     *
     * @return void
     */
    public static function log(string $table, int $recordId, string $action, ?array $oldValues, ?array $newValues): void
    {
        db_connect()->table('audit_log')->insert([
            'user_id'    => self::currentUserId(),
            'table_name' => $table,
            'record_id'  => $recordId,
            'action'     => $action,
            'old_values' => self::encode($oldValues),
            'new_values' => self::encode($newValues),
            'ip_address' => self::ipAddress(),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Compare two rows and return only the changed columns.
     *
     * @param array<string, mixed> $old Row before.
     * @param array<string, mixed> $new Submitted values (may be partial).
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>} [old changed values, new changed values]
     */
    public static function diff(array $old, array $new): array
    {
        $before = [];
        $after  = [];

        foreach ($new as $key => $value) {
            if (in_array($key, self::IGNORED, true)) {
                continue;
            }

            $oldValue = $old[$key] ?? null;

            if (self::normalize($oldValue) !== self::normalize($value)) {
                $before[$key] = $oldValue;
                $after[$key]  = $value;
            }
        }

        return [$before, $after];
    }

    /**
     * Drop ignored columns (timestamps, secrets) from a row.
     *
     * @param array<string, mixed> $row Row data.
     *
     * @return array<string, mixed>
     */
    public static function clean(array $row): array
    {
        return array_diff_key($row, array_flip(self::IGNORED));
    }

    /**
     * Normalize values so "1" vs 1 and "10.00" vs "10.0" do not count as changes.
     *
     * @param mixed $value Raw value.
     *
     * @return string|null
     */
    private static function normalize(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_numeric($value)) {
            return rtrim(rtrim(number_format((float) $value, 6, '.', ''), '0'), '.');
        }

        return (string) $value;
    }

    /**
     * @param array<string, mixed>|null $values
     *
     * @return string|null JSON or null.
     */
    private static function encode(?array $values): ?string
    {
        if ($values === null) {
            return null;
        }

        return json_encode(self::clean($values), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * @return int|null Logged-in user id, or null for CLI/system changes.
     */
    private static function currentUserId(): ?int
    {
        if (is_cli()) {
            return null;
        }

        try {
            $id = auth()->id();

            return $id === null ? null : (int) $id;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return string|null Client IP for web requests.
     */
    private static function ipAddress(): ?string
    {
        $request = service('request');

        return $request instanceof IncomingRequest ? $request->getIPAddress() : null;
    }
}
