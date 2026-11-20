<?php

namespace App\Database;

use Closure;
use Illuminate\Database\QueryException;
use PDOException;

/**
 * Runs a statement again when the database cancelled it to break a deadlock. When a class polls
 * its exam in the same second, two statements can each wait for rows the other holds (a session
 * saved while old sessions are cleaned up, two rate limits of the same page in the cache table),
 * and MySQL cancels one of them, which used to end in an error. A statement on its own, outside a
 * transaction, had no effect when it was cancelled, so it is safe to run once more. A statement
 * within a transaction is left to the transaction: DB::transaction() runs again as a whole.
 */
trait RetriesDeadlockedStatements
{
    /**
     * How often a cancelled statement is run again before its error is reported.
     */
    public const DEADLOCK_RETRIES = 3;

    /**
     * A deadlock or a serialization failure: SQLSTATE 40001 (MySQL error 1213, PostgreSQL) or
     * 40P01 (PostgreSQL). A lock wait timeout is not one: that statement already waited 50 seconds.
     */
    public static function isDeadlock(QueryException $exception): bool
    {
        $previous = $exception->getPrevious();
        $sqlState = $previous instanceof PDOException ? ($previous->errorInfo[0] ?? $previous->getCode()) : $exception->getCode();

        return in_array((string) $sqlState, ['40001', '40P01'], true);
    }

    /**
     * @param  string  $query
     * @param  array<int|string, mixed>  $bindings
     *
     * @throws QueryException
     */
    protected function handleQueryException(QueryException $e, $query, $bindings, Closure $callback): mixed
    {
        if ($this->transactions >= 1 || ! self::isDeadlock($e)) {
            return parent::handleQueryException($e, $query, $bindings, $callback);
        }

        for ($attempt = 1; ; $attempt++) {
            // A short pause, different for each request, so the two statements do not meet again.
            usleep(random_int(5, 25) * 1000 * $attempt);

            try {
                return $this->runQueryCallback($query, $bindings, $callback);
            } catch (QueryException $retryException) {
                if ($attempt >= self::DEADLOCK_RETRIES || ! self::isDeadlock($retryException)) {
                    throw $retryException;
                }
            }
        }
    }
}
