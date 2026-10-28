<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Database Backups
    |--------------------------------------------------------------------------
    |
    | "php artisan db:backup" writes a copy of the database to this folder.
    | "php artisan lan:serve" makes one when it starts (if the latest is
    | older than "fresh_hours") and every day while it runs. The newest
    | "keep" copies are kept; lan:check warns after "max_age_hours".
    |
    */

    'backup' => [
        'path' => env('BACKUP_PATH', storage_path('app/backups')),
        'keep' => (int) env('BACKUP_KEEP', 14),
        'fresh_hours' => 12,
        'max_age_hours' => 24,
        // Herd and Postgres.app put these outside the PATH: set the full path when needed.
        'pg_dump' => env('BACKUP_PG_DUMP', 'pg_dump'),
        'mysqldump' => env('BACKUP_MYSQLDUMP', 'mysqldump'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    |
    | Days kept before "php artisan model:prune" (run by lan:serve every day)
    | removes old records: the activity report, the periodic copies of the
    | answers during an exam, and server errors marked as solved.
    |
    */

    'retention_days' => [
        'activity_logs' => (int) env('ACTIVITY_LOG_DAYS', 365),
        'draft_snapshots' => (int) env('DRAFT_SNAPSHOT_DAYS', 90),
        'server_errors' => 90,
    ],

];
