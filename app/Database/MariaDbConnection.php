<?php

namespace App\Database;

use Illuminate\Database\MariaDbConnection as BaseMariaDbConnection;

/**
 * MariaDB, with statements a deadlock cancelled run again.
 */
class MariaDbConnection extends BaseMariaDbConnection
{
    use RetriesDeadlockedStatements;
}
