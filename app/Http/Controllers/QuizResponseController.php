<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreQuizResponseRequest;
use App\Models\QuizForm;
use App\Models\QuizResponse;
use App\Models\QuizSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class QuizResponseController extends Controller
{
    public function show(Request $request, QuizForm $quizForm): Response|RedirectResponse
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

        $respondentIdentifier = $request->cookie('alsen_resp_id') ?? ($user ? 'user_'.$user->id : 'anon_'.Str::random(16));

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
        }, $quizForm->questions ?? []);

        // Server-side QuizSession management
        $timeLimitMinutes = isset($quizForm->settings['timeLimit']) && is_numeric($quizForm->settings['timeLimit']) && $quizForm->settings['timeLimit'] > 0
            ? (int) $quizForm->settings['timeLimit']
            : null;

        $quizSession = QuizSession::query()
            ->where('quiz_form_id', $quizForm->id)
            ->where(function ($q) use ($user, $respondentIdentifier) {
                if ($user) {
                    $q->where('user_id', $user->id)->orWhere('respondent_identifier', $respondentIdentifier);
                } else {
                    $q->where('respondent_identifier', $respondentIdentifier);
                }
            })
            ->first();

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

        cookie()->queue('alsen_resp_id', $respondentIdentifier, 60 * 24 * 30);

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

        // Check session lock and expiration
        $session = null;
        if ($respondentIdentifier) {
            $session = QuizSession::query()
                ->where('quiz_form_id', $quizForm->id)
                ->where(function ($q) use ($user, $respondentIdentifier) {
                    if ($user) {
                        $q->where('user_id', $user->id)->orWhere('respondent_identifier', $respondentIdentifier);
                    } else {
                        $q->where('respondent_identifier', $respondentIdentifier);
                    }
                })
                ->first();
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

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Tanggapan kuis berhasil disimpan.',
            ]);
        }

        return to_route('forms.public', ['quizForm' => $quizForm->slug]);
    }

    public function lockSession(Request $request, QuizForm $quizForm): JsonResponse
    {
        $identifier = $request->input('respondent_identifier') ?? ($request->user() ? 'user_'.$request->user()->id : null);
        if ($identifier) {
            QuizSession::where('quiz_form_id', $quizForm->id)
                ->where('respondent_identifier', $identifier)
                ->update(['is_locked' => true]);
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

            if ($correctAnswer === null || $correctAnswer === '') {
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
                if (trim(mb_strtolower((string) $userAnswer)) === trim(mb_strtolower((string) $correctAnswer))) {
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
}
