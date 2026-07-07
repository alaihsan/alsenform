<?php

namespace App\Http\Requests;

use App\Models\QuizForm;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class MoveQuizFormToFolderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $quizForm = $this->route('quizForm');

        return $quizForm instanceof QuizForm
            && $this->user()?->can('update', $quizForm);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'folder_id' => ['nullable', 'integer', 'exists:quiz_folders,id'],
            'folder' => ['nullable', 'string', 'max:80'],
        ];
    }
}
