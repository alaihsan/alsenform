<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreDonationRequest extends FormRequest
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
            'donor_name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'integer', 'min:1000', 'max:100000000'],
            'message' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
