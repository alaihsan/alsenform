<?php

namespace App\Http\Requests;

use App\Models\QuizForm;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateQuizFormRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $quizForm = $this->route('quizForm');

        return $quizForm instanceof QuizForm
            && (bool) $this->user()
            && $quizForm->canBeEditedBy($this->user());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $quizForm = $this->route('quizForm');

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'alpha_dash:ascii',
                Rule::unique('quiz_forms', 'slug')->ignore($quizForm),
            ],
            'questions' => ['required', 'array', 'min:1'],
            'questions.*.id' => ['required'],
            'questions.*.title' => ['required', 'string'],
            'questions.*.description' => ['nullable', 'string'],
            'questions.*.type' => ['required', 'string', 'max:100'],
            'questions.*.options' => ['nullable', 'array'],
            'questions.*.rows' => ['nullable', 'array'],
            'questions.*.columns' => ['nullable', 'array'],
            'questions.*.answer' => ['nullable'],
            'questions.*.required' => ['nullable', 'boolean'],
            'questions.*.media' => ['nullable', 'array'],
            'questions.*.points' => ['nullable', 'integer', 'min:0'],
            'settings' => ['required', 'array'],
            'settings.collectEmail' => ['boolean'],
            'settings.showProgress' => ['boolean'],
            'settings.shuffleQuestions' => ['boolean'],
            'settings.isQuiz' => ['nullable', 'boolean'],
            'settings.emailCollectionMode' => ['nullable', 'string', Rule::in(['none', 'verified', 'responder'])],
            'settings.sendResponseCopy' => ['nullable', 'string', Rule::in(['off', 'request', 'always'])],
            'settings.allowResponseEditing' => ['nullable', 'boolean'],
            'settings.limitOneResponse' => ['nullable', 'boolean'],
            'settings.confirmationMessage' => ['nullable', 'string', 'max:500'],
            'settings.showSubmitAnotherResponse' => ['nullable', 'boolean'],
            'settings.showResultsSummary' => ['nullable', 'boolean'],
            'settings.disableRespondentAutosave' => ['nullable', 'boolean'],
            'settings.defaultCollectEmailMode' => ['nullable', 'string', Rule::in(['none', 'verified', 'responder'])],
            'settings.defaultQuestionRequired' => ['nullable', 'boolean'],
            'settings.defaultQuestionPoints' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'settings.maxUploadSize' => ['nullable', 'integer', 'min:1', 'max:40'],
            'settings.questionFont' => ['nullable', 'string', 'max:255'],
            'settings.answerFont' => ['nullable', 'string', 'max:255'],
            'settings.themeColorClass' => ['nullable', 'string', 'max:100'],
            'settings.backgroundColorClass' => ['nullable', 'string', 'max:100'],
            'settings.backgroundPatternClass' => ['nullable', 'string', 'max:100'],
            'settings.lockOnBlur' => ['nullable', 'boolean'],
            'settings.timeLimit' => ['nullable', 'integer', 'min:0'],
            'published' => ['nullable', 'boolean'],
            'cohort_ids' => ['nullable', 'array'],
            'cohort_ids.*' => ['integer', 'exists:cohorts,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.unique' => 'Link sudah digunakan',
            'slug.alpha_dash' => 'Link hanya boleh berisi huruf, angka, tanda hubung, dan garis bawah.',
        ];
    }
}
