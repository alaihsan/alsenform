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
    public function show(QuizForm $quizForm): Response
    {
        abort_unless($quizForm->published_at || auth()->user()?->is($quizForm->user), 404);

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
        ]);
    }

    public function store(StoreQuizResponseRequest $request, QuizForm $quizForm): RedirectResponse
    {
        abort_unless($quizForm->published_at || $request->user()?->is($quizForm->user), 404);

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
