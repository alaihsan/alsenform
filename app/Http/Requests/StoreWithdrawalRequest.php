<?php

namespace App\Http\Requests;

use App\Models\Donation;
use App\Models\Withdrawal;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreWithdrawalRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $currentBalance = Donation::query()->where('status', 'success')->sum('amount') - Withdrawal::query()->sum('amount');

        return [
            'amount' => ['required', 'integer', 'min:5000', 'max:'.$currentBalance],
            'bank_name' => ['required', 'string', 'max:255'],
            'account_number' => ['required', 'string', 'max:50'],
            'account_name' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.max' => 'Saldo tidak mencukupi untuk melakukan penarikan sebesar ini.',
        ];
    }
}
