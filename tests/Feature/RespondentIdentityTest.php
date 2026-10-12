<?php

use App\Models\ActivityLog;
use App\Models\QuizForm;
use App\Models\QuizResponse;
use App\Models\QuizSession;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->teacher = User::factory()->create(['role' => 'guru']);
    $this->exam = QuizForm::factory()->for($this->teacher)->create([
        'title' => 'Kuis Terbuka IPA',
        'published_at' => now(),
        'settings' => ['isQuiz' => true, 'timeLimit' => 30, 'respondentFields' => ['name', 'class'], 'collectEmail' => true, 'emailCollectionMode' => 'responder'],
        'questions' => [
            ['id' => 1, 'title' => '2 + 2 = ?', 'type' => 'Multiple choice', 'options' => ['3', '4'], 'answer' => '4', 'required' => true, 'points' => 1],
        ],
    ]);
});

/**
 * Open a public quiz as a guest and return the exam session it hands to the browser.
 *
 * @return array{token: string, respondent_identifier: string}
 */
function openAsGuest(QuizForm $quizForm): array
{
    return test()->get(route('forms.public', $quizForm->slug))->assertOk()->inertiaProps('session');
}

test('the teacher chooses which identity fields guests fill in', function () {
    $settings = [...$this->exam->settings, 'respondentFields' => ['class']];

    $this->actingAs($this->teacher)
        ->patch(route('forms.update', $this->exam), [
            'title' => $this->exam->title,
            'slug' => $this->exam->slug,
            'questions' => $this->exam->questions,
            'settings' => $settings,
            'published' => true,
        ])
        ->assertSessionHasNoErrors();

    expect($this->exam->fresh()->settings['respondentFields'])->toBe(['class']);

    $this->actingAs($this->teacher)
        ->patch(route('forms.update', $this->exam), [
            'title' => $this->exam->title,
            'slug' => $this->exam->slug,
            'questions' => $this->exam->questions,
            'settings' => [...$settings, 'respondentFields' => ['nisn']],
            'published' => true,
        ])
        ->assertSessionHasErrors('settings.respondentFields.0');
});

