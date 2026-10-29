<?php

namespace App\Models;

use Database\Factories\ActivityLogFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A login, logout, exam step, page that failed to load or server error, with the device, browser and
 * time it happened.
 */
class ActivityLog extends Model
{
    /** @use HasFactory<ActivityLogFactory> */
    use HasFactory;

    use MassPrunable;

    public const LOGIN = 'login';

    public const LOGOUT = 'logout';

    public const EXAM_STARTED = 'exam_started';

    public const EXAM_SUBMITTED = 'exam_submitted';

    /** A page the browser could not show, with its App\Support\PageFailure code in the meta. */
    public const PAGE_FAILED = 'page_failed';

    /** An error of the server (HTTP 500) on a request of this person, with its reference code in the meta. */
    public const SERVER_ERROR = 'server_error';

    /**
     * Labels of the events, in the order the report lists them.
     *
     * @var array<string, string>
     */
    public const EVENT_LABELS = [
        self::LOGIN => 'Masuk (login)',
        self::LOGOUT => 'Keluar (logout)',
        self::EXAM_STARTED => 'Mulai ujian',
        self::EXAM_SUBMITTED => 'Kirim jawaban',
        self::PAGE_FAILED => 'Halaman gagal dimuat',
        self::SERVER_ERROR => 'Galat server',
    ];

    /**
     * Labels of the device types detected by App\Support\UserAgent.
     *
     * @var array<string, string>
     */
    public const DEVICE_TYPE_LABELS = [
        'mobile' => 'HP',
        'tablet' => 'Tablet',
        'desktop' => 'Komputer',
        'unknown' => 'Tidak diketahui',
    ];

    public const UPDATED_AT = null;

    protected $fillable = [
        'event',
        'user_id',
        'quiz_form_id',
        'respondent_identifier',
        'respondent_email',
        'respondent_name',
        'respondent_class',
        'ip_address',
        'user_agent',
        'device_type',
        'device',
        'os',
        'browser',
        'meta',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function quizForm(): BelongsTo
    {
        return $this->belongsTo(QuizForm::class)->withTrashed();
    }

    /**
     * Activities older than the retention period (a school year by default) are removed.
     *
     * @return Builder<ActivityLog>
     */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subDays((int) config('maintenance.retention_days.activity_logs')));
    }
}
