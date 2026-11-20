<?php

namespace App\Database;

use Illuminate\Database\MySqlConnection as BaseMySqlConnection;

/**
 * MySQL, the database of the school's server, with statements a deadlock cancelled run again.
 */
class MySqlConnection extends BaseMySqlConnection
{
    use RetriesDeadlockedStatements;
}
