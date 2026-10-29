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
        'manual_scores',
        'feedback',
        'is_timeout',
        'is_auto_submitted',
        'late_seconds',
        'submit_attempts',
        'receipt_hash',
        'ip_address',
        'user_agent',
    ];

    /**
     * Whenever the application stores answers, their receipt is sealed with them: a receipt that no
     * longer matches tells the answers were changed outside the application (in the database).
     */
    protected static function booted(): void
    {
        static::saving(function (QuizResponse $response): void {
            if ($response->isDirty('answers') || $response->receipt_hash === null) {
                $response->receipt_hash = $response->computeReceiptHash();
            }
        });
    }

    /**
     * The hash of the handed-in answers with the attempt and student they belong to.
     */
    public function computeReceiptHash(): string
    {
        $answers = is_array($this->answers) ? $this->answers : [];
        ksort($answers, SORT_STRING);

        return hash('sha256', (string) json_encode([
            (int) $this->quiz_form_id,
            $this->quiz_session_id !== null ? (int) $this->quiz_session_id : null,
            (string) ($this->user_id ?? $this->respondent_identifier),
            $answers,
        ]));
    }

    /**
     * The receipt the student gets after handing in, as "7F3A-9C21": the beginning of the hash.
     */
    public function receiptCode(): ?string
    {
        return $this->receipt_hash !== null ? strtoupper(substr($this->receipt_hash, 0, 4).'-'.substr($this->receipt_hash, 4, 4)) : null;
    }

    /**
     * Whether the answers kept are still the ones the receipt was given for (null: no receipt,
     * handed in before receipts existed).
     */
    public function hasIntactReceipt(): ?bool
    {
        return $this->receipt_hash !== null ? hash_equals($this->receipt_hash, $this->computeReceiptHash()) : null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'doubtful_question_ids' => 'array',
            'manual_scores' => 'array',
            'feedback' => 'array',
            'question_ids' => 'array',
            'late_seconds' => 'integer',
            'submit_attempts' => 'integer',
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
