<?php

namespace App\Database;

use Illuminate\Database\PostgresConnection as BasePostgresConnection;

/**
 * PostgreSQL, with statements a deadlock cancelled run again.
 */
class PostgresConnection extends BasePostgresConnection
{
    use RetriesDeadlockedStatements;
}
