<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveQuizDraftRequest;
use App\Http\Requests\StoreQuizResponseRequest;
use App\Models\ActivityLog;
use App\Models\QuizForm;
use App\Models\QuizResponse;
use App\Models\QuizSession;
use App\Models\UnlockRequest;
use App\Models\User;
use App\Support\ActivityRecorder;
use App\Support\ExamLayout;
use App\Support\MediaUrl;
use App\Support\QuizScoring;
use App\Support\RespondentIdentity;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class QuizResponseController extends Controller
{
    public function show(Request $request, QuizForm $quizForm, MediaUrl $mediaUrl, ExamLayout $examLayout): Response|RedirectResponse
    {
        abort_unless($quizForm->published_at || auth()->user()?->is($quizForm->user), 404);

        $user = auth()->user();

        // If quiz is restricted to cohorts
        if ($quizForm->isRestrictedToCohorts()) {
            if (! $user) {
                return redirect()->guest(route('login'))->with('error', 'Kuis ini hanya dapat diakses oleh peserta terdaftar (Cohort). Silakan masuk terlebih dahulu.');
            }

            if (! $quizForm->allowsUser($user)) {
                return Inertia::render('PublicQuiz', [
                    'quizForm' => [
                        'id' => $quizForm->id,
                        'slug' => $quizForm->slug,
                        'title' => $quizForm->title,
                        'description' => $quizForm->description,
                        'questions' => [],
                        'settings' => $quizForm->settings,
                        'submitUrl' => route('forms.responses.store', ['quizForm' => $quizForm->slug]),
                    ],
                    'accessRestricted' => true,
                    'restrictionReason' => 'Akun Anda tidak terdaftar dalam kelompok (Cohort) peserta untuk kuis ini.',
                    'allowedCohorts' => $quizForm->cohorts()->pluck('name')->all(),
                ]);
            }
        }

        // Logged-in students are identified by their account only: a respondent cookie left behind
        // on a shared lab computer must never open another student's exam session (or its draft).
        $respondentIdentifier = $user ? 'user_'.$user->id : $this->guestRespondentIdentifier($request);

        // Check single-response restriction early
        if (! empty($quizForm->settings['limitOneResponse'])) {
            $hasSubmitted = false;
            if ($user) {
                $hasSubmitted = QuizResponse::where('quiz_form_id', $quizForm->id)
                    ->where('user_id', $user->id)
                    ->exists();
            }
            if (! $hasSubmitted && $respondentIdentifier) {
                $hasSubmitted = QuizResponse::where('quiz_form_id', $quizForm->id)
                    ->where('respondent_identifier', $respondentIdentifier)
                    ->exists();
            }

            if ($hasSubmitted) {
                return Inertia::render('PublicQuiz', [
                    'quizForm' => [
                        'id' => $quizForm->id,
                        'slug' => $quizForm->slug,
                        'title' => $quizForm->title,
                        'description' => $quizForm->description,
                        'questions' => [],
                        'settings' => $quizForm->settings,
                        'submitUrl' => route('forms.responses.store', ['quizForm' => $quizForm->slug]),
                    ],
                    'accessRestricted' => true,
                    'restrictionReason' => 'Anda sudah pernah mengisi kuis ini. Setiap peserta dibatasi hanya dapat mengirim 1 kali tanggapan.',
                ]);
            }
        }

        $timeLimitMinutes = $this->timeLimitMinutes($quizForm);
        $requiresStartConfirmation = $this->requiresStartConfirmation($quizForm);

        // Server-side QuizSession management. The session is opened here, but for an exam the
        // timer only starts once the student confirms the rules (see start()). After an attempt
        // was handed in (more than one allowed), the next one is a new attempt with its own timer.
        $quizSession = $this->findExamSession($quizForm, $user, $respondentIdentifier);

        if ($quizSession?->submitted_at !== null) {
            $quizSession = null;
        }

        if (! $quizSession) {
            $quizSession = $this->openExamSession($quizForm, $user, $respondentIdentifier, $requiresStartConfirmation, $timeLimitMinutes);

            // A survey (no rules to confirm) starts as soon as it is opened.
            if ($quizSession->started_at !== null) {
                $this->recordExamActivity(ActivityLog::EXAM_STARTED, $request, $quizForm, $quizSession);
            }
        }

        // Back after the page was closed or reloaded: away too long locks the attempt. Otherwise the
        // page tells on its first status check whether it is a reload of the same tab.
        $quizSession->settleLeave($quizForm, $request->ip());

        // Until the exam has started the questions stay on the server, so they cannot be read
        // (from the page source) before the timer runs.
        $hasStarted = $quizSession->started_at !== null;

        // The questions of this student (all, or a draw) in their order, with their choices shuffled.
        $layout = $examLayout->ensure($quizForm, $quizSession);
        $sanitizedQuestions = $this->sanitizedQuestions($quizForm, $mediaUrl, $layout);

        if (! $user) {
            cookie()->queue('alsen_resp_id', $respondentIdentifier, 60 * 24 * 30);
        }

        // Answers saved on the server survive an IP / address change, a browser crash or a
        // switch to another device, where answers kept only in localStorage would be lost.
        $hasServerDraft = ! empty($quizSession->draft_answers) && $quizSession->submitted_at === null;

        return Inertia::render('PublicQuiz', [
            'quizForm' => [
                'id' => $quizForm->id,
                'slug' => $quizForm->slug,
                'title' => $quizForm->title,
                'description' => $quizForm->description,
                'questions' => $hasStarted ? $sanitizedQuestions : [],
                'settings' => $quizForm->settings,
                'submitUrl' => route('forms.responses.store', ['quizForm' => $quizForm->slug]),
                'startUrl' => route('forms.responses.start', ['quizForm' => $quizForm->slug]),
                // Guests say who they are on the confirmation screen; accounts identify themselves.
                'identityFields' => $user ? [] : app(RespondentIdentity::class)->fields($quizForm),
                'respondentClasses' => RespondentIdentity::CLASSES,
            ],
            'examSummary' => [
                'questionCount' => count($sanitizedQuestions),
                'requiredCount' => count(array_filter($sanitizedQuestions, fn ($question) => ! empty($question['required']))),
                'totalPoints' => array_sum(array_map(fn ($question) => is_numeric($question['points'] ?? null) ? (float) $question['points'] : 0, $sanitizedQuestions)),
                'timeLimitMinutes' => $timeLimitMinutes,
            ],
            'session' => [
                'token' => $quizSession->session_token,
                'respondent_identifier' => $quizSession->respondent_identifier,
                'respondent_name' => $quizSession->respondent_name,
                'respondent_class' => $quizSession->respondent_class,
                'respondent_email' => $quizSession->respondent_email,
                'started_at' => $quizSession->started_at?->toISOString(),
                'expires_at' => $quizSession->expires_at?->toISOString(),
                'server_time' => now()->toISOString(),
                'is_locked' => $quizSession->isEffectivelyLocked($quizForm),
                'draft_answers' => $hasServerDraft ? $quizSession->draft_answers : null,
                'draft_saved_at' => $hasServerDraft ? $quizSession->draft_saved_at?->toISOString() : null,
            ],
            'accessRestricted' => false,
        ]);
    }

    /**
     * Start the exam once the student has read the rules and pressed "Kerjakan Sekarang":
     * the timer begins now and the questions are handed out from this moment on.
     * Repeating the request (a retry on weak Wi-Fi) keeps the original start time.
     */
    public function start(Request $request, QuizForm $quizForm, MediaUrl $mediaUrl, ExamLayout $examLayout): JsonResponse
    {
        abort_unless($quizForm->published_at || $request->user()?->is($quizForm->user), 404);

        if ($quizForm->isRestrictedToCohorts() && ! $quizForm->allowsUser($request->user())) {
            abort(403, 'Anda tidak memiliki hak akses untuk mengerjakan kuis ini.');
        }

        $validated = $request->validate([
            'session_token' => ['required', 'string', 'max:100'],
        ]);

        $session = QuizSession::query()
            ->where('quiz_form_id', $quizForm->id)
            ->where('session_token', $validated['session_token'])
            ->first();

        abort_if($session === null, 404);
        abort_if($session->user_id !== null && $session->user_id !== $request->user()?->id, 403);

        if ($session->submitted_at !== null) {
            return response()->json([
                'message' => 'Jawaban ujian ini sudah dikirim.',
                'submitted' => true,
            ], 409);
        }

        if ($session->started_at === null) {
            // The questions are fixed now, with the settings of the moment the student starts.
            $examLayout->ensure($quizForm, $session);

            // Guests give their name, class and email before the timer starts; they cannot be
            // changed afterwards. (A retry of a started exam keeps what was given.)
            $identity = app(RespondentIdentity::class);
            $identityInput = $request->validate($identity->rules($quizForm, $request->user(), $session), $identity->messages());
            $timeLimitMinutes = $this->timeLimitMinutes($quizForm);
            $now = now();

            $started = QuizSession::query()
                ->whereKey($session->id)
                ->whereNull('started_at')
                ->update([
                    'started_at' => $now,
                    'expires_at' => $timeLimitMinutes ? (clone $now)->addMinutes($timeLimitMinutes) : null,
                    ...($request->user() ? [] : $identity->fromInput($quizForm, $identityInput, $session)),
                ]);

            $session->refresh();

            // Only the request that actually started the timer is recorded, not its retries.
            if ($started > 0) {
                $this->recordExamActivity(ActivityLog::EXAM_STARTED, $request, $quizForm, $session);
            }
        }

        return response()->json([
            'questions' => $this->sanitizedQuestions($quizForm, $mediaUrl, $examLayout->ensure($quizForm, $session)),
            'session' => [
                'started_at' => $session->started_at?->toISOString(),
                'expires_at' => $session->expires_at?->toISOString(),
                'server_time' => now()->toISOString(),
                'respondent_name' => $session->respondent_name,
                'respondent_class' => $session->respondent_class,
                'respondent_email' => $session->respondent_email,
            ],
        ]);
    }

    public function store(StoreQuizResponseRequest $request, QuizForm $quizForm, ExamLayout $examLayout): RedirectResponse|JsonResponse
    {
        abort_unless($quizForm->published_at || $request->user()?->is($quizForm->user), 404);

        if ($quizForm->isRestrictedToCohorts() && ! $quizForm->allowsUser($request->user())) {
            abort(403, 'Anda tidak memiliki hak akses untuk mengerjakan kuis ini.');
        }

        $validated = $request->validated();
        $user = $request->user();
        $isTimeout = $request->boolean('is_timeout');
        $isExam = $this->requiresStartConfirmation($quizForm);

        $session = $this->attemptOfSubmission($request, $quizForm, $validated['session_token'] ?? null);
        // The token proves the attempt is theirs, also when the login expired during a long exam.
        $user ??= $session?->user_id !== null ? $session->user : null;
        $respondentIdentifier = $session?->respondent_identifier
            ?? ($user ? 'user_'.$user->id : $this->guestIdentifierOf($request, $validated['respondent_identifier'] ?? null));

        // An exam is handed in from an attempt that was started (with its timer) on the exam page,
        // never by sending answers directly.
        if ($isExam && ($session === null || $session->started_at === null)) {
            abort(403, 'Ujian belum dimulai dari halaman ujian. Muat ulang halaman, lalu tekan "Kerjakan Sekarang".');
        }

        if ($session !== null && $session->submitted_at !== null) {
            $isSameAttempt = $this->isRetryOfSubmittedAttempt($session, $validated['session_token'] ?? null);
            $autoSubmitted = $isSameAttempt ? $this->autoSubmittedResponse($session) : null;

            if ($autoSubmitted !== null) {
                $this->completeAutoSubmitted($request, $quizForm, $session, $autoSubmitted, $validated['answers'], $examLayout);

                return $this->submissionSucceeded($request, $quizForm);
            }

            // On weak Wi-Fi the answers may have been saved while the response never reached the
            // browser. A retry of that same attempt is a success, not a second response.
            if ($isSameAttempt) {
                return $this->submissionSucceeded($request, $quizForm);
            }

            // (One response per student is answered below with its own message.)
            if ($isExam && empty($quizForm->settings['limitOneResponse'])) {
                abort(403, 'Jawaban ujian ini sudah dikirim. Muat ulang halaman untuk melihat statusnya.');
            }
        }

        // Check single response restriction
        if (! empty($quizForm->settings['limitOneResponse'])) {
            $hasSubmitted = false;
            if ($user) {
                $hasSubmitted = QuizResponse::where('quiz_form_id', $quizForm->id)
                    ->where('user_id', $user->id)
                    ->exists();
            }
            if (! $hasSubmitted && $respondentIdentifier) {
                $hasSubmitted = QuizResponse::where('quiz_form_id', $quizForm->id)
                    ->where('respondent_identifier', $respondentIdentifier)
                    ->exists();
            }

            if ($hasSubmitted) {
                if ($request->wantsJson()) {
                    return response()->json([
                        'message' => 'Anda sudah pernah mengisi kuis ini. Setiap peserta hanya diperbolehkan mengirim 1 kali tanggapan.',
                    ], 403);
                }

                return back()->withErrors(['error' => 'Anda sudah pernah mengisi kuis ini.']);
            }
        }

        $lateSeconds = null;

        if ($session) {
            if ($session->expires_at && now()->gt($session->expires_at->copy()->addSeconds(60))) {
                $isTimeout = true;
            }

            // A locked attempt waits for the teacher, except when its time is up: the answers
            // given so far are then stored instead of being lost.
            if ($session->isEffectivelyLocked($quizForm) && ! $this->timeIsUp($session)) {
                abort(403, 'Kuis sedang terkunci. Silakan hubungi pengawas / guru untuk membuka kunci.');
            }

            $lateSeconds = $this->secondsLate($session);
        }

        $wasLocked = $session?->isEffectivelyLocked($quizForm) ?? false;
        $assignedIds = $session ? $examLayout->assignedIds($quizForm, $session->layout) : null;

        // Which answers count:
        // - a locked attempt counts the answers it had when it was locked (changes made behind the
        //   lock screen are ignored);
        // - answers arriving well after the time ran out from a page that was online until the end
        //   (a stopped timer) count as saved when the time ran out; a page that was offline at the
        //   end sends what it had, marked late for the teacher.
        $countsSavedAnswers = $session !== null && is_array($session->draft_answers)
            && ($wasLocked || ($lateSeconds !== null && $this->wasOnlineAtTheEnd($session)));

        $answers = $countsSavedAnswers
            ? $session->savedAnswersFor($quizForm, $assignedIds)
            : $this->onlyAssigned($validated['answers'], $assignedIds);

        // Calculate score on the server
        $score = app(QuizScoring::class)->score($quizForm->questions ?? [], $answers);

        // A guest's identity: what the exam session locked at the start, or (a survey that opens
        // without a confirmation screen) what is sent now.
        $identity = $user
            ? ['respondent_name' => null, 'respondent_class' => null, 'respondent_email' => null]
            : app(RespondentIdentity::class)->fromInput($quizForm, $validated, $session);

        QuizResponse::query()->create([
            'quiz_form_id' => $quizForm->id,
            'quiz_session_id' => $session?->id,
            'user_id' => $user?->id,
            'respondent_identifier' => $respondentIdentifier,
            'respondent_name' => $identity['respondent_name'],
            'respondent_class' => $identity['respondent_class'],
            'email' => $identity['respondent_email'] ?? $validated['email'] ?? $user?->email,
            'answers' => $answers,
            'question_ids' => $assignedIds,
            'score' => $score,
            'is_timeout' => $isTimeout,
            'late_seconds' => $countsSavedAnswers ? null : $lateSeconds,
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);

        $session?->update([
            'submitted_at' => now(),
            'draft_answers' => null,
            ...$identity,
        ]);

        app(ActivityRecorder::class)->record(
            ActivityLog::EXAM_SUBMITTED,
            $request,
            $user,
            $quizForm,
            respondentIdentifier: $respondentIdentifier,
            respondentEmail: $identity['respondent_email'] ?? $validated['email'] ?? null,
            respondentName: $identity['respondent_name'],
            respondentClass: $identity['respondent_class'],
            meta: array_filter(['timeout' => $isTimeout, 'locked' => $wasLocked]),
        );

        return $this->submissionSucceeded($request, $quizForm);
    }

    /**
     * Record an exam step of the respondent of the given session in the activity report.
     */
    protected function recordExamActivity(string $event, Request $request, QuizForm $quizForm, QuizSession $session): void
    {
        app(ActivityRecorder::class)->record(
            $event,
            $request,
            $session->user_id !== null ? $request->user() : null,
            $quizForm,
            respondentIdentifier: $session->respondent_identifier,
            respondentEmail: $session->respondent_email,
            respondentName: $session->respondent_name,
            respondentClass: $session->respondent_class,
        );
    }

    /**
     * The exam session of a student (by account) or of an anonymous respondent (by identifier).
     */
    /**
     * Open a new exam session. An exam is created "not started" (no started_at) until the
     * student confirms the rules. If the database was not migrated after an update (started_at
     * still required), the exam starts right away as it used to, instead of failing with a 500.
     */
    protected function openExamSession(QuizForm $quizForm, ?User $user, string $respondentIdentifier, bool $deferStart, ?int $timeLimitMinutes): QuizSession
    {
        $attributes = [
            'quiz_form_id' => $quizForm->id,
            'user_id' => $user?->id,
            'respondent_identifier' => $respondentIdentifier,
            'is_locked' => false,
            'session_token' => Str::random(40),
        ];

        $startNow = function () use ($attributes, $timeLimitMinutes): QuizSession {
            $now = now();

            return QuizSession::create($attributes + [
                'started_at' => $now,
                'expires_at' => $timeLimitMinutes ? (clone $now)->addMinutes($timeLimitMinutes) : null,
            ]);
        };

        if (! $deferStart) {
            return $startNow();
        }

        try {
            // The transaction (a savepoint when nested) keeps the connection usable if the insert is rejected.
            return DB::transaction(fn () => QuizSession::create($attributes + ['started_at' => null, 'expires_at' => null]));
        } catch (QueryException $exception) {
            if (! str_contains($exception->getMessage(), 'started_at')) {
                throw $exception;
            }

            Log::warning('Database belum dimigrasi: jalankan "php artisan migrate --force". Sampai itu, timer ujian mulai saat halaman dibuka.');

            return $startNow();
        }
    }

    /**
     * The questions as sent to respondents: answer keys removed, media URLs host independent.
     *
     * @return list<array<string, mixed>>
     */
    protected function sanitizedQuestions(QuizForm $quizForm, MediaUrl $mediaUrl, ?array $layout = null): array
    {
        return array_values(array_map(function ($question) {
            if (is_array($question)) {
                unset($question['answer']);
            }

            return $question;
        }, $mediaUrl->normalizeQuestions(app(ExamLayout::class)->arrange($quizForm, $layout)) ?? []));
    }

    /**
     * The time limit of the quiz in minutes, or null when it has none.
     */
    protected function timeLimitMinutes(QuizForm $quizForm): ?int
    {
        $timeLimit = $quizForm->settings['timeLimit'] ?? null;

        return is_numeric($timeLimit) && $timeLimit > 0 ? (int) $timeLimit : null;
    }

    /**
     * Exams (quizzes and timed forms) start only after the student confirms the rules;
     * a plain survey opens directly.
     */
    protected function requiresStartConfirmation(QuizForm $quizForm): bool
    {
        return ($quizForm->settings['isQuiz'] ?? true) !== false || $this->timeLimitMinutes($quizForm) !== null;
    }

    protected function findExamSession(QuizForm $quizForm, ?User $user, string $respondentIdentifier): ?QuizSession
    {
        return QuizSession::query()
            ->where('quiz_form_id', $quizForm->id)
            ->when(
                $user,
                fn ($query) => $query->where('user_id', $user->id),
                fn ($query) => $query->whereNull('user_id')->where('respondent_identifier', $respondentIdentifier),
            )
            ->latest('id')
            ->first();
    }

    /**
     * The identifier of an anonymous respondent, remembered in a cookie on this device.
     */
    protected function guestRespondentIdentifier(Request $request): string
    {
        $identifier = $request->cookie('alsen_resp_id');

        return is_string($identifier) && str_starts_with($identifier, 'anon_')
            ? $identifier
            : 'anon_'.Str::random(16);
    }

    /**
     * Determine if the request repeats an attempt whose answers were already stored.
     */
    protected function isRetryOfSubmittedAttempt(?QuizSession $session, ?string $sessionToken): bool
    {
        return $session !== null
            && $session->submitted_at !== null
            && is_string($sessionToken)
            && $sessionToken !== ''
            && hash_equals((string) $session->session_token, $sessionToken);
    }

    /**
     * Seconds after the time ran out (plus some room for a slow connection) that answers arrive,
     * or null when they are on time.
     */
    public const LATE_AFTER_SECONDS = 120;

    /**
     * The attempt answers are handed in for: by its token (the exam page always sends it), else
     * the signed-in account's latest attempt, else (an older page) the guest's by their cookie.
     * A guest cannot name another respondent's attempt.
     */
    protected function attemptOfSubmission(Request $request, QuizForm $quizForm, ?string $token): ?QuizSession
    {
        if (is_string($token) && $token !== '') {
            $session = QuizSession::query()
                ->where('quiz_form_id', $quizForm->id)
                ->where('session_token', $token)
                ->first();

            if ($session !== null) {
                abort_if($session->user_id !== null && $request->user() !== null && $session->user_id !== $request->user()->id, 403);

                return $session;
            }
        }

        $user = $request->user();
        $identifier = $user ? 'user_'.$user->id : $this->guestIdentifierOf($request, $request->input('respondent_identifier'));

        return is_string($identifier) && $identifier !== '' ? $this->findExamSession($quizForm, $user, $identifier) : null;
    }

    /**
     * A guest is the respondent of their (encrypted, unforgeable) cookie; the identifier a page
     * sends only counts without that cookie (a survey opened with cookies blocked).
     */
    protected function guestIdentifierOf(Request $request, mixed $sentIdentifier): ?string
    {
        $cookie = $request->cookie('alsen_resp_id');

        if (is_string($cookie) && str_starts_with($cookie, 'anon_')) {
            return $cookie;
        }

        return is_string($sentIdentifier) && $sentIdentifier !== '' ? $sentIdentifier : null;
    }

    /**
     * Seconds the answers arrive after the time ran out, or null when they are on time.
     */
    protected function secondsLate(QuizSession $session): ?int
    {
        if ($session->expires_at === null || now()->lte($session->expires_at->copy()->addSeconds(self::LATE_AFTER_SECONDS))) {
            return null;
        }

        return (int) $session->expires_at->diffInSeconds(now(), true);
    }

    /**
     * Whether the exam page was still in touch with the server when the time ran out. Such a page
     * hands in by itself; answers it sends long afterwards were given after the time.
     */
    protected function wasOnlineAtTheEnd(QuizSession $session): bool
    {
        return $session->expires_at !== null
            && $session->last_seen_at !== null
            && $session->last_seen_at->gte($session->expires_at->copy()->subSeconds(45));
    }

    /**
     * Only the answers to the questions the student was given.
     *
     * @param  array<int|string, mixed>  $answers
     * @param  list<string>|null  $assignedIds
     * @return array<int|string, mixed>
     */
    protected function onlyAssigned(array $answers, ?array $assignedIds): array
    {
        if ($assignedIds === null) {
            return $answers;
        }

        return array_filter($answers, fn (int|string $questionId): bool => in_array((string) $questionId, $assignedIds, true), ARRAY_FILTER_USE_KEY);
    }

    /**
     * The server handed this attempt in when its time ran out, while the page was away. A page that
     * was offline at the end sends what the student had on the device then: those answers fill in
     * the saved ones and are marked late. A page that stayed online until the end (it should have
     * handed in itself), or a locked attempt, keeps what was saved.
     *
     * @param  array<int|string, mixed>  $deviceAnswers
     */
    protected function completeAutoSubmitted(Request $request, QuizForm $quizForm, QuizSession $session, QuizResponse $autoSubmitted, array $deviceAnswers, ExamLayout $examLayout): void
    {
        if (($session->is_locked && $quizForm->locksOnBlur()) || $this->wasOnlineAtTheEnd($session)) {
            return;
        }

        $answers = array_replace(
            is_array($autoSubmitted->answers) ? $autoSubmitted->answers : [],
            array_filter(
                $this->onlyAssigned($deviceAnswers, $examLayout->assignedIds($quizForm, $session->layout)),
                fn (mixed $answer): bool => $answer !== null && $answer !== '' && $answer !== [],
            ),
        );

        $autoSubmitted->update([
            'answers' => $answers,
            'score' => app(QuizScoring::class)->score($quizForm->questions ?? [], $answers),
            'is_auto_submitted' => false,
            'late_seconds' => $this->secondsLate($session),
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);
    }

    /**
     * The response the server handed in for this attempt when its time ran out. Null as well when
     * the database was not migrated yet (no is_auto_submitted column): then nothing was handed in.
     */
    protected function autoSubmittedResponse(QuizSession $session): ?QuizResponse
    {
        return rescue(
            fn (): ?QuizResponse => QuizResponse::query()
                ->where('quiz_session_id', $session->id)
                ->where('is_auto_submitted', true)
                ->first(),
            report: false,
        );
    }

    /**
     * The response returned after the answers have been stored.
     */
    protected function submissionSucceeded(Request $request, QuizForm $quizForm): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Tanggapan kuis berhasil disimpan.',
            ]);
        }

        return to_route('forms.public', ['quizForm' => $quizForm->slug]);
    }

    /**
     * Store the in-progress answers of an exam session on the server.
     */
    public function saveDraft(SaveQuizDraftRequest $request, QuizForm $quizForm): JsonResponse
    {
        abort_unless($quizForm->published_at || $request->user()?->is($quizForm->user), 404);

        $validated = $request->validated();

        $session = QuizSession::query()
            ->where('quiz_form_id', $quizForm->id)
            ->where('session_token', $validated['session_token'])
            ->first();

        // The session token is only handed to its owner. A guest is still allowed so the draft
        // keeps being saved when the login session expires in the middle of a long exam.
        abort_if($session === null, 404);
        abort_if($session->user_id !== null && $request->user() !== null && $session->user_id !== $request->user()->id, 403);

        // The attempt is sealed once submitted: a late draft request must not resurrect old answers.
        if ($session->submitted_at !== null) {
            return response()->json(['saved' => false, 'submitted' => true], 409);
        }

        // While locked the answers stay as they were at the lock (see lockSession()).
        if ($session->isEffectivelyLocked($quizForm)) {
            return response()->json(['saved' => false, 'locked' => true]);
        }

        // After the time ran out (and a minute for a slow connection) nothing more is saved.
        if ($session->expires_at !== null && now()->gt($session->expires_at->copy()->addSeconds(60))) {
            return response()->json(['saved' => false, 'expired' => true], 409);
        }

        $session->markSeen();

        if (! empty($quizForm->settings['disableRespondentAutosave'])) {
            return response()->json(['saved' => false]);
        }

        $session->update([
            'draft_answers' => $this->onlyAssigned($validated['answers'], app(ExamLayout::class)->assignedIds($quizForm, $session->layout)),
            'draft_saved_at' => now(),
        ]);

        return response()->json([
            'saved' => true,
            'saved_at' => $session->draft_saved_at?->toISOString(),
        ]);
    }

    /**
     * Lock the attempt after the student left the exam tab or window. Nothing is locked when
     * the teacher has switched the lock off, before the exam starts or after it is handed in.
     */
    public function lockSession(Request $request, QuizForm $quizForm): JsonResponse
    {
        $session = $this->sessionOfRequest($request, $quizForm);

        if (! $quizForm->locksOnBlur()) {
            return $this->lockState($quizForm, $session);
        }

        if ($session === null && $request->user() !== null) {
            $session = QuizSession::create([
                'quiz_form_id' => $quizForm->id,
                'user_id' => $request->user()->id,
                'respondent_identifier' => 'user_'.$request->user()->id,
                'session_token' => Str::random(40),
                'started_at' => now(),
            ]);
        }

        // The page is being closed or reloaded: only noted; it counts once the page is away too long.
        if ($request->input('reason') === 'unload') {
            if ($session !== null && $session->started_at !== null && $session->submitted_at === null && ! $session->is_locked) {
                $session->forceFill(['left_at' => now()])->saveQuietly();
            }

            return $this->lockState($quizForm, $session);
        }

        if ($session !== null && $session->started_at !== null && $session->submitted_at === null) {
            $wasLocked = $session->isEffectivelyLocked($quizForm);
            $session->recordBlurEvent($request->ip());

            // The answers on the page when the student left are kept: they are the ones that count
            // if the time runs out before the teacher unlocks. Not replaced by a repeated lock.
            $snapshot = $request->input('answers');
            if (! $wasLocked && is_array($snapshot) && SaveQuizDraftRequest::answersProblem($quizForm, $snapshot) === null) {
                $session->update([
                    'draft_answers' => $this->onlyAssigned($snapshot, app(ExamLayout::class)->assignedIds($quizForm, $session->layout)),
                    'draft_saved_at' => now(),
                ]);
            }

            $session->markSeen();
        }

        return $this->lockState($quizForm, $session);
    }

    /**
     * The live lock state of an attempt, polled by the exam page: whether the teacher has the
     * lock switched on (it can change mid-exam) and whether the attempt is locked right now.
     */
    public function examStatus(Request $request, QuizForm $quizForm): JsonResponse
    {
        $request->validate([
            'session_token' => ['required', 'string', 'max:100'],
            'returning' => ['nullable', 'string', 'max:20'],
        ]);

        $session = $this->sessionOfRequest($request, $quizForm);

        abort_if($session === null, 404);

        $session->settleLeave($quizForm, $request->ip(), $request->input('returning'));
        $session->markSeen();

        return $this->lockState($quizForm, $session);
    }

    /**
     * The exam session a lock or status request is about: by its token, or (older pages) by the
     * signed-in account or the respondent identifier of a guest.
     */
    protected function sessionOfRequest(Request $request, QuizForm $quizForm): ?QuizSession
    {
        $token = $request->input('session_token');

        if (is_string($token) && $token !== '') {
            $session = QuizSession::query()
                ->where('quiz_form_id', $quizForm->id)
                ->where('session_token', $token)
                ->first();

            abort_if($session?->user_id !== null && $request->user() !== null && $session->user_id !== $request->user()->id, 403);

            return $session;
        }

        $user = $request->user();
        $identifier = $user ? 'user_'.$user->id : $request->input('respondent_identifier');

        return is_string($identifier) && $identifier !== ''
            ? $this->findExamSession($quizForm, $user, $identifier)
            : null;
    }

    /**
     * The lock state returned to the exam page.
     */
    protected function lockState(QuizForm $quizForm, ?QuizSession $session): JsonResponse
    {
        $unlockRequested = $session !== null && $session->isEffectivelyLocked($quizForm)
            && UnlockRequest::query()
                ->whereBelongsTo($quizForm)
                ->where('respondent_identifier', $session->respondent_identifier)
                ->where('status', 'pending')
                ->exists();

        return response()->json([
            'lock_on_blur' => $quizForm->locksOnBlur(),
            'locked' => $session?->isEffectivelyLocked($quizForm) ?? false,
            'unlock_requested' => $unlockRequested,
            'submitted' => $session?->submitted_at !== null,
        ]);
    }

    /**
     * Determine if the time limit of the attempt has run out. A little early is accepted: the
     * page submits when its own clock reaches the end, which may run slightly ahead.
     */
    protected function timeIsUp(QuizSession $session): bool
    {
        return $session->expires_at !== null && now()->gte($session->expires_at->copy()->subSeconds(30));
    }
}