test('guests are asked for the chosen fields and accounts are not', function () {
    $this->get(route('forms.public', $this->exam->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('quizForm.identityFields', ['name', 'class', 'email'])
            ->where('quizForm.respondentClasses', ['7A', '7B', '7C', '7D', '8A', '8B', '8C', '8D', '9A', '9B', '9C', '9D'])
        );

    $this->actingAs(User::factory()->create(['role' => 'siswa', 'nis' => '2024101']))
        ->get(route('forms.public', $this->exam->slug))
        ->assertInertia(fn (Assert $page) => $page->where('quizForm.identityFields', []));
});

test('a quiz published before this feature asks guests for nothing', function () {
    $this->exam->update(['settings' => ['isQuiz' => true, 'timeLimit' => 30]]);
    $session = openAsGuest($this->exam);

    $this->postJson(route('forms.responses.start', $this->exam->slug), ['session_token' => $session['token']])->assertOk();
});

test('a guest must give a name, a class from the list and an email before starting', function () {
    $session = openAsGuest($this->exam);

    $this->postJson(route('forms.responses.start', $this->exam->slug), ['session_token' => $session['token']])
        ->assertUnprocessable()
        ->assertJsonPath('errors.respondent_name.0', 'Nama lengkap wajib diisi.')
        ->assertJsonPath('errors.respondent_class.0', 'Pilih kelas Anda.')
        ->assertJsonPath('errors.email.0', 'Email wajib diisi.');

    $this->postJson(route('forms.responses.start', $this->exam->slug), [
        'session_token' => $session['token'],
        'respondent_name' => 'B',
        'respondent_class' => '10A',
        'email' => 'bukan-email',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('errors.respondent_name.0', 'Nama lengkap minimal 3 huruf.')
        ->assertJsonPath('errors.respondent_class.0', 'Pilih kelas dari daftar yang tersedia.')
        ->assertJsonPath('errors.email.0', 'Alamat email tidak valid.');

    expect(QuizSession::firstWhere('session_token', $session['token'])->started_at)->toBeNull();
});

test('the identity is tidied, locked at the start and kept on the response', function () {
    $session = openAsGuest($this->exam);

    $this->postJson(route('forms.responses.start', $this->exam->slug), [
        'session_token' => $session['token'],
        'respondent_name' => '  budi   santoso ',
        'respondent_class' => '8B',
        'email' => 'Budi@Contoh.com',
    ])
        ->assertOk()
        ->assertJsonPath('session.respondent_name', 'Budi Santoso');

    $examSession = QuizSession::firstWhere('session_token', $session['token']);
    expect($examSession->respondent_name)->toBe('Budi Santoso')
        ->and($examSession->respondent_class)->toBe('8B')
        ->and($examSession->respondent_email)->toBe('budi@contoh.com');

    // Reloading the page during the exam (same device) shows who is taking it.
    $this->withCookie('alsen_resp_id', $session['respondent_identifier'])
        ->get(route('forms.public', $this->exam->slug))
        ->assertInertia(fn (Assert $page) => $page->where('session.respondent_name', 'Budi Santoso')->where('session.respondent_class', '8B'));

    // A retry with other values (or a later change in the browser) does not change the identity.
    $this->postJson(route('forms.responses.start', $this->exam->slug), [
        'session_token' => $session['token'],
        'respondent_name' => 'Orang Lain',
        'respondent_class' => '9D',
        'email' => 'lain@contoh.com',
    ])->assertOk();

    $this->postJson(route('forms.responses.store', $this->exam->slug), [
        'session_token' => $session['token'],
        'respondent_identifier' => $session['respondent_identifier'],
        'respondent_name' => 'Orang Lain',
        'respondent_class' => '9D',
        'answers' => [1 => '4'],
    ])->assertOk();

    expect(QuizResponse::sole())
        ->respondent_name->toBe('Budi Santoso')
        ->respondent_class->toBe('8B')
        ->email->toBe('budi@contoh.com')
        ->and(ActivityLog::where('event', ActivityLog::EXAM_SUBMITTED)->sole())
        ->respondent_name->toBe('Budi Santoso')
        ->respondent_class->toBe('8B');

});

test('a survey without a confirmation screen asks for the identity when sending', function () {
    $survey = QuizForm::factory()->for($this->teacher)->create([
        'published_at' => now(),
        'settings' => ['isQuiz' => false, 'respondentFields' => ['name']],
        'questions' => [['id' => 1, 'title' => 'Saran', 'type' => 'Short answer', 'required' => false]],
    ]);
    $session = openAsGuest($survey);

    $this->postJson(route('forms.responses.store', $survey->slug), [
        'session_token' => $session['token'],
        'respondent_identifier' => $session['respondent_identifier'],
        'answers' => [1 => 'Bagus'],
    ])->assertUnprocessable()->assertJsonPath('errors.respondent_name.0', 'Nama lengkap wajib diisi.');

    $this->postJson(route('forms.responses.store', $survey->slug), [
        'session_token' => $session['token'],
        'respondent_identifier' => $session['respondent_identifier'],
        'respondent_name' => 'SITI AMINAH',
        'respondent_class' => '7A',
        'answers' => [1 => 'Bagus'],
    ])->assertOk();

    // Only the chosen fields are kept.
    expect(QuizResponse::sole())->respondent_name->toBe('Siti Aminah')->respondent_class->toBeNull();
});

test('answers sent when the time ran out are accepted without an identity', function () {
    $session = openAsGuest($this->exam);
    QuizSession::firstWhere('session_token', $session['token'])->update(['started_at' => now()->subHour(), 'expires_at' => now()->subMinutes(30)]);

    $this->postJson(route('forms.responses.store', $this->exam->slug), [
        'session_token' => $session['token'],
        'respondent_identifier' => $session['respondent_identifier'],
        'answers' => [1 => '4'],
        'is_timeout' => true,
    ])->assertOk();

    expect(QuizResponse::sole()->is_timeout)->toBeTrue();
});

test('students with an account start without filling in anything', function () {
    $student = User::factory()->create(['name' => 'Ani Lestari', 'role' => 'siswa', 'nis' => '2024101', 'kelas' => 'X-1']);
    $this->actingAs($student);
    $session = openAsGuest($this->exam);

    $this->postJson(route('forms.responses.start', $this->exam->slug), ['session_token' => $session['token']])->assertOk();
    expect(QuizSession::firstWhere('session_token', $session['token'])->respondent_name)->toBeNull();
});

test('guest names appear in the grade recap, the excel file and the activity report', function () {
    QuizResponse::factory()->for($this->exam)->create(['respondent_name' => 'Budi Santoso', 'respondent_class' => '8B', 'answers' => [1 => '4']]);
    QuizResponse::factory()->for($this->exam)->create(['respondent_name' => 'Budi Santoso', 'respondent_class' => '8B', 'answers' => [1 => '3']]);
    QuizResponse::factory()->for($this->exam)->create(['respondent_name' => 'Citra Dewi', 'respondent_class' => '9A', 'answers' => [1 => '4']]);
    ActivityLog::factory()->examSubmitted($this->exam)->create(['user_id' => null, 'respondent_name' => 'Citra Dewi', 'respondent_class' => '9A']);

    $gradebook = collect($this->actingAs($this->teacher)->get(route('forms.edit', $this->exam->slug))->inertiaProps('quizForm.responses.gradebook'));

    expect($gradebook->pluck('name')->sort()->values()->all())->toBe(['Budi Santoso', 'Budi Santoso', 'Citra Dewi'])
        ->and($gradebook->firstWhere('name', 'Citra Dewi'))->toMatchArray(['kelas' => '9A', 'isGuest' => true, 'duplicateName' => false])
        ->and($gradebook->where('name', 'Budi Santoso')->pluck('duplicateName')->all())->toBe([true, true]);

    $export = $this->get(route('forms.responses.export', $this->exam));
    $zip = new ZipArchive;
    $zip->open($export->baseResponse->getFile()->getPathname());
    $recap = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    expect($recap)->toContain('Citra Dewi')->toContain('9A')->not->toContain('Anonim');

    $this->get(route('reports.index', ['search' => 'citra']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('activities.rows', 1)
            ->where('activities.rows.0.person.name', 'Citra Dewi')
            ->where('activities.rows.0.person.kelas', '9A')
            ->where('activities.rows.0.person.role', 'Tamu')
        );
});
