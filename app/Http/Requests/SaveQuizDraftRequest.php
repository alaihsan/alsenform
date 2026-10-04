<?php

namespace App\Http\Requests;

use App\Models\QuizForm;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SaveQuizDraftRequest extends FormRequest
{
    /**
     * Largest accepted draft (JSON encoded), enough for long essays on every question.
     */
    public const MAX_PAYLOAD_BYTES = 262144;

    /**
     * Deepest accepted answer: answers > question > grid row > ticked columns.
     */
    public const MAX_DEPTH = 4;

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
            'session_token' => ['required', 'string', 'max:100'],
            'respondent_identifier' => ['nullable', 'string', 'max:100'],
            'answers' => ['present', 'array', 'max:500'],
        ];
    }

    /**
     * Only answers to questions of this quiz, with a bounded size, are stored.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $answers = $this->input('answers');
                if (! is_array($answers)) {
                    return;
                }

                $quizForm = $this->route('quizForm');
                $questionIds = $quizForm instanceof QuizForm
                    ? array_map('strval', array_column($quizForm->questions ?? [], 'id'))
                    : [];

                foreach (array_keys($answers) as $questionId) {
                    if (! in_array((string) $questionId, $questionIds, true)) {
                        $validator->errors()->add('answers', 'Draf berisi pertanyaan yang tidak ada di kuis ini.');

                        return;
                    }
                }

                $encoded = json_encode($answers, 0, self::MAX_DEPTH);
                if ($encoded === false || strlen($encoded) > self::MAX_PAYLOAD_BYTES) {
                    $validator->errors()->add('answers', 'Draf jawaban terlalu besar.');
                }
            },
        ];
    }
}
