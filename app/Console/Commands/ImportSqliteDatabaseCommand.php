<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ImportSqliteDatabaseCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:import-sqlite
                            {path? : Lokasi berkas SQLite lama (bawaan: database/database.sqlite)}
                            {--force : Kosongkan tabel PostgreSQL yang sudah berisi data sebelum diimpor}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pindahkan seluruh data Alsenform dari database SQLite lama ke PostgreSQL';

    /**
     * Tables that only hold temporary runtime data and are not copied.
     *
     * @var list<string>
     */
    protected array $skippedTables = [
        'migrations',
        'sessions',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'password_reset_tokens',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $path = (string) ($this->argument('path') ?: database_path('database.sqlite'));

        if (! is_file($path)) {
            $this->error("Berkas SQLite tidak ditemukan: {$path}");

            return self::FAILURE;
        }

        $target = DB::connection();
        if ($target->getDriverName() !== 'pgsql') {
            $this->error('Koneksi database aktif bukan PostgreSQL. Atur DB_CONNECTION=pgsql di berkas .env terlebih dahulu.');

            return self::FAILURE;
        }

        config(['database.connections.sqlite_import' => [
            'driver' => 'sqlite',
            'database' => $path,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]]);
        DB::purge('sqlite_import');
        $source = DB::connection('sqlite_import');

        if (! Schema::hasTable('migrations')) {
            $this->info('⚙️  Membuat struktur tabel PostgreSQL (php artisan migrate)...');
            $this->call('migrate', ['--force' => true]);
        }

        $tables = array_values(array_filter(
            $this->sortByForeignKeys($source, $this->sourceTables($source)),
            fn (string $table): bool => ! in_array($table, $this->skippedTables, true) && Schema::hasTable($table),
        ));

        $filledTables = array_values(array_filter($tables, fn (string $table): bool => $target->table($table)->exists()));
        if ($filledTables !== [] && ! $this->option('force')) {
            $this->error('Tabel PostgreSQL berikut sudah berisi data: '.implode(', ', $filledTables).'.');
            $this->line('Jalankan ulang dengan opsi --force untuk mengosongkannya terlebih dahulu.');

            return self::FAILURE;
        }

        $summary = [];

        $target->transaction(function () use ($source, $target, $tables, &$summary): void {
            if ($this->option('force') && $tables !== []) {
                $target->statement('TRUNCATE TABLE '.implode(', ', array_map(fn (string $table) => '"'.$table.'"', $tables)).' RESTART IDENTITY CASCADE');
            }

            foreach ($tables as $table) {
                $summary[] = [$table, $this->copyTable($source, $target, $table)];
                $this->resetSequence($target, $table);
            }
        });

        DB::purge('sqlite_import');

        $this->table(['Tabel', 'Baris dipindahkan'], $summary);
        $this->info('✓ Data SQLite berhasil dipindahkan ke PostgreSQL. Berkas media di storage/app/public tetap dipakai apa adanya.');

        return self::SUCCESS;
    }

    /**
     * Copy every row of a table, converting SQLite values to PostgreSQL types.
     */
    protected function copyTable(Connection $source, Connection $target, string $table): int
    {
        $targetColumns = collect(Schema::getColumns($table))->mapWithKeys(fn (array $column) => [$column['name'] => $column['type_name']]);
        $sourceColumns = collect($source->select('PRAGMA table_info("'.$table.'")'))->pluck('name');
        $columns = $sourceColumns->filter(fn (string $column) => $targetColumns->has($column))->values()->all();

        if ($columns === []) {
            return 0;
        }

        $copied = 0;

        $source->table($table)->select($columns)->orderByRaw('rowid')->chunk(500, function ($rows) use ($target, $table, $targetColumns, &$copied): void {
            $records = $rows->map(function (object $row) use ($targetColumns): array {
                $record = (array) $row;
                foreach ($record as $column => $value) {
                    if ($value !== null && $targetColumns->get($column) === 'bool') {
                        $record[$column] = in_array(strtolower((string) $value), ['1', 'true', 't', 'yes'], true);
                    }
                }

                return $record;
            })->all();

            $target->table($table)->insert($records);
            $copied += count($records);
        });

        return $copied;
    }

    /**
     * Continue the auto increment sequence after the highest imported id.
     */
    protected function resetSequence(Connection $target, string $table): void
    {
        if (! Schema::hasColumn($table, 'id')) {
            return;
        }

        $sequence = $target->selectOne('SELECT pg_get_serial_sequence(?, ?) AS name', ['"'.$table.'"', 'id'])?->name;
        if (! $sequence) {
            return;
        }

        $target->statement('SELECT setval(?, COALESCE((SELECT MAX(id) FROM "'.$table.'"), 0) + 1, false)', [$sequence]);
    }

    /**
     * User tables of the SQLite database.
     *
     * @return list<string>
     */
    protected function sourceTables(Connection $source): array
    {
        return collect($source->select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name"))
            ->pluck('name')
            ->all();
    }

    /**
     * Order tables so that referenced tables are copied before the tables that point to them.
     *
     * @param  list<string>  $tables
     * @return list<string>
     */
    protected function sortByForeignKeys(Connection $source, array $tables): array
    {
        $dependencies = [];
        foreach ($tables as $table) {
            $dependencies[$table] = collect($source->select('PRAGMA foreign_key_list("'.$table.'")'))
                ->pluck('table')
                ->filter(fn (string $referenced) => $referenced !== $table && in_array($referenced, $tables, true))
                ->unique()
                ->values()
                ->all();
        }

        $sorted = [];
        while ($dependencies !== []) {
            $ready = array_keys(array_filter($dependencies, fn (array $needs) => array_diff($needs, $sorted) === []));

            if ($ready === []) {
                // Circular references: keep the remaining order, PostgreSQL will report a real problem.
                return [...$sorted, ...array_keys($dependencies)];
            }

            foreach ($ready as $table) {
                $sorted[] = $table;
                unset($dependencies[$table]);
            }
        }

        return $sorted;
    }
}
