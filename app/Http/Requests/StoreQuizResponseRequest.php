<?php

namespace App\Http\Requests;

use App\Models\QuizForm;
use App\Models\QuizSession;
use App\Support\ExamLayout;
use App\Support\RespondentIdentity;
use App\Support\SectionFlow;
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
        $quizForm = $this->quizForm();

        return [
            'email' => ['nullable', 'email', 'max:255'],
            'respondent_identifier' => ['nullable', 'string', 'max:100'],
            'session_token' => ['nullable', 'string', 'max:100'],
            'is_timeout' => ['nullable', 'boolean'],
            // When the time ran out the answers may be empty: nothing was answered yet.
            'answers' => [$this->boolean('is_timeout') ? 'present' : 'required', 'array', 'max:500'],
            'answers.*' => ['nullable'],
            // The questions marked "Ragu-ragu".
            'doubts' => ['sometimes', 'nullable', 'array', 'max:500'],
            // The how-many-th try of the page to hand in.
            'submit_attempts' => ['nullable', 'integer', 'min:1', 'max:10000'],
            // A guest's name, class and email (the fields the teacher picked). Not required again
            // when the exam session holds them already, nor when the time ran out.
            ...($quizForm ? app(RespondentIdentity::class)->rules($quizForm, $this->user(), $this->examSession($quizForm), required: ! $this->boolean('is_timeout')) : []),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return app(RespondentIdentity::class)->messages();
    }

    protected function quizForm(): ?QuizForm
    {
        $quizForm = $this->route('quizForm');

        return $quizForm instanceof QuizForm ? $quizForm : QuizForm::where('slug', (string) $quizForm)->first();
    }

    protected function examSession(QuizForm $quizForm): ?QuizSession
    {
        $token = $this->input('session_token');

        return is_string($token) && $token !== ''
            ? QuizSession::query()->where('quiz_form_id', $quizForm->id)->where('session_token', $token)->first()
            : null;
    }

    /**
     * Configure the validator instance for question-level validation.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $quizForm = $this->quizForm();

            if (! $quizForm) {
                return;
            }

            $formQuestions = collect($quizForm->questions ?? []);
            $validQuestionIds = $formQuestions->pluck('id')->all();
            $answers = $this->input('answers', []);
            $isTimeout = $this->boolean('is_timeout');

            if (! is_array($answers)) {
                return;
            }

            $encoded = json_encode($answers, 0, SaveQuizDraftRequest::MAX_DEPTH);
            if ($encoded === false || strlen($encoded) > SaveQuizDraftRequest::MAX_PAYLOAD_BYTES) {
                $validator->errors()->add('answers', 'Jawaban terlalu besar.');

                return;
            }

            // With a random selection per student, only the questions this student got are required.
            $assignedIds = app(ExamLayout::class)->assignedIds($quizForm, $this->examSession($quizForm)?->layout);

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

            // Sections the student's answers skipped (see SectionFlow) are not required either.
            $onPathIds = SectionFlow::questionIdsOnPath(
                is_array($quizForm->questions) ? $quizForm->questions : [],
                is_array($quizForm->settings) ? $quizForm->settings : [],
                $answers,
            );

            // Validate required questions
            foreach ($formQuestions as $q) {
                if ($assignedIds !== null && ! in_array((string) ($q['id'] ?? ''), $assignedIds, true)) {
                    continue;
                }

                if ($onPathIds !== null && ! in_array((string) ($q['id'] ?? ''), $onPathIds, true)) {
                    continue;
                }

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
