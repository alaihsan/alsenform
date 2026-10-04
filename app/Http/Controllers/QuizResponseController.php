<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveQuizDraftRequest;
use App\Http\Requests\StoreQuizResponseRequest;
use App\Models\QuizForm;
use App\Models\QuizResponse;
use App\Models\QuizSession;
use App\Models\User;
use App\Support\MediaUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class QuizResponseController extends Controller
{
    public function show(Request $request, QuizForm $quizForm, MediaUrl $mediaUrl): Response|RedirectResponse
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

        // Sanitize questions: strip answer keys to prevent answer leaks
        $sanitizedQuestions = array_map(function ($q) {
            if (is_array($q)) {
                unset($q['answer']);
            }

            return $q;
        }, $mediaUrl->normalizeQuestions($quizForm->questions));

        // Server-side QuizSession management
        $timeLimitMinutes = isset($quizForm->settings['timeLimit']) && is_numeric($quizForm->settings['timeLimit']) && $quizForm->settings['timeLimit'] > 0
            ? (int) $quizForm->settings['timeLimit']
            : null;

        $quizSession = $this->findExamSession($quizForm, $user, $respondentIdentifier);

        if (! $quizSession) {
            $now = now();
            $expiresAt = $timeLimitMinutes ? (clone $now)->addMinutes($timeLimitMinutes) : null;
            $quizSession = QuizSession::create([
                'quiz_form_id' => $quizForm->id,
                'user_id' => $user?->id,
                'respondent_identifier' => $respondentIdentifier,
                'started_at' => $now,
                'expires_at' => $expiresAt,
                'is_locked' => false,
                'session_token' => Str::random(40),
            ]);
        }

        if (! $user) {
            cookie()->queue('alsen_resp_id', $respondentIdentifier, 60 * 24 * 30);
        }

        // Answers saved on the server survive an IP / address change, a browser crash or a
        // switch to another device, where answers kept only in localStorage would be lost.
        $hasServerDraft = ! empty($quizSession->draft_answers)
            && ($quizSession->submitted_at === null || $quizSession->draft_saved_at?->gt($quizSession->submitted_at));

        return Inertia::render('PublicQuiz', [
            'quizForm' => [
                'id' => $quizForm->id,
                'slug' => $quizForm->slug,
                'title' => $quizForm->title,
                'description' => $quizForm->description,
                'questions' => $sanitizedQuestions,
                'settings' => $quizForm->settings,
                'submitUrl' => route('forms.responses.store', ['quizForm' => $quizForm->slug]),
            ],
            'session' => [
                'token' => $quizSession->session_token,
                'respondent_identifier' => $quizSession->respondent_identifier,
                'started_at' => $quizSession->started_at?->toISOString(),
                'expires_at' => $quizSession->expires_at?->toISOString(),
                'server_time' => now()->toISOString(),
                'is_locked' => (bool) $quizSession->is_locked,
                'draft_answers' => $hasServerDraft ? $quizSession->draft_answers : null,
                'draft_saved_at' => $hasServerDraft ? $quizSession->draft_saved_at?->toISOString() : null,
            ],
            'accessRestricted' => false,
        ]);
    }

    public function store(StoreQuizResponseRequest $request, QuizForm $quizForm): RedirectResponse|JsonResponse
    {
        abort_unless($quizForm->published_at || $request->user()?->is($quizForm->user), 404);

        if ($quizForm->isRestrictedToCohorts() && ! $quizForm->allowsUser($request->user())) {
            abort(403, 'Anda tidak memiliki hak akses untuk mengerjakan kuis ini.');
        }

        $validated = $request->validated();
        $user = $request->user();
        $respondentIdentifier = $validated['respondent_identifier'] ?? ($user ? 'user_'.$user->id : null);
        $isTimeout = $request->boolean('is_timeout');

        // Locate the exam session of this respondent
        $session = $respondentIdentifier ? $this->findExamSession($quizForm, $user, $respondentIdentifier) : null;

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
                // On weak Wi-Fi the answers may have been saved while the response never
                // reached the browser. A retry of that same attempt is a success, not an error.
                if ($this->isRetryOfSubmittedAttempt($session, $validated['session_token'] ?? null)) {
                    return $this->submissionSucceeded($request, $quizForm);
                }

                if ($request->wantsJson()) {
                    return response()->json([
                        'message' => 'Anda sudah pernah mengisi kuis ini. Setiap peserta hanya diperbolehkan mengirim 1 kali tanggapan.',
                    ], 403);
                }

                return back()->withErrors(['error' => 'Anda sudah pernah mengisi kuis ini.']);
            }
        }

        if ($session) {
            if ($session->is_locked) {
                abort(403, 'Kuis sedang terkunci. Silakan hubungi pengawas / guru untuk membuka kunci.');
            }

            if ($session->expires_at && now()->gt($session->expires_at->addSeconds(60))) {
                $isTimeout = true;
            }
        }

        // Calculate score on the server
        $score = $this->calculateScore($quizForm->questions ?? [], $validated['answers'] ?? []);

        QuizResponse::query()->create([
            'quiz_form_id' => $quizForm->id,
            'user_id' => $user?->id,
            'respondent_identifier' => $respondentIdentifier,
            'email' => $validated['email'] ?? $user?->email,
            'answers' => $validated['answers'],
            'score' => $score,
            'is_timeout' => $isTimeout,
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);

        $session?->update([
            'submitted_at' => now(),
            'draft_answers' => null,
            'draft_saved_at' => null,
        ]);

        return $this->submissionSucceeded($request, $quizForm);
    }

    /**
     * The exam session of a student (by account) or of an anonymous respondent (by identifier).
     */
    protected function findExamSession(QuizForm $quizForm, ?User $user, string $respondentIdentifier): ?QuizSession
    {
        return QuizSession::query()
            ->where('quiz_form_id', $quizForm->id)
            ->when(
                $user,
                fn ($query) => $query->where('user_id', $user->id),
                fn ($query) => $query->whereNull('user_id')->where('respondent_identifier', $respondentIdentifier),
            )
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

        if (! empty($quizForm->settings['disableRespondentAutosave'])) {
            return response()->json(['saved' => false]);
        }

        $session->update([
            'draft_answers' => $validated['answers'],
            'draft_saved_at' => now(),
        ]);

        return response()->json([
            'saved' => true,
            'saved_at' => $session->draft_saved_at?->toISOString(),
        ]);
    }

    public function lockSession(Request $request, QuizForm $quizForm): JsonResponse
    {
        $identifier = $request->input('respondent_identifier') ?? ($request->user() ? 'user_'.$request->user()->id : null);
        if ($identifier) {
            $session = QuizSession::query()
                ->where('quiz_form_id', $quizForm->id)
                ->where('respondent_identifier', $identifier)
                ->first();

            if (! $session) {
                $session = QuizSession::create([
                    'quiz_form_id' => $quizForm->id,
                    'user_id' => $request->user()?->id,
                    'respondent_identifier' => $identifier,
                    'session_token' => Str::random(40),
                    'started_at' => now(),
                    'is_locked' => true,
                ]);
            }

            $session->recordBlurEvent($request->ip());
        }

        return response()->json(['locked' => true]);
    }

    /**
     * Compute total points earned based on answer keys stored in questions.
     */
    protected function calculateScore(array $questions, array $userAnswers): int
    {
        $totalScore = 0;

        foreach ($questions as $q) {
            $qid = $q['id'] ?? null;
            if (! $qid || ! array_key_exists($qid, $userAnswers)) {
                continue;
            }

            $correctAnswer = $q['answer'] ?? null;
            $userAnswer = $userAnswers[$qid];
            $points = isset($q['points']) ? (int) $q['points'] : 1;

            // No answer key (or an essay, which the teacher grades manually): nothing to score.
            if ($correctAnswer === null || $correctAnswer === '' || $correctAnswer === [] || ($q['type'] ?? '') === 'Paragraph') {
                continue;
            }

            $type = $q['type'] ?? '';

            if ($type === 'Multiple choice' || $type === 'Dropdown') {
                $isCorrect = false;
                if (is_numeric($correctAnswer) && isset($q['options'][(int) $correctAnswer])) {
                    $expectedOption = $q['options'][(int) $correctAnswer];
                    $isCorrect = ($userAnswer === $expectedOption || (string) $userAnswer === (string) $correctAnswer);
                } else {
                    $isCorrect = ((string) $userAnswer === (string) $correctAnswer);
                }
                if ($isCorrect) {
                    $totalScore += $points;
                }
            } elseif ($type === 'Checkboxes') {
                $userAnsArray = is_array($userAnswer) ? $userAnswer : [];
                $expectedOptions = [];
                if (is_array($correctAnswer)) {
                    foreach ($correctAnswer as $ans) {
                        if (is_numeric($ans) && isset($q['options'][(int) $ans])) {
                            $expectedOptions[] = $q['options'][(int) $ans];
                        } else {
                            $expectedOptions[] = (string) $ans;
                        }
                    }
                }
                sort($userAnsArray);
                sort($expectedOptions);
                if ($userAnsArray == $expectedOptions) {
                    $totalScore += $points;
                }
            } elseif ($type === 'Short answer') {
                if (is_scalar($userAnswer) && $this->isShortAnswerCorrect((string) $userAnswer, (string) $correctAnswer)) {
                    $totalScore += $points;
                }
            } elseif ($type === 'Multiple-choice grid') {
                if (is_array($correctAnswer) && is_array($userAnswer)) {
                    $allMatch = true;
                    foreach ($correctAnswer as $rIdx => $cIdx) {
                        if (! isset($userAnswer[$rIdx]) || (string) $userAnswer[$rIdx] !== (string) $cIdx) {
                            $allMatch = false;
                            break;
                        }
                    }
                    if ($allMatch) {
                        $totalScore += $points;
                    }
                }
            } else {
                if ((string) $userAnswer === (string) $correctAnswer) {
                    $totalScore += $points;
                }
            }
        }

        return $totalScore;
    }

    /**
     * Check a short answer against its key.
     *
     * The key may list several accepted answers separated by "|" (e.g. "Jakarta | DKI Jakarta").
     * Numbers are compared by value ("3,5" equals "3.50") and "min..max" accepts a numeric range.
     */
    protected function isShortAnswerCorrect(string $userAnswer, string $answerKey): bool
    {
        $normalize = fn (string $value): string => (string) preg_replace('/\s+/u', ' ', trim(mb_strtolower($value)));
        $number = function (string $value): ?float {
            $value = str_replace([' ', ','], ['', '.'], trim($value));

            return is_numeric($value) ? (float) $value : null;
        };

        $given = $normalize($userAnswer);
        if ($given === '') {
            return false;
        }

        $givenNumber = $number($given);

        foreach (explode('|', $answerKey) as $accepted) {
            $accepted = $normalize($accepted);
            if ($accepted === '') {
                continue;
            }

            if (preg_match('/^(-?[\d.,]+)\s*\.\.\s*(-?[\d.,]+)$/', $accepted, $range) && $givenNumber !== null) {
                $minimum = $number($range[1]);
                $maximum = $number($range[2]);
                if ($minimum !== null && $maximum !== null && $givenNumber >= min($minimum, $maximum) && $givenNumber <= max($minimum, $maximum)) {
                    return true;
                }

                continue;
            }

            $acceptedNumber = $number($accepted);
            if ($givenNumber !== null && $acceptedNumber !== null) {
                if (abs($givenNumber - $acceptedNumber) < 1e-9) {
                    return true;
                }

                continue;
            }

            if ($given === $accepted) {
                return true;
            }
        }

        return false;
    }
}
