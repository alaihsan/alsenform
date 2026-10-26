<?php

namespace App\Http\Requests;

use App\Support\PageFailure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A page the browser could not show, as the page guard reports it (also before login).
 */
class StorePageFailureRequest extends FormRequest
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
            'code' => ['required', 'string', Rule::enum(PageFailure::class)],
            'message' => ['nullable', 'string', 'max:2000'],
            'path' => ['nullable', 'string', 'max:2000'],
            'asset' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
