<?php

use App\Models\ActivityLog;
use App\Models\QuizForm;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

const SAMSUNG_BROWSER = 'Mozilla/5.0 (Linux; Android 13; SAMSUNG SM-A536E) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/26.0 Chrome/122.0.0.0 Mobile Safari/537.36';
const EDGE_ON_WINDOWS = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36 Edg/129.0.2792.79';

beforeEach(function () {
    $this->teacher = User::factory()->create(['name' => 'Bu Rina', 'nip' => '198001']);
    $this->student = User::factory()->create(['name' => 'Ani Lestari', 'role' => 'siswa', 'nis' => '2024101', 'kelas' => 'X-1']);
    $this->exam = QuizForm::factory()->for($this->teacher)->create([
        'title' => 'Ujian Matematika',
        'published_at' => now(),
        'settings' => ['isQuiz' => true, 'timeLimit' => 45],
        'questions' => [
            ['id' => 1, 'title' => '2 + 2 = ?', 'type' => 'Multiple choice', 'options' => ['3', '4'], 'answer' => '4', 'points' => 1],
        ],
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
});

test('a login is recorded with the device, browser, address and time', function () {
    Carbon::setTestNow('2026-10-11 00:30:00');

    $this->post(route('login'), ['identifier' => '2024101', 'password' => 'password'], ['User-Agent' => SAMSUNG_BROWSER])
        ->assertRedirect(route('dashboard', absolute: false));

    $log = ActivityLog::sole();
    expect($log->event)->toBe(ActivityLog::LOGIN)
        ->and($log->user_id)->toBe($this->student->id)
        ->and($log->device_type)->toBe('mobile')
        ->and($log->device)->toBe('Samsung SM-A536E')
        ->and($log->os)->toBe('Android 13')
        ->and($log->browser)->toBe('Samsung Internet 26')
        ->and($log->ip_address)->toBe('127.0.0.1')
        ->and($log->user_agent)->toBe(SAMSUNG_BROWSER)
        ->and($log->created_at->toIso8601String())->toBe('2026-10-11T00:30:00+00:00');
});

test('a failed login is not recorded and a logout is', function () {
    $this->post(route('login'), ['identifier' => '2024101', 'password' => 'salah']);
    expect(ActivityLog::count())->toBe(0);

    $this->actingAs($this->student)->post(route('logout'), [], ['User-Agent' => EDGE_ON_WINDOWS]);

    expect(ActivityLog::sole())
        ->event->toBe(ActivityLog::LOGOUT)
        ->device->toBe('PC Windows')
        ->browser->toBe('Microsoft Edge 129.0.2792.79');
});

test('starting and submitting an exam are recorded once each', function () {
    $this->actingAs($this->student);
    $token = $this->get(route('forms.public', $this->exam->slug))->inertiaProps('session.token');
    expect(ActivityLog::count())->toBe(0);

    $this->postJson(route('forms.responses.start', $this->exam->slug), ['session_token' => $token], ['User-Agent' => SAMSUNG_BROWSER])->assertOk();
    // A retry on weak Wi-Fi does not start the exam a second time.
    $this->postJson(route('forms.responses.start', $this->exam->slug), ['session_token' => $token], ['User-Agent' => SAMSUNG_BROWSER])->assertOk();

    $this->postJson(route('forms.responses.store', $this->exam->slug), [
        'session_token' => $token,
        'answers' => [1 => '4'],
        'is_timeout' => true,
    ], ['User-Agent' => EDGE_ON_WINDOWS])->assertOk();

    $logs = ActivityLog::orderBy('id')->get();
    expect($logs->pluck('event')->all())->toBe([ActivityLog::EXAM_STARTED, ActivityLog::EXAM_SUBMITTED])
        ->and($logs->pluck('quiz_form_id')->unique()->all())->toBe([$this->exam->id])
        ->and($logs->pluck('user_id')->unique()->all())->toBe([$this->student->id])
        // The student switched from a phone to a computer for the submit.
        ->and($logs->pluck('device')->all())->toBe(['Samsung SM-A536E', 'PC Windows'])
        ->and($logs[1]->meta)->toBe(['timeout' => true]);
});

test('guests on a public survey are recorded with their email', function () {
    $survey = QuizForm::factory()->for($this->teacher)->create([
        'published_at' => now(),
        'settings' => ['isQuiz' => false],
        'questions' => [['id' => 1, 'title' => 'Saran', 'type' => 'Short answer']],
    ]);

    $this->get(route('forms.public', $survey->slug))->assertOk();
    $this->postJson(route('forms.responses.store', $survey->slug), ['answers' => [1 => 'Bagus'], 'email' => 'tamu@example.com'])->assertOk();

    $logs = ActivityLog::orderBy('id')->get();
    expect($logs->pluck('event')->all())->toBe([ActivityLog::EXAM_STARTED, ActivityLog::EXAM_SUBMITTED])
        ->and($logs->pluck('user_id')->filter()->all())->toBe([])
        ->and($logs[1]->respondent_email)->toBe('tamu@example.com')
        ->and($logs[0]->respondent_identifier)->toStartWith('anon_');
});

test('logging in still works when the activity table is missing', function () {
    Schema::drop('activity_logs');

    $this->post(route('login'), ['identifier' => '2024101', 'password' => 'password'])
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($this->student);
});

test('the report shows each activity with its device, browser and local time', function () {
    Carbon::setTestNow('2026-10-11 02:00:00');
    ActivityLog::factory()->for($this->student)->create(['created_at' => '2026-10-11 00:30:15', 'ip_address' => '192.168.1.23']);
    ActivityLog::factory()->for($this->student)->examSubmitted($this->exam)->fromComputer()->create(['created_at' => '2026-10-11 01:15:00', 'meta' => ['timeout' => true]]);

    $this->actingAs($this->teacher)
        ->get(route('reports.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Reports/Activity')
            ->where('filters.from', '2026-10-11')
            ->where('filters.to', '2026-10-11')
            ->has('activities.rows', 2)
            ->where('activities.rows.0.eventLabel', 'Kirim jawaban')
            ->where('activities.rows.0.form.title', 'Ujian Matematika')
            ->where('activities.rows.0.form.url', route('forms.edit', $this->exam->slug))
            ->where('activities.rows.0.note', 'Waktu habis')
            ->where('activities.rows.0.browser', 'Microsoft Edge 129.0.2792.79')
            ->where('activities.rows.1.eventLabel', 'Masuk (login)')
            ->where('activities.rows.1.date', 'Min, 11 Okt 2026')
            ->where('activities.rows.1.time', '07:30:15')
            ->where('activities.rows.1.timezone', 'WIB')
            ->where('activities.rows.1.person.name', 'Ani Lestari')
            ->where('activities.rows.1.person.identifier', '2024101')
            ->where('activities.rows.1.person.role', 'Siswa')
            ->where('activities.rows.1.person.kelas', 'X-1')
            ->where('activities.rows.1.device', 'Samsung SM-A536E')
            ->where('activities.rows.1.os', 'Android 13')
            ->where('activities.rows.1.deviceTypeLabel', 'HP')
            ->where('activities.rows.1.ip', '192.168.1.23')
            ->where('summary.logins', 1)
            ->where('summary.loginUsers', 1)
            ->where('summary.examSubmitted', 1)
            ->where('summary.people.mobile', 1)
            ->where('summary.people.desktop', 1)
            ->where('forms', [['id' => $this->exam->id, 'title' => 'Ujian Matematika']])
            ->where('roleOptions', null)
        );
});

test('teachers see their students and their own exams, admins see everything', function () {
    $otherTeacher = User::factory()->create(['name' => 'Pak Budi']);
    $otherExam = QuizForm::factory()->for($otherTeacher)->create(['title' => 'Ujian Fisika']);
    $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true]);

    ActivityLog::factory()->for($this->student)->create();
    ActivityLog::factory()->for($otherTeacher)->create();
    ActivityLog::factory()->for($this->student)->examStarted($this->exam)->create();
    ActivityLog::factory()->for($this->student)->examStarted($otherExam)->create();
    ActivityLog::factory()->for($this->teacher)->create();

    $this->actingAs($this->teacher)
        ->get(route('reports.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('activities.rows', 3)
            ->where('activities.rows', fn ($rows) => collect($rows)->pluck('person.name')->sort()->values()->all() === ['Ani Lestari', 'Ani Lestari', 'Bu Rina']
                && collect($rows)->pluck('form.title')->filter()->values()->all() === ['Ujian Matematika'])
        );

    $this->actingAs($admin)
        ->get(route('reports.index'))
        ->assertInertia(fn (Assert $page) => $page->has('activities.rows', 5)->has('roleOptions', 4));

    $this->actingAs($this->student)->get(route('reports.index'))->assertForbidden();
    $this->actingAs($this->student)->get(route('reports.export'))->assertForbidden();
});

test('the report can be filtered by period, activity, exam, role and search', function () {
    Carbon::setTestNow('2026-10-11 05:00:00');
    $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true]);
    $otherStudent = User::factory()->create(['name' => 'Budi Santoso', 'role' => 'siswa', 'nis' => '2024102']);

    ActivityLog::factory()->for($this->student)->create(['created_at' => '2026-10-11 01:00:00']);
    ActivityLog::factory()->for($otherStudent)->create(['created_at' => '2026-10-11 01:05:00']);
    ActivityLog::factory()->for($this->student)->examStarted($this->exam)->create(['created_at' => '2026-10-11 01:10:00']);
    ActivityLog::factory()->for($this->teacher)->create(['created_at' => '2026-10-11 01:20:00']);
    // Yesterday in Jakarta (16:59 UTC on the 10th is 23:59 WIB).
    ActivityLog::factory()->for($this->student)->create(['created_at' => '2026-10-10 16:59:00']);

    $rows = fn (array $query) => $this->actingAs($admin)->get(route('reports.index', $query))->inertiaProps('activities.rows');

    expect($rows([]))->toHaveCount(4)
        ->and($rows(['from' => '2026-10-10', 'to' => '2026-10-10']))->toHaveCount(1)
        ->and($rows(['from' => '2026-10-11', 'to' => '2026-10-10']))->toHaveCount(5)
        ->and($rows(['event' => ActivityLog::EXAM_STARTED]))->toHaveCount(1)
        ->and($rows(['form' => $this->exam->id]))->toHaveCount(1)
        ->and($rows(['role' => 'guru']))->toHaveCount(1)
        ->and($rows(['role' => 'siswa']))->toHaveCount(3)
        ->and($rows(['search' => 'budi']))->toHaveCount(1)
        ->and($rows(['search' => '2024101']))->toHaveCount(2)
        ->and($rows(['event' => 'tidak-ada', 'from' => 'bukan-tanggal']))->toHaveCount(4);

    // The totals ignore the activity filter, so the tiles always show the whole period.
    $this->actingAs($admin)
        ->get(route('reports.index', ['event' => ActivityLog::EXAM_STARTED]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.logins', 3)
            ->where('summary.loginUsers', 3)
            ->where('summary.examStarted', 1)
        );
});

test('the report can be downloaded as an excel workbook', function () {
    Carbon::setTestNow('2026-10-11 05:00:00');
    ActivityLog::factory()->for($this->student)->create(['created_at' => '2026-10-11 00:30:15', 'ip_address' => '192.168.1.23']);

    $response = $this->actingAs($this->teacher)->get(route('reports.export'));

    $response->assertOk()->assertDownload('laporan_aktivitas_20261011.xlsx');

    $zip = new ZipArchive;
    $zip->open($response->baseResponse->getFile()->getPathname());
    $sheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();

    expect($sheet)->toContain('Ani Lestari')
        ->toContain('11/10/2026')
        ->toContain('07:30:15 WIB')
        ->toContain('Samsung SM-A536E')
        ->toContain('Chrome 129.0.6668.100')
        ->toContain('192.168.1.23');
});
