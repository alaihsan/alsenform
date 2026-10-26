<?php

use App\Models\ActivityLog;
use App\Models\QuizForm;
use App\Models\User;
use App\Support\PageFailure;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * No page stays blank white: the page guard shows a code (App\Support\PageFailure) and reports it
 * to the activity report with the device and browser, so an admin sees who could not open what.
 */
const OLD_IPAD = 'Mozilla/5.0 (iPad; CPU OS 15_8 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/15.6.6 Mobile/15E148 Safari/604.1';

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin', 'is_admin' => true]);
    $this->teacher = User::factory()->create(['role' => 'guru', 'nip' => '198001']);
    $this->student = User::factory()->create(['name' => 'Ani Lestari', 'role' => 'siswa', 'nis' => '2024101', 'kelas' => 'X-1']);
    $this->exam = QuizForm::factory()->for($this->teacher)->create(['title' => 'Ulangan Kimia', 'published_at' => now()]);
});

test('every page carries the guard with all codes, and FF-101 for browsers without JavaScript', function () {
    $html = $this->get(route('login'))->assertOk()->getContent();

    expect($html)
        ->toContain('window.FalconPageGuard')
        ->toContain('"\/galat-halaman"')
        ->toContain('<noscript>')
        ->toContain('FF-101');
    foreach (PageFailure::cases() as $failure) {
        expect($html)->toContain('"'.$failure->value.'"');
    }
});

test('a guest\'s blank exam page is recorded with its code, device and exam', function () {
    $this->withHeader('User-Agent', OLD_IPAD)
        ->post(route('page-failures.store'), [
            'code' => 'FF-301',
            'path' => "/forms/{$this->exam->slug}",
            'message' => "SyntaxError:   Invalid regular expression: invalid group specifier name\n(app.js:1:2)",
        ])
        ->assertNoContent();

    $log = ActivityLog::query()->sole();
    expect($log)
        ->event->toBe(ActivityLog::PAGE_FAILED)
        ->user_id->toBeNull()
        ->quiz_form_id->toBe($this->exam->id)
        ->device_type->toBe('tablet')
        ->and($log->browser)->toContain('Safari')
        ->and($log->meta)->toBe([
            'code' => 'FF-301',
            'message' => 'SyntaxError: Invalid regular expression: invalid group specifier name (app.js:1:2)',
            'path' => "/forms/{$this->exam->slug}",
        ]);
});

test('a signed-in student\'s report names the student; unknown codes and floods are refused', function () {
    $this->actingAs($this->student)
        ->post(route('page-failures.store'), ['code' => 'FF-201', 'path' => '/dashboard', 'asset' => '/build/assets/app-1a2b.js', 'message' => str_repeat('x', 900)])
        ->assertNoContent();

    expect(ActivityLog::query()->sole())
        ->user_id->toBe($this->student->id)
        ->quiz_form_id->toBeNull()
        ->and(ActivityLog::query()->sole()->meta['message'])->toHaveLength(300);

    $this->postJson(route('page-failures.store'), ['code' => 'FF-999'])->assertUnprocessable()->assertJsonValidationErrors('code');

    // Ten reports a minute per device, these two included.
    foreach (range(1, 8) as $attempt) {
        $this->post(route('page-failures.store'), ['code' => 'FF-401'])->assertNoContent();
    }
    $this->post(route('page-failures.store'), ['code' => 'FF-401'])->assertTooManyRequests();
});

test('the report shows each failed page with its code, meaning and details', function () {
    $this->actingAs($this->student)->withHeader('User-Agent', OLD_IPAD)
        ->post(route('page-failures.store'), ['code' => 'FF-202', 'path' => '/dashboard', 'message' => 'HTTP 404', 'asset' => '/build/assets/Dashboard-9z.js']);

    $this->actingAs($this->admin)
        ->get(route('reports.index', ['event' => ActivityLog::PAGE_FAILED]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('activities.rows', 1)
            ->where('activities.rows.0.event', 'page_failed')
            ->where('activities.rows.0.eventLabel', 'Halaman gagal dimuat')
            ->where('activities.rows.0.note', 'FF-202: Berkas aplikasi tidak ditemukan di server')
            ->where('activities.rows.0.detail', '/dashboard · HTTP 404 · /build/assets/Dashboard-9z.js')
            ->where('activities.rows.0.person.name', 'Ani Lestari')
            ->where('eventOptions', fn ($options) => collect($options)->contains('value', 'page_failed')));
});

test('teachers see the failed pages of students and on their exams; a guest elsewhere is for admins', function () {
    $this->actingAs($this->student)->post(route('page-failures.store'), ['code' => 'FF-102', 'path' => '/dashboard']);
    auth()->logout();
    $this->post(route('page-failures.store'), ['code' => 'FF-201', 'path' => "/forms/{$this->exam->slug}"]);
    $this->post(route('page-failures.store'), ['code' => 'FF-401', 'path' => '/login']);

    $this->actingAs($this->teacher)
        ->get(route('reports.index', ['event' => ActivityLog::PAGE_FAILED]))
        ->assertInertia(fn (Assert $page) => $page->has('activities.rows', 2));

    $this->actingAs($this->admin)
        ->get(route('reports.index', ['event' => ActivityLog::PAGE_FAILED]))
        ->assertInertia(fn (Assert $page) => $page->has('activities.rows', 3));
});
