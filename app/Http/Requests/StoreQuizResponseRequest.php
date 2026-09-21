<?php

namespace App\Http\Requests;

use App\Models\QuizForm;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreQuizResponseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['nullable', 'email', 'max:255'],
            'respondent_identifier' => ['nullable', 'string', 'max:100'],
            'session_token' => ['nullable', 'string', 'max:100'],
            'is_timeout' => ['nullable', 'boolean'],
            'answers' => ['required', 'array'],
            'answers.*' => ['nullable'],
        ];
    }

    /**
     * Configure the validator instance for question-level validation.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $quizForm = $this->route('quizForm');
            if (! $quizForm instanceof QuizForm) {
                $quizForm = QuizForm::where('slug', (string) $this->route('quizForm'))->first();
            }

            if (! $quizForm) {
                return;
            }

            $formQuestions = collect($quizForm->questions ?? []);
            $validQuestionIds = $formQuestions->pluck('id')->all();
            $answers = $this->input('answers', []);
            $isTimeout = $this->boolean('is_timeout');

            // Validate that answered question IDs belong to this quiz
            foreach (array_keys($answers) as $qId) {
                if (! in_array($qId, $validQuestionIds)) {
                    $validator->errors()->add("answers.{$qId}", "Pertanyaan ID {$qId} tidak ditemukan.");
                }
            }

            // If timeout occurred, allow partial answers and skip required check
            if ($isTimeout) {
                return;
            }

            // Check collectEmail setting if user is unauthenticated
            if (! empty($quizForm->settings['collectEmail']) && empty($this->input('email')) && ! $this->user()) {
                $validator->errors()->add('email', 'Email wajib diisi untuk kuis ini.');
            }

            // Validate required questions
            foreach ($formQuestions as $q) {
                if (! empty($q['required'])) {
                    $qid = $q['id'];
                    $ans = $answers[$qid] ?? null;

                    if ($ans === null || $ans === '' || (is_array($ans) && empty($ans))) {
                        $validator->errors()->add("answers.{$qid}", "Pertanyaan '{$q['title']}' wajib diisi.");
                    }
                }
            }
        });
    }
}
