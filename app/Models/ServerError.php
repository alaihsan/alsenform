<?php

namespace App\Models;

use Database\Factories\ServerErrorFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One fault of the server, however often it happened: where it comes from, how often, and the
 * request it last happened on (see App\Support\ServerErrorRecorder).
 */
class ServerError extends Model
{
    /** @use HasFactory<ServerErrorFactory> */
    use HasFactory;

    use MassPrunable;

    protected $fillable = [
        'fingerprint',
        'exception',
        'message',
        'location',
        'occurrences',
        'last_reference',
        'last_method',
        'last_url',
        'last_user_id',
        'last_seen_at',
        'resolved_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurrences' => 'integer',
            'last_seen_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function lastUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_user_id');
    }

    /**
     * Faults marked as solved that did not come back for the retention period are forgotten.
     *
     * @return Builder<ServerError>
     */
    public function prunable(): Builder
    {
        return static::query()
            ->whereNotNull('resolved_at')
            ->where('last_seen_at', '<', now()->subDays((int) config('maintenance.retention_days.server_errors')));
    }
}
