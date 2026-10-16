<?php

namespace App\Http\Requests;

use App\Models\QuizForm;
use App\Support\Addon;
use App\Support\FormDesign;
use App\Support\RespondentIdentity;
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
            // A section or a "title and description" may go without a title (a description only).
            'questions.*.title' => ['required_unless:questions.*.type,Section,Title and description', 'nullable', 'string'],
            'questions.*.description' => ['nullable', 'string'],
            'questions.*.type' => ['required', 'string', 'max:100'],
            'questions.*.options' => ['nullable', 'array'],
            'questions.*.rows' => ['nullable', 'array'],
            'questions.*.columns' => ['nullable', 'array'],
            'questions.*.answer' => ['nullable'],
            'questions.*.required' => ['nullable', 'boolean'],
            'questions.*.media' => ['nullable', 'array'],
            'questions.*.points' => ['nullable', 'integer', 'min:0'],
            // Pembahasan: shown to students with their results, when the teacher shows them in full.
            'questions.*.explanation' => ['nullable', 'string', 'max:5000'],
            // Going to another section, like in Google Forms (see SectionFlow): after a section, and
            // by the answer to a multiple-choice or drop-down question.
            'questions.*.goTo' => ['nullable'],
            'questions.*.branching' => ['nullable', 'boolean'],
            'questions.*.optionGoTo' => ['nullable', 'array'],
            'questions.*.optionGoTo.*' => ['nullable'],
            'settings' => ['required', 'array'],
            'settings.collectEmail' => ['boolean'],
            'settings.showProgress' => ['boolean'],
            'settings.shuffleQuestions' => ['boolean'],
            'settings.shuffleOptions' => ['nullable', 'boolean'],
            'settings.isQuiz' => ['nullable', 'boolean'],
            'settings.emailCollectionMode' => ['nullable', 'string', Rule::in(['none', 'verified', 'responder'])],
            'settings.sendResponseCopy' => ['nullable', 'string', Rule::in(['off', 'request', 'always'])],
            'settings.allowResponseEditing' => ['nullable', 'boolean'],
            'settings.limitOneResponse' => ['nullable', 'boolean'],
            // How many times a student may take the quiz; 0 is no limit.
            'settings.maxAttempts' => ['nullable', 'integer', 'min:0', 'max:100'],
            'settings.resultsDetail' => ['nullable', 'string', Rule::in(['score', 'full'])],
            // Jadwal ujian: when students may start (ISO times).
            'settings.schedule' => ['nullable', 'array:opensAt,closesAt'],
            'settings.schedule.opensAt' => ['nullable', 'date'],
            'settings.schedule.closesAt' => ['nullable', 'date', 'after:settings.schedule.opensAt'],
            // Add-ons switched on or off for this exam, by add-on key.
            'settings.addons' => ['nullable', 'array:'.implode(',', array_column(Addon::cases(), 'value'))],
            'settings.addons.*' => ['boolean'],
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
            // Desainer Form add-on: normalised by FormDesign when saved.
            'settings.design' => ['nullable', 'array'],
            'settings.design.*' => ['nullable'],
            'settings.design.customCss' => ['nullable', 'string', 'max:'.FormDesign::MAX_CSS_LENGTH],
            'settings.lockOnBlur' => ['nullable', 'boolean'],
            'settings.timeLimit' => ['nullable', 'integer', 'min:0'],
            'settings.questionsPerPage' => ['nullable'],
            'settings.firstSectionGoTo' => ['nullable'],
            'settings.questionDraw' => ['nullable', 'array'],
            'settings.questionDraw.enabled' => ['nullable', 'boolean'],
            'settings.questionDraw.mode' => ['nullable', 'string', Rule::in(['count', 'difficulty'])],
            'settings.questionDraw.count' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'settings.questionDraw.perDifficulty' => ['nullable', 'array'],
            'settings.questionDraw.perDifficulty.mudah' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'settings.questionDraw.perDifficulty.sedang' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'settings.questionDraw.perDifficulty.sulit' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'settings.respondentFields' => ['nullable', 'array'],
            'settings.respondentFields.*' => ['string', Rule::in(RespondentIdentity::FIELDS)],
            'published' => ['nullable', 'boolean'],
            'base_version' => ['nullable', 'string', 'max:64'],
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
