<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * Headers of a visit made by Inertia inside the app.
 *
 * @return array<string, string>
 */
function errorPageInertiaHeaders(): array
{
    return [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
        'X-Requested-With' => 'XMLHttpRequest',
    ];
}

beforeEach(function () {
    config(['app.debug' => false]);

    Route::middleware('web')->group(function (): void {
        Route::get('_test/abort/{status}', fn (string $status) => abort((int) $status));
        Route::get('_test/locked', fn () => abort(403, 'Kuis sedang terkunci. Silakan hubungi pengawas / guru untuk membuka kunci.'));
        Route::post('_test/expired', fn () => throw new TokenMismatchException('CSRF token mismatch.'));
        Route::get('_test/throttled', fn () => throw new ThrottleRequestsException('Too Many Attempts.', null, ['Retry-After' => 42]));
        Route::post('_test/too-large', fn () => throw new PostTooLargeException('The POST data is too large.'));
        Route::get('_test/crash', fn () => throw new RuntimeException('SQLSTATE[08006] password=rahasia'));
    });
});

test('every error status gets an alsenform page instead of the laravel default', function (int $status, string $title) {
    $this->get("/_test/abort/{$status}")
        ->assertStatus($status)
        ->assertSee($title)
        ->assertSee("Kode Error {$status}")
        ->assertSee('Alsenform')
        ->assertDontSee(Response::$statusTexts[$status] ?? 'Page Expired');
})->with([
    [400, 'Permintaan Tidak Valid'],
    [401, 'Silakan Masuk Terlebih Dahulu'],
    [402, 'Permintaan Tidak Dapat Diproses'],
    [403, 'Akses Ditolak'],
    [404, 'Halaman Tidak Ditemukan'],
    [405, 'Aksi Tidak Dapat Dilakukan'],
    [408, 'Waktu Permintaan Habis'],
    [409, 'Permintaan Tidak Dapat Diproses'],
    [410, 'Permintaan Tidak Dapat Diproses'],
    [413, 'Berkas Terlalu Besar'],
    [419, 'Sesi Halaman Telah Habis'],
    [422, 'Permintaan Tidak Dapat Diproses'],
    [429, 'Terlalu Banyak Permintaan'],
    [500, 'Terjadi Kesalahan di Server'],
    [502, 'Server Sedang Bermasalah'],
    [503, 'Sedang Dalam Pemeliharaan'],
    [504, 'Server Sedang Bermasalah'],
]);

test('an unknown address shows the not found page with a way home', function () {
    $this->get('/alamat-yang-tidak-ada')
        ->assertNotFound()
        ->assertSee('Halaman Tidak Ditemukan')
        ->assertSee(route('home'), false)
        ->assertSee('Kembali');
});

test('visits inside the app get the error page instead of the raw error dialog', function () {
    $this->get('/alamat-yang-tidak-ada', errorPageInertiaHeaders())
        ->assertNotFound()
        ->assertHeader('X-Inertia', 'true')
        // The page knows the asset version even though no web middleware ran for this address.
        ->assertJsonPath('version', app(HandleInertiaRequests::class)->version(request()))
        ->assertJsonPath('component', 'Error')
        ->assertJsonPath('props.status', 404)
        ->assertJsonPath('props.title', 'Halaman Tidak Ditemukan')
        ->assertJsonPath('props.detail', null)
        ->assertJsonPath('props.primaryAction.href', route('home'))
        ->assertJsonPath('props.secondaryAction.label', 'Kembali')
        ->assertJsonPath('props.secondaryAction.href', null);
});

test('a missing exam does not reveal the internal not found message', function () {
    $student = User::factory()->create(['role' => 'siswa', 'nis' => '2024901']);

    $this->actingAs($student)
        ->get(route('forms.public', 'ujian-yang-tidak-ada'), errorPageInertiaHeaders())
        ->assertNotFound()
        ->assertJsonPath('component', 'Error')
        ->assertJsonPath('props.status', 404)
        ->assertJsonPath('props.detail', null);
});

test('pages for teachers show access denied to students without the english framework message', function () {
    $student = User::factory()->create(['role' => 'siswa', 'nis' => '2024902']);

    $this->actingAs($student)
        ->get(route('students.index'))
        ->assertForbidden()
        ->assertSee('Akses Ditolak')
        ->assertDontSee('This action is unauthorized');

    $this->actingAs($student)
        ->get(route('students.index'), errorPageInertiaHeaders())
        ->assertForbidden()
        ->assertJsonPath('component', 'Error')
        ->assertJsonPath('props.status', 403)
        ->assertJsonPath('props.detail', null);
});

