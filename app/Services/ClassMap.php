<?php

namespace App\Services;

use App\Models\QuizForm;
use App\Models\QuizResponse;
use App\Models\QuizSession;
use App\Support\ExamLayout;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Peta Kelas: every student of an exam at a glance for the proctor, while it runs. How far each
 * student is, how many questions they marked "Ragu-ragu", and whether something needs a look:
 * locked, left the exam page, lost the connection, or no new answer for a while.
 */
class ClassMap
{
    /**
     * Attempts with activity this long ago or later make up the current sitting of the exam.
     */
    public const WINDOW_HOURS = 6;

    /**
     * A page that has not been in touch for this long has lost its connection (pages check in
     * every few seconds while the exam runs).
     */
    public const OFFLINE_AFTER_SECONDS = 60;

    /**
     * No new answer saved for this long: the student may be stuck, or doing something else.
     */
    public const IDLE_AFTER_MINUTES = 5;

    /**
     * The order of the statuses on the map: what needs the proctor first.
     */
    public const STATUSES = ['locked', 'left', 'offline', 'idle', 'working', 'not_started', 'submitted'];

    public function __construct(protected ExamLayout $examLayout) {}

    /**
     * @return array{students: list<array<string, mixed>>, counts: array<string, int>, serverTime: string}
     */
    public function of(QuizForm $quizForm): array
    {
        $since = now()->subHours(self::WINDOW_HOURS);
        $totalQuestions = count(array_filter(is_array($quizForm->questions) ? $quizForm->questions : [], fn (mixed $question): bool => is_array($question) && isset($question['id'])));

        $sessions = QuizSession::query()
            ->where('quiz_form_id', $quizForm->id)
            ->where(fn ($query) => $query->where('updated_at', '>=', $since)->orWhere('last_seen_at', '>=', $since))
            ->with('user:id,name,kelas,nis')
            ->orderBy('id')
            ->get()
            // A student who took the exam again shows with their latest attempt.
            ->groupBy(fn (QuizSession $session): string => $session->user_id !== null ? 'user:'.$session->user_id : 'guest:'.$session->respondent_identifier)
            ->map(fn (Collection $attempts): QuizSession => $attempts->last());

        $answeredInResponse = $this->answeredInResponses($sessions->where('submitted_at', '!==', null)->pluck('id')->all());

        $students = $sessions
            ->map(function (QuizSession $session) use ($quizForm, $totalQuestions, $answeredInResponse): array {
                $assigned = $this->examLayout->assignedIds($quizForm, $session->layout);
                $status = $this->statusOf($quizForm, $session);

                return [
                    'id' => $session->id,
                    'name' => $session->user?->name ?: ($session->respondent_name ?: 'Tamu'),
                    'kelas' => $session->user?->kelas ?: $session->respondent_class,
                    'status' => $status,
                    'answered' => $session->submitted_at !== null
                        ? ($answeredInResponse[$session->id] ?? 0)
                        : $this->answeredCount($session->draft_answers),
                    'total' => $assigned !== null ? count($assigned) : $totalQuestions,
                    'doubts' => is_array($session->doubtful_question_ids) ? count($session->doubtful_question_ids) : 0,
                    'blurCount' => (int) $session->blur_count,
                    'remainingSeconds' => $session->started_at !== null && $session->submitted_at === null && $session->expires_at !== null
                        ? max(0, (int) now()->diffInSeconds($session->expires_at, false))
                        : null,
                    'lastSeenSeconds' => $session->last_seen_at !== null ? (int) $session->last_seen_at->diffInSeconds(now()) : null,
                    'idleMinutes' => $status === 'idle' ? (int) $this->lastAnswerAt($session)?->diffInMinutes(now()) : null,
                ];
            })
            ->sort(fn (array $a, array $b): int => array_search($a['status'], self::STATUSES, true) <=> array_search($b['status'], self::STATUSES, true)
                ?: ($a['total'] > 0 ? $a['answered'] / $a['total'] : 0) <=> ($b['total'] > 0 ? $b['answered'] / $b['total'] : 0)
                ?: strnatcasecmp($a['name'], $b['name']))
            ->values();

        return [
            'students' => $students->all(),
            'counts' => array_merge(array_fill_keys(self::STATUSES, 0), $students->countBy('status')->all()),
            'serverTime' => now()->toISOString(),
        ];
    }

    public function statusOf(QuizForm $quizForm, QuizSession $session): string
    {
        return match (true) {
            $session->submitted_at !== null => 'submitted',
            $session->started_at === null => 'not_started',
            $session->isEffectivelyLocked($quizForm) => 'locked',
            // The exam page was closed (or the student went elsewhere) and has not come back.
            $session->left_at !== null => 'left',
            ($session->last_seen_at ?? $session->started_at)->lt(now()->subSeconds(self::OFFLINE_AFTER_SECONDS)) => 'offline',
            $this->lastAnswerAt($session)?->lt(now()->subMinutes(self::IDLE_AFTER_MINUTES)) ?? false => 'idle',
            default => 'working',
        };
    }

    protected function lastAnswerAt(QuizSession $session): ?CarbonInterface
    {
        return $session->draft_saved_at ?? $session->started_at;
    }

    /**
     * @param  mixed  $answers  The saved answers of an attempt, by question.
     */
    protected function answeredCount(mixed $answers): int
    {
        return is_array($answers)
            ? count(array_filter($answers, fn (mixed $answer): bool => $answer !== null && $answer !== '' && $answer !== []))
            : 0;
    }

    /**
     * How many questions each handed-in attempt answered, by session id.
     *
     * @param  list<int>  $sessionIds
     * @return array<int, int>
     */
    protected function answeredInResponses(array $sessionIds): array
    {
        if ($sessionIds === []) {
            return [];
        }

        return QuizResponse::query()
            ->whereIn('quiz_session_id', $sessionIds)
            ->get(['quiz_session_id', 'answers'])
            ->mapWithKeys(fn (QuizResponse $response): array => [$response->quiz_session_id => $this->answeredCount($response->answers)])
            ->all();
    }
}
