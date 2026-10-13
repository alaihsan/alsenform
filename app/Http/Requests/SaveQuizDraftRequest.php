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
            // The questions marked "Ragu-ragu".
            'doubts' => ['sometimes', 'nullable', 'array', 'max:500'],
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
                $problem = self::answersProblem($quizForm instanceof QuizForm ? $quizForm : null, $answers);

                if ($problem !== null) {
                    $validator->errors()->add('answers', $problem);
                }
            },
        ];
    }

    /**
     * Why the answers cannot be stored as a draft of this quiz, or null when they can.
     *
     * @param  array<mixed>  $answers
     */
    public static function answersProblem(?QuizForm $quizForm, array $answers): ?string
    {
        $questionIds = $quizForm !== null
            ? array_map('strval', array_column($quizForm->questions ?? [], 'id'))
            : [];

        if (count($answers) > 500) {
            return 'Draf jawaban terlalu besar.';
        }

        foreach (array_keys($answers) as $questionId) {
            if (! in_array((string) $questionId, $questionIds, true)) {
                return 'Draf berisi pertanyaan yang tidak ada di kuis ini.';
            }
        }

        $encoded = json_encode($answers, 0, self::MAX_DEPTH);

        return $encoded === false || strlen($encoded) > self::MAX_PAYLOAD_BYTES ? 'Draf jawaban terlalu besar.' : null;
    }
}
