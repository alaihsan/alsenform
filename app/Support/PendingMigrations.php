<?php

namespace App\Support;

use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Artisan;

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

    /**
     * Run the pending migrations, one server process at a time. Returns whether the database
     * is up to date afterwards.
     */
    public function apply(): bool
    {
        Artisan::call('migrate', ['--force' => true, '--isolated' => true]);

        return $this->names() === [];
    }
}
