<?php

namespace App\Http\Requests;

use App\Models\QuizFolder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateQuizFolderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $quizFolder = $this->route('quizFolder');

        return $quizFolder instanceof QuizFolder
            && $this->user()?->can('update', $quizFolder);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $quizFolder = $this->route('quizFolder');

        return [
            'name' => [
                'required',
                'string',
                'max:80',
                Rule::unique('quiz_folders')
                    ->ignore($quizFolder)
                    ->where(fn ($query) => $query->where('user_id', $this->user()?->id)),
            ],
        ];
    }
}
