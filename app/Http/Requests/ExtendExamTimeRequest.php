<?php

namespace App\Http\Requests;

use App\Models\QuizForm;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ExtendExamTimeRequest extends FormRequest
{
    /**
     * The most minutes one "Tambah waktu" may give.
     */
    public const MAX_MINUTES = 120;

    /**
     * The teachers of the exam (its owner, collaborators and admins), like unlocking students.
     */
    public function authorize(): bool
    {
        $quizForm = $this->route('quizForm');

        return $quizForm instanceof QuizForm && $quizForm->canBeEditedBy($this->user());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'minutes' => ['required', 'integer', 'min:1', 'max:'.self::MAX_MINUTES],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'minutes.required' => 'Isi berapa menit waktu yang ditambahkan.',
            'minutes.integer' => 'Tambahan waktu harus berupa angka menit.',
            'minutes.min' => 'Tambahan waktu paling sedikit 1 menit.',
            'minutes.max' => 'Tambahan waktu paling banyak '.self::MAX_MINUTES.' menit sekali tambah.',
        ];
    }
}
