<?php

use App\Models\QuizForm;
use App\Models\QuizResponse;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->sqlitePath = tempnam(sys_get_temp_dir(), 'alsen_legacy_').'.sqlite';
    touch($this->sqlitePath);

    config(['database.connections.legacy_sqlite' => [
        'driver' => 'sqlite',
        'database' => $this->sqlitePath,
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]]);

    Artisan::call('migrate', ['--database' => 'legacy_sqlite', '--force' => true]);
    $this->legacy = DB::connection('legacy_sqlite');
});

afterEach(function () {
    DB::purge('legacy_sqlite');
    @unlink($this->sqlitePath);
});

test('existing sqlite data is moved to postgresql with booleans, json and ids intact', function () {
    $now = now()->format('Y-m-d H:i:s');

    $this->legacy->table('users')->insert([
        ['id' => 7, 'name' => 'Admin Sekolah', 'email' => 'admin@sekolah.test', 'password' => bcrypt('rahasia'), 'role' => 'admin', 'is_admin' => 1, 'must_change_password' => 0, 'created_at' => $now, 'updated_at' => $now],
        ['id' => 9, 'name' => 'Siswa Satu', 'email' => null, 'password' => bcrypt('rahasia'), 'role' => 'siswa', 'is_admin' => 0, 'must_change_password' => 1, 'created_at' => $now, 'updated_at' => $now],
    ]);
    $this->legacy->table('quiz_forms')->insert([
        'id' => 12,
        'user_id' => 7,
        'title' => 'Ujian Akhir',
        'description' => '',
        'slug' => 'ujian-akhir',
        'template' => 'blank',
        'questions' => json_encode([['id' => 1, 'title' => 'Ibu kota Indonesia?', 'type' => 'Short answer', 'answer' => 'Jakarta']]),
        'settings' => json_encode(['isQuiz' => true]),
        'published_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $this->legacy->table('quiz_responses')->insert([
        'id' => 30,
        'quiz_form_id' => 12,
        'user_id' => 9,
        'answers' => json_encode(['1' => 'Jakarta']),
        'score' => 1,
        'is_timeout' => 0,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $this->legacy->table('sessions')->insert(['id' => 'old-session', 'payload' => 'x', 'last_activity' => time()]);

    // PostgreSQL already has some test data: --force replaces it.
    User::factory()->create();

    $this->artisan('db:import-sqlite', ['path' => $this->sqlitePath, '--force' => true])
        ->expectsOutputToContain('berhasil dipindahkan')
        ->assertSuccessful();

    $admin = User::find(7);
    expect(User::count())->toBe(2)
        ->and($admin->isAdmin())->toBeTrue()
        ->and(User::find(9)->must_change_password)->toBeTrue()
        ->and(QuizForm::find(12)->questions[0]['answer'])->toBe('Jakarta')
        ->and(QuizResponse::find(30)->answers)->toBe(['1' => 'Jakarta'])
        ->and(DB::table('sessions')->where('id', 'old-session')->exists())->toBeFalse();

    // New records continue after the imported ids instead of colliding with them.
    expect(User::factory()->create()->id)->toBeGreaterThan(9)
        ->and(QuizForm::factory()->create()->id)->toBeGreaterThan(12);
});

test('the import refuses to overwrite postgresql data without --force', function () {
    $this->legacy->table('users')->insert([
        'name' => 'Guru', 'email' => 'guru@sekolah.test', 'password' => 'x', 'role' => 'guru', 'is_admin' => 0,
    ]);
    User::factory()->create(['email' => 'existing@sekolah.test']);

    $this->artisan('db:import-sqlite', ['path' => $this->sqlitePath])
        ->expectsOutputToContain('sudah berisi data')
        ->assertFailed();

    expect(User::where('email', 'existing@sekolah.test')->exists())->toBeTrue();
});

test('the import reports a missing sqlite file', function () {
    $this->artisan('db:import-sqlite', ['path' => '/tidak/ada/database.sqlite'])
        ->expectsOutputToContain('tidak ditemukan')
        ->assertFailed();
});
