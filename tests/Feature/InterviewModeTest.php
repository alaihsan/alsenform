<?php

use App\Models\QuizForm;
use App\Models\QuizResponse;
use App\Models\User;
use App\Services\QuizResultsReport;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Mode pewawancara: an interviewer (logged in) fills the scored questionnaire for one candidate
 * after another, naming each candidate. Every response is the candidate's, not the interviewer's.
 */

beforeEach(function () {
    $this->teacher = User::factory()->create(['role' => 'guru', 'name' => 'Bu Penguji']);
    $this->interview = QuizForm::factory()->for($this->teacher)->create([
        'title' => 'Wawancara PMB',
        'published_at' => now(),
        'questions' => [
            ['id' => 1, 'title' => 'Anda memilih sekolah ini karena…', 'type' => 'Multiple choice', 'required' => true,
                'options' => ['Kemauan sendiri saja', 'Didukung orang tua', 'Kemauan orang tua saja'], 'optionScores' => [3, 2, 1]],
        ],
        // A limit of one response must not stop the interviewer at the second candidate.
        'settings' => ['isQuiz' => true, 'optionScoring' => true, 'interviewMode' => true, 'maxAttempts' => 1, 'showResultsSummary' => true, 'resultsDetail' => 'full'],
    ]);
});

/**
 * Open the questionnaire, name the candidate, start and hand in, as the page does.
 *
 * @param  array<string, string|null>  $candidate
 */
function interviewCandidate(QuizForm $form, User $interviewer, array $candidate, string $answer): void
{
    $token = test()->actingAs($interviewer)->get(route('forms.public', $form->slug))->inertiaProps('session.token');
    test()->actingAs($interviewer)->postJson(route('forms.responses.start', $form->slug), ['session_token' => $token, ...$candidate])->assertOk();
    test()->actingAs($interviewer)->postJson(route('forms.responses.store', $form->slug), ['session_token' => $token, 'answers' => [1 => $answer]])->assertOk();
}

test('the interviewer, logged in, is asked for the candidate before starting', function () {
    $this->actingAs($this->teacher)->get(route('forms.public', $this->interview->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('quizForm.isInterview', true)
            ->where('quizForm.identityFields', ['name', 'number', 'school']));

    $token = $this->actingAs($this->teacher)->get(route('forms.public', $this->interview->slug))->inertiaProps('session.token');
    $this->actingAs($this->teacher)->postJson(route('forms.responses.start', $this->interview->slug), ['session_token' => $token])
        ->assertUnprocessable()
        ->assertJsonPath('errors.respondent_name.0', 'Nama lengkap wajib diisi.');
});

test('every candidate gets a response of their own, with their name, registration number and school', function () {
    interviewCandidate($this->interview, $this->teacher, [
        'respondent_name' => 'ahmad fauzi', 'respondent_number' => 'PMB-001', 'respondent_school' => 'SDN 1 Bekasi',
    ], 'Kemauan sendiri saja');
    interviewCandidate($this->interview, $this->teacher, [
        'respondent_name' => 'Siti Aminah', 'respondent_number' => null, 'respondent_school' => 'MI Al Ikhlas',
    ], 'Kemauan orang tua saja');

    $responses = $this->interview->responses()->orderBy('id')->get();
    expect($responses)->toHaveCount(2)
        ->and($responses[0])->filled_for_respondent->toBeTrue()
        ->respondent_name->toBe('Ahmad Fauzi')
        ->respondent_number->toBe('PMB-001')
        ->respondent_school->toBe('SDN 1 Bekasi')
        ->user_id->toBe($this->teacher->id)
        ->email->toBeNull()
        ->score->toBe(3)
        ->and($responses[1]->score)->toBe(1)
        ->and($responses[1]->respondent_number)->toBeNull()
        ->and($responses[0]->studentKey())->not->toBe($responses[1]->studentKey())
        ->and($this->interview->submittedStudentCount())->toBe(2);

    // The teacher's gradebook lists the candidates, not the interviewer.
    $this->actingAs($this->teacher)->get(route('forms.edit', $this->interview))
        ->assertInertia(fn (Assert $page) => $page
            ->where('quizForm.responses.students', 2)
            ->where('quizForm.responses.gradebook.1.name', 'Ahmad Fauzi')
            ->where('quizForm.responses.gradebook.1.nis', 'PMB-001')
            ->where('quizForm.responses.gradebook.1.kelas', 'SDN 1 Bekasi')
            ->where('quizForm.responses.gradebook.1.interviewer', 'Bu Penguji')
            ->where('quizForm.responses.gradebook.1.attempts', 1)
            ->where('quizForm.responses.gradebook.0.nis', '-'));

    // The results page names the candidate of every questionnaire filled in.
    $this->actingAs($this->teacher)->get(route('forms.results', $this->interview->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('attempts.0.candidate.name', 'Ahmad Fauzi')
            ->where('attempts.1.candidate.school', 'MI Al Ikhlas'));
});

test('the Excel report names the candidates, with their registration number and school', function () {
    interviewCandidate($this->interview, $this->teacher, [
        'respondent_name' => 'Ahmad Fauzi', 'respondent_number' => 'PMB-001', 'respondent_school' => 'SDN 1 Bekasi',
    ], 'Didukung orang tua');
    $path = tempnam(sys_get_temp_dir(), 'interview-report-');

    app(QuizResultsReport::class)->save($this->interview->refresh(), $path);

    $zip = new ZipArchive;
    $zip->open($path);
    $scores = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    @unlink($path);

    expect($scores)->toContain('No. Pendaftaran')
        ->toContain('Asal Sekolah')
        ->toContain('Ahmad Fauzi')
        ->toContain('PMB-001')
        ->toContain('SDN 1 Bekasi')
        ->not->toContain('Bu Penguji');
});

test('interview mode needs a scored questionnaire; elsewhere accounts are not asked who they are', function () {
    $quiz = QuizForm::factory()->for($this->teacher)->create([
        'published_at' => now(),
        'settings' => ['isQuiz' => true, 'interviewMode' => true],
    ]);
    $student = User::factory()->create(['role' => 'siswa']);

    expect($quiz->isInterview())->toBeFalse();
    $this->actingAs($student)->get(route('forms.public', $quiz->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('quizForm.isInterview', false)
            ->where('quizForm.identityFields', []));
});

test('a response by an account outside interview mode stays the account holder\'s', function () {
    $student = User::factory()->create(['role' => 'siswa', 'name' => 'Rina', 'nis' => '2024001', 'kelas' => '7A']);
    $response = QuizResponse::create([
        'quiz_form_id' => $this->interview->id,
        'user_id' => $student->id,
        'respondent_identifier' => 'user_'.$student->id,
        'answers' => [1 => 'Kemauan sendiri saja'],
        'score' => 3,
    ]);

    expect($response->refresh()->filled_for_respondent)->toBeFalse()
        ->and($response->respondentDisplayName())->toBe('Rina')
        ->and($response->respondentDisplayNumber())->toBe('2024001')
        ->and($response->respondentDisplayGroup())->toBe('7A')
        ->and($response->studentKey())->toBe('user:'.$student->id);
});
