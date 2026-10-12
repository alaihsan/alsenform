<?php

namespace App\Models;

use Database\Factories\ActivityLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A login, logout or exam step with the device, browser and time it happened.
 */
class ActivityLog extends Model
{
    /** @use HasFactory<ActivityLogFactory> */
    use HasFactory;

    public const LOGIN = 'login';

    public const LOGOUT = 'logout';

    public const EXAM_STARTED = 'exam_started';

    public const EXAM_SUBMITTED = 'exam_submitted';

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
}
