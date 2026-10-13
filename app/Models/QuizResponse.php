<?php

namespace App\Models;

use Database\Factories\QuizResponseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class QuizResponse extends Model
{
    /** @use HasFactory<QuizResponseFactory> */
    use HasFactory;

    protected $fillable = [
        'quiz_form_id',
        'quiz_session_id',
        'user_id',
        'respondent_identifier',
        'respondent_name',
        'respondent_class',
        'email',
        'answers',
        'doubtful_question_ids',
        'question_ids',
        'score',
        'is_timeout',
        'is_auto_submitted',
        'late_seconds',
        'ip_address',
        'user_agent',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'doubtful_question_ids' => 'array',
            'question_ids' => 'array',
            'late_seconds' => 'integer',
            'score' => 'integer',
            'is_timeout' => 'boolean',
            'is_auto_submitted' => 'boolean',
        ];
    }

    public function quizForm(): BelongsTo
    {
        return $this->belongsTo(QuizForm::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function quizSession(): BelongsTo
    {
        return $this->belongsTo(QuizSession::class);
    }

    /**
     * Who handed this response in: an account, or a guest by their device.
     */
    public function studentKey(): string
    {
        return match (true) {
            $this->user_id !== null => 'user:'.$this->user_id,
            filled($this->respondent_identifier) => 'guest:'.$this->respondent_identifier,
            default => 'response:'.$this->id,
        };
    }

    /**
     * The attempt number of each response (1 is the first) and how many attempts its student
     * handed in, by response id.
     *
     * @param  Collection<int, QuizResponse>  $responses
     * @return array<int, array{attempt: int, attempts: int}>
     */
    public static function attemptNumbers(Collection $responses): array
    {
        $numbers = [];

        $responses
            ->sortBy('id')
            ->groupBy(fn (QuizResponse $response): string => $response->studentKey())
            ->each(function (Collection $attempts) use (&$numbers): void {
                foreach ($attempts->values() as $index => $response) {
                    $numbers[$response->id] = ['attempt' => $index + 1, 'attempts' => $attempts->count()];
                }
            });

        return $numbers;
    }
}
