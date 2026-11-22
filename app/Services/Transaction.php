<?php

declare(strict_types=1);

namespace App\Services;

use Closure;
use CodeIgniter\Database\BaseConnection;
use Throwable;

/**
 * Runs a unit of work in a database transaction.
 * Nested calls join the outer transaction (CodeIgniter tracks the depth).
 */
final class Transaction
{
    /**
     * Execute $work inside a transaction; commit on success, roll back and rethrow on failure.
     *
     * @template T
     *
     * @param Closure(BaseConnection): T $work Unit of work; receives the connection.
     *
     * @return T Whatever $work returns.
     *
     * @throws Throwable When $work (or the commit) fails.
     */
    public static function run(Closure $work): mixed
    {
        $db = db_connect();
        $db->transException(true);
        $db->transBegin();

        try {
            $result = $work($db);
            $db->transCommit();

            return $result;
        } catch (Throwable $e) {
            $db->transRollback();

            throw $e;
        }
    }
}
