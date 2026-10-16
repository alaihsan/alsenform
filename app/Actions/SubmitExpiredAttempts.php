<?php

namespace App\Actions;

use App\Models\ActivityLog;
use App\Models\QuizForm;
use App\Models\QuizResponse;
use App\Models\QuizSession;
use App\Support\ActivityRecorder;
use App\Support\ExamLayout;
use App\Support\QuizScoring;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Hands in the exams whose time ran out while the page was closed, offline or out of battery:
 * the answers saved on the server so far are graded like an answer sheet handed in at the bell.
 * An open exam page hands in by itself when the time runs out; the server waits a moment for it.
 */
class SubmitExpiredAttempts
{
    /**
     * Seconds the exam page gets to hand in by itself after the time ran out.
     */
    public const GRACE_SECONDS = 60;

    public function __construct(
        protected QuizScoring $scoring = new QuizScoring,
        protected ActivityRecorder $activityRecorder = new ActivityRecorder,
        protected ExamLayout $examLayout = new ExamLayout,
    ) {}

    /**
     * Hand in every expired attempt, or those of one form.
     *
     * @return int The number of attempts handed in.
     */
    public function handle(?QuizForm $quizForm = null): int
    {
        $submitted = 0;

        $this->expiredAttempts($quizForm)
            ->with(['quizForm', 'user:id,email'])
            ->chunkById(100, function (Collection $sessions) use (&$submitted): void {
                foreach ($sessions as $session) {
                    if ($this->submit($session)) {
                        $submitted++;
                    }
                }
            });

        return $submitted;
    }

    /**
     * Like handle(), for the pages that show results: a failure is reported, the page still opens.
     */
    public function handleQuietly(?QuizForm $quizForm = null): int
    {
        try {
            return $this->handle($quizForm);
        } catch (Throwable $exception) {
            report($exception);

            return 0;
        }
    }

    /**
     * Started attempts whose time ran out (plus the grace period) without the answers handed
     * in. A teacher trying out their own exam is not graded.
     *
     * @return Builder<QuizSession>
     */
    public function expiredAttempts(?QuizForm $quizForm = null): Builder
    {
        return QuizSession::query()
            ->whereNull('submitted_at')
            ->whereNotNull('started_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->subSeconds(self::GRACE_SECONDS))
            ->when($quizForm, fn (Builder $query) => $query->where('quiz_form_id', $quizForm->id))
            ->whereHas('quizForm', fn (Builder $query) => $query->where(fn (Builder $owner) => $owner
                ->whereNull('quiz_sessions.user_id')
                ->orWhereColumn('quiz_forms.user_id', '<>', 'quiz_sessions.user_id')));
    }

    /**
     * Hand in one attempt with the answers saved on the server.
     */
    public function submit(QuizSession $session): bool
    {
        $quizForm = $session->quizForm;

        return DB::transaction(function () use ($session, $quizForm): bool {
            // Locked, so the exam page handing in at the same moment does not add a second response.
            $attempt = QuizSession::query()->whereKey($session->id)->lockForUpdate()->first();

            if ($attempt === null || $attempt->submitted_at !== null) {
                return false;
            }

            // The answers already reached the server another way (an older version of the page).
            if ($this->hasResponse($attempt)) {
                $attempt->update(['submitted_at' => now()]);

                return false;
            }

            $wasLocked = $attempt->isEffectivelyLocked($quizForm);
            $questions = is_array($quizForm->questions) ? $quizForm->questions : [];
            $assignedIds = $this->examLayout->assignedIds($quizForm, $attempt->layout);
            // A form that sends students past sections keeps only what is on their way.
            ['answers' => $answers, 'questionIds' => $givenIds] = $quizForm->keepOnPath($attempt->savedAnswersFor($quizForm, $assignedIds), $assignedIds);
            $isAccount = $attempt->user_id !== null;

            $response = new QuizResponse([
                'quiz_form_id' => $quizForm->id,
                'quiz_session_id' => $attempt->id,
                'user_id' => $attempt->user_id,
                'respondent_identifier' => $attempt->respondent_identifier,
                'respondent_name' => $isAccount ? null : $attempt->respondent_name,
                'respondent_class' => $isAccount ? null : $attempt->respondent_class,
                'email' => $attempt->respondent_email ?? $session->user?->email,
                'answers' => $answers,
                'doubtful_question_ids' => $quizForm->onlyGiven($attempt->doubtful_question_ids, $givenIds),
                'question_ids' => $givenIds,
                'score' => $this->scoring->score($questions, $answers),
                'is_timeout' => true,
                'is_auto_submitted' => true,
            ]);
            // Handed in at the moment the time ran out.
            $response->created_at = $attempt->expires_at;
            $response->save();

            // draft_saved_at stays: it tells later whether the page was in touch until the end.
            $attempt->update([
                'submitted_at' => now(),
                'draft_answers' => null,
            ]);

            $startedOn = $this->startActivityOf($attempt);

            $this->activityRecorder->record(
                ActivityLog::EXAM_SUBMITTED,
                new Request,
                $isAccount ? $session->user : null,
                $quizForm,
                respondentIdentifier: $attempt->respondent_identifier,
                respondentEmail: $attempt->respondent_email,
                respondentName: $attempt->respondent_name,
                respondentClass: $attempt->respondent_class,
                meta: array_filter(['timeout' => true, 'auto' => true, 'locked' => $wasLocked]),
                occurredAt: $attempt->expires_at,
                sameDeviceAs: $startedOn,
            );

            return true;
        });
    }

    /**
     * The "Mulai ujian" activity of the attempt: the Laporan lists the exam handed in by the server
     * on the device it was taken on, exactly as recorded then, rather than on an unknown one.
     */
    protected function startActivityOf(QuizSession $attempt): ?ActivityLog
    {
        return rescue(fn (): ?ActivityLog => ActivityLog::query()
            ->where('event', ActivityLog::EXAM_STARTED)
            ->where('quiz_form_id', $attempt->quiz_form_id)
            ->when(
                $attempt->user_id !== null,
                fn (Builder $query) => $query->where('user_id', $attempt->user_id),
                fn (Builder $query) => $query->whereNull('user_id')->where('respondent_identifier', $attempt->respondent_identifier),
            )
            ->latest('id')
            ->first(['ip_address', 'user_agent', 'device_type', 'device', 'os', 'browser']), report: false);
    }

    /**
     * Determine if a response of this attempt was stored without closing the attempt.
     */
    protected function hasResponse(QuizSession $attempt): bool
    {
        return QuizResponse::query()
            ->where('quiz_form_id', $attempt->quiz_form_id)
            ->where(fn (Builder $query) => $query
                ->where('quiz_session_id', $attempt->id)
                ->orWhere(fn (Builder $sameRespondent) => $attempt->user_id !== null
                    ? $sameRespondent->where('user_id', $attempt->user_id)
                    : $sameRespondent->whereNull('user_id')->where('respondent_identifier', $attempt->respondent_identifier)))
            ->where('created_at', '>=', $attempt->started_at)
            ->exists();
    }
}
