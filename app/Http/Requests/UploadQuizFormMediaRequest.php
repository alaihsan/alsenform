<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UploadQuizFormMediaRequest extends FormRequest
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
            'file' => [
                'required',
                'file',
                'mimetypes:image/jpeg,image/png,image/gif,image/webp,video/mp4,video/webm,video/quicktime',
                'mimes:jpg,jpeg,png,gif,webp,mp4,webm,mov',
                'max:40960',
            ],
        ];
    }
}
