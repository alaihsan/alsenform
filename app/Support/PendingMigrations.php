<?php

namespace App\Support;

use Illuminate\Database\Migrations\Migrator;

/**
 * Database migrations that have not been applied yet, e.g. right after "git pull".
 */
class PendingMigrations
{
    /**
     * The names of the migrations that still have to run.
     *
     * @return list<string>
     */
    public function names(): array
    {
        /** @var Migrator $migrator */
        $migrator = app('migrator');

        $files = array_keys($migrator->getMigrationFiles(array_merge([database_path('migrations')], $migrator->paths())));

        if (! $migrator->repositoryExists()) {
            return $files;
        }

        return array_values(array_diff($files, $migrator->getRepository()->getRan()));
    }
}
