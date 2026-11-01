<?php

namespace App\Support;

use App\Models\QuizForm;
use App\Models\QuizSession;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Who a guest (someone without an account) is on a public quiz. The teacher picks the fields
 * when publishing: Nama Lengkap, Kelas and Email. Guests fill them in on the confirmation screen;
 * once the exam has started they are locked in the exam session.
 *
 * Mode pewawancara: everyone, the interviewer with an account too, names the candidate the
 * questionnaire is filled for (name, registration number and school).
 */
class RespondentIdentity
{
    /**
     * Fields stored in the quiz settings (respondentFields). Email is the existing
     * "collect email addresses" setting.
     *
     * @var list<string>
     */
    public const FIELDS = ['name', 'class'];

    /**
     * The candidate's fields in Mode pewawancara: the name is required, the others are not.
     *
     * @var list<string>
     */
    public const INTERVIEW_FIELDS = ['name', 'number', 'school'];

    /**
     * The classes of the Kelas drop-down.
     *
     * @var list<string>
     */
    public const CLASSES = ['7A', '7B', '7C', '7D', '8A', '8B', '8C', '8D', '9A', '9B', '9C', '9D'];

    /**
     * The fields this quiz asks guests for, in the order they are shown.
     *
     * @return list<string>
     */
    public function fields(QuizForm $quizForm): array
    {
        if ($quizForm->isInterview()) {
            return self::INTERVIEW_FIELDS;
        }

        $settings = is_array($quizForm->settings) ? $quizForm->settings : [];
        $fields = array_values(array_intersect(self::FIELDS, (array) ($settings['respondentFields'] ?? [])));

        if (! empty($settings['collectEmail'])) {
            $fields[] = 'email';
        }

        return $fields;
    }

    /**
     * Validation rules for the identity a guest sends. A field is required only while the exam
     * session does not hold it yet, and never when the time ran out (the answers come first).
     *
     * @return array<string, list<mixed>>
     */
    public function rules(QuizForm $quizForm, ?User $user, ?QuizSession $session = null, bool $required = true): array
    {
        if ($user && ! $this->asksIdentityOf($quizForm, $user)) {
            return [];
        }

        $fields = $this->fields($quizForm);
        $isRequired = fn (string $field, ?string $stored): string => $required && in_array($field, $fields, true) && blank($stored) ? 'required' : 'nullable';

        if ($quizForm->isInterview()) {
            return [
                'respondent_name' => [$isRequired('name', $session?->respondent_name), 'string', 'min:3', 'max:100', 'regex:/\pL/u'],
                'respondent_number' => ['nullable', 'string', 'max:50'],
                'respondent_school' => ['nullable', 'string', 'max:150'],
            ];
        }

        return [
            'respondent_name' => [$isRequired('name', $session?->respondent_name), 'string', 'min:3', 'max:100', 'regex:/\pL/u'],
            'respondent_class' => [$isRequired('class', $session?->respondent_class), 'string', Rule::in(self::CLASSES)],
            'email' => [$isRequired('email', $session?->respondent_email), 'email', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'respondent_name.required' => 'Nama lengkap wajib diisi.',
            'respondent_name.min' => 'Nama lengkap minimal 3 huruf.',
            'respondent_name.max' => 'Nama lengkap maksimal 100 karakter.',
            'respondent_name.regex' => 'Nama lengkap harus berisi huruf.',
            'respondent_number.max' => 'Nomor pendaftaran maksimal 50 karakter.',
            'respondent_school.max' => 'Asal sekolah maksimal 150 karakter.',
            'respondent_class.required' => 'Pilih kelas Anda.',
            'respondent_class.in' => 'Pilih kelas dari daftar yang tersedia.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Alamat email tidak valid.',
        ];
    }

    /**
     * The identity to store from validated input. What the exam session already holds is kept:
     * the identity cannot change once the exam has started.
     *
     * @param  array<string, mixed>  $input
     * @return array{respondent_name: string|null, respondent_class: string|null, respondent_email: string|null, respondent_number: string|null, respondent_school: string|null}
     */
    public function fromInput(QuizForm $quizForm, array $input, ?QuizSession $session = null): array
    {
        $fields = $this->fields($quizForm);
        $class = $input['respondent_class'] ?? null;
        $email = trim((string) ($input['email'] ?? ''));
        $text = fn (string $field, int $limit): ?string => in_array($field, $fields, true) && filled($value = Str::squish((string) ($input["respondent_{$field}"] ?? '')))
            ? Str::limit($value, $limit, '')
            : null;

        return [
            'respondent_name' => $session?->respondent_name ?? (in_array('name', $fields, true) ? $this->tidyName($input['respondent_name'] ?? null) : null),
            'respondent_class' => $session?->respondent_class ?? (in_array('class', $fields, true) && in_array($class, self::CLASSES, true) ? $class : null),
            'respondent_email' => $session?->respondent_email ?? (in_array('email', $fields, true) && $email !== '' ? Str::lower($email) : null),
            'respondent_number' => $session?->respondent_number ?? $text('number', 50),
            'respondent_school' => $session?->respondent_school ?? $text('school', 150),
        ];
    }

    /**
     * Whether someone with an account names who they answer for: only an interviewer, in Mode
     * pewawancara. Guests always say who they are.
     */
    public function asksIdentityOf(QuizForm $quizForm, ?User $user): bool
    {
        return $user === null || $quizForm->isInterview();
    }

    /**
     * Extra spaces removed; a name typed all in lower or upper case gets capital initials
     * ("budi SANTOSO" is kept, "budi santoso" becomes "Budi Santoso").
     */
    public function tidyName(mixed $name): ?string
    {
        $name = Str::squish((string) $name);

        if ($name === '') {
            return null;
        }

        if ($name === mb_strtolower($name) || $name === mb_strtoupper($name)) {
            $name = mb_convert_case(mb_strtolower($name), MB_CASE_TITLE);
        }

        return Str::limit($name, 100, '');
    }
}