test('the reason given to abort is shown on the error page', function () {
    $this->get('/_test/locked')
        ->assertForbidden()
        ->assertSee('Kuis sedang terkunci. Silakan hubungi pengawas / guru untuk membuka kunci.');

    $teacher = User::factory()->create();
    $otherTeacher = User::factory()->create();

    $this->actingAs($teacher)
        ->post(route('students.reset-password', $otherTeacher), [], errorPageInertiaHeaders())
        ->assertForbidden()
        ->assertJsonPath('component', 'Error')
        ->assertJsonPath('props.detail', 'Aksi ini hanya dapat dilakukan pada akun murid.');
});

test('an expired page offers to reload the page the form was sent from', function () {
    $this->post('/_test/expired', [], [...errorPageInertiaHeaders(), 'Referer' => url('/settings/profile')])
        ->assertStatus(419)
        ->assertJsonPath('component', 'Error')
        ->assertJsonPath('props.title', 'Sesi Halaman Telah Habis')
        ->assertJsonPath('props.primaryAction.label', 'Muat Ulang Halaman')
        ->assertJsonPath('props.primaryAction.href', url('/settings/profile'));

    // A page of another site is never offered.
    $this->post('/_test/expired', [], [...errorPageInertiaHeaders(), 'Referer' => 'https://contoh.com/halaman'])
        ->assertJsonPath('props.primaryAction.href', route('home'));
});

test('too many attempts tell how long to wait', function () {
    $this->get('/_test/throttled')
        ->assertTooManyRequests()
        ->assertSee('Silakan coba lagi dalam sekitar 42 detik.');

    $this->get('/_test/throttled', errorPageInertiaHeaders())
        ->assertTooManyRequests()
        ->assertHeader('Retry-After', '42')
        ->assertJsonPath('component', 'Error')
        ->assertJsonPath('props.hint', 'Silakan coba lagi dalam sekitar 42 detik.')
        ->assertJsonPath('props.primaryAction.label', 'Coba Lagi')
        ->assertJsonPath('props.primaryAction.href', url('/_test/throttled'));
});

test('an upload that is too large explains the size limit', function () {
    $this->post('/_test/too-large', [], [...errorPageInertiaHeaders(), 'Referer' => url('/settings/profile')])
        ->assertStatus(413)
        ->assertJsonPath('component', 'Error')
        ->assertJsonPath('props.title', 'Berkas Terlalu Besar')
        ->assertJsonPath('props.primaryAction.href', url('/settings/profile'));
});

test('a server error never shows its internal message', function () {
    $this->get('/_test/crash')
        ->assertInternalServerError()
        ->assertSee('Terjadi Kesalahan di Server')
        ->assertSee('Waktu kejadian')
        ->assertDontSee('rahasia');

    $this->get('/_test/crash', errorPageInertiaHeaders())
        ->assertInternalServerError()
        ->assertJsonPath('component', 'Error')
        ->assertJsonPath('props.detail', null)
        ->assertJsonPath('props.occurredAt', fn (?string $occurredAt) => $occurredAt !== null);
});

test('the debug page of a server error stays available during development', function () {
    config(['app.debug' => true]);

    $this->get('/_test/crash', errorPageInertiaHeaders())
        ->assertInternalServerError()
        ->assertHeaderMissing('X-Inertia');
});

test('maintenance mode shows the maintenance page', function () {
    $this->app->maintenanceMode()->activate(['retry' => 120, 'status' => 503, 'except' => []]);

    try {
        $this->get('/')
            ->assertServiceUnavailable()
            ->assertSee('Sedang Dalam Pemeliharaan')
            ->assertSee('Silakan coba lagi dalam sekitar 2 menit.')
            ->assertDontSee('Waktu kejadian');

        $this->get('/', errorPageInertiaHeaders())
            ->assertServiceUnavailable()
            ->assertJsonPath('component', 'Error')
            ->assertJsonPath('props.status', 503);
    } finally {
        $this->app->maintenanceMode()->deactivate();
    }
});

test('json requests such as the exam autosave keep their json errors', function () {
    $this->getJson('/alamat-yang-tidak-ada')
        ->assertNotFound()
        ->assertJsonStructure(['message']);

    $this->postJson(route('forms.responses.draft', 'ujian-yang-tidak-ada'), [])
        ->assertNotFound()
        ->assertJsonStructure(['message']);
});
