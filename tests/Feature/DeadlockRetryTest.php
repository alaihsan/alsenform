<?php

use App\Database\MariaDbConnection;
use App\Database\MySqlConnection;
use App\Database\PostgresConnection;
use App\Database\RetriesDeadlockedStatements;
use Illuminate\Database\QueryException;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Support\Facades\DB;

/*
 * A statement the database cancelled to break a deadlock, as MySQL did when a class polled its exam
 * in the same second ("1213 Deadlock found when trying to get lock"), is run again instead of
 * ending in an error.
 */

const DEADLOCK = ['40001', 1213, 'Deadlock found when trying to get lock; try restarting transaction'];

/**
 * A database whose next $cancelled statements fail with the given error, as MySQL reports it.
 *
 * @param  array{0: string, 1: int, 2: string}  $errorInfo
 */
function databaseCancelling(int $cancelled, array $errorInfo = DEADLOCK): SQLiteConnection
{
    return new class(new PDO('sqlite::memory:'), $cancelled, $errorInfo) extends SQLiteConnection
    {
        use RetriesDeadlockedStatements;

        public int $attempts = 0;

        /**
         * @param  array{0: string, 1: int, 2: string}  $errorInfo
         */
        public function __construct(PDO $pdo, private int $cancelled, private array $errorInfo)
        {
            parent::__construct($pdo);
        }

        protected function runQueryCallback($query, $bindings, Closure $callback)
        {
            $this->attempts++;

            if ($this->cancelled > 0) {
                $this->cancelled--;
                $cause = new PDOException("SQLSTATE[{$this->errorInfo[0]}]: {$this->errorInfo[1]} {$this->errorInfo[2]}");
                $cause->errorInfo = $this->errorInfo;

                throw new QueryException('mysql', $query, $bindings, $cause);
            }

            return parent::runQueryCallback($query, $bindings, $callback);
        }
    };
}

test('a statement a deadlock cancelled is run again, and answers as if nothing happened', function () {
    $database = databaseCancelling(2);

    expect($database->select('select 42 as answer'))->toEqual([(object) ['answer' => 42]])
        ->and($database->attempts)->toBe(3);
});

test('a statement still cancelled after three more tries is reported', function () {
    $database = databaseCancelling(10);

    expect(fn () => $database->select('select 1'))->toThrow(QueryException::class, 'Deadlock found');
    expect($database->attempts)->toBe(1 + MySqlConnection::DEADLOCK_RETRIES);
});

test('a PostgreSQL deadlock is run again too', function () {
    $database = databaseCancelling(1, ['40P01', 7, 'ERROR: deadlock detected']);

    expect($database->select('select 1 as answer'))->toHaveCount(1)
        ->and($database->attempts)->toBe(2);
});

test('other errors, such as a lock wait timeout, are not run again', function () {
    $database = databaseCancelling(1, ['HY000', 1205, 'Lock wait timeout exceeded; try restarting transaction']);

    expect(fn () => $database->select('select 1'))->toThrow(QueryException::class, 'Lock wait timeout');
    expect($database->attempts)->toBe(1);
});

test('a statement within a transaction is left to the transaction, which runs again as a whole', function () {
    $database = databaseCancelling(1);

    expect(fn () => $database->transaction(fn () => $database->select('select 1')))->toThrow(QueryException::class);
    expect($database->attempts)->toBe(1);

    $database = databaseCancelling(1);

    expect($database->transaction(fn () => $database->select('select 1 as answer'), attempts: 2))->toHaveCount(1)
        ->and($database->attempts)->toBe(2);
});

test('the server\'s databases run their deadlocked statements again: MySQL, MariaDB and PostgreSQL', function () {
    try {
        // Made without connecting: the connection opens on its first statement.
        expect(DB::connection('mysql'))->toBeInstanceOf(MySqlConnection::class)
            ->and(DB::connection('mariadb'))->toBeInstanceOf(MariaDbConnection::class)
            ->and(DB::connection('pgsql'))->toBeInstanceOf(PostgresConnection::class);
    } finally {
        DB::purge('mysql');
        DB::purge('mariadb');
        DB::purge('pgsql');
    }
});
