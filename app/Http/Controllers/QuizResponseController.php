<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreQuizResponseRequest;
use App\Models\QuizForm;
use App\Models\QuizResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class QuizResponseController extends Controller
{
    public function show(QuizForm $quizForm): Response|RedirectResponse
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

        return Inertia::render('PublicQuiz', [
            'quizForm' => [
                'id' => $quizForm->id,
                'slug' => $quizForm->slug,
                'title' => $quizForm->title,
                'description' => $quizForm->description,
                'questions' => $quizForm->questions,
                'settings' => $quizForm->settings,
                'submitUrl' => route('forms.responses.store', ['quizForm' => $quizForm->slug]),
            ],
            'accessRestricted' => false,
        ]);
    }

    public function store(StoreQuizResponseRequest $request, QuizForm $quizForm): RedirectResponse
    {
        abort_unless($quizForm->published_at || $request->user()?->is($quizForm->user), 404);

        if ($quizForm->isRestrictedToCohorts() && ! $quizForm->allowsUser($request->user())) {
            abort(403, 'Anda tidak memiliki hak akses untuk mengerjakan kuis ini.');
        }

        $validated = $request->validated();

        QuizResponse::query()->create([
            'quiz_form_id' => $quizForm->id,
            'email' => $validated['email'] ?? null,
            'answers' => $validated['answers'],
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);

        return to_route('forms.public', ['quizForm' => $quizForm->slug]);
    }
}
