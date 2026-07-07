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
            && $this->user()?->is($quizForm->user);
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
            'questions.*.title' => ['required', 'string', 'max:255'],
            'questions.*.description' => ['nullable', 'string', 'max:1000'],
            'questions.*.type' => ['required', 'string', 'max:100'],
            'questions.*.options' => ['array'],
            'questions.*.answer' => ['nullable'],
            'questions.*.required' => ['boolean'],
            'questions.*.media' => ['array'],
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
            'settings.maxUploadSize' => ['nullable', 'integer', 'min:1', 'max:500'],
            'settings.questionFont' => ['nullable', 'string', 'max:255'],
            'settings.answerFont' => ['nullable', 'string', 'max:255'],
            'settings.themeColorClass' => ['nullable', 'string', 'max:100'],
            'settings.backgroundColorClass' => ['nullable', 'string', 'max:100'],
            'settings.backgroundPatternClass' => ['nullable', 'string', 'max:100'],
            'published' => ['nullable', 'boolean'],
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
