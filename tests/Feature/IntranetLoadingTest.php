<?php

use App\Console\Commands\OptimizeExamCommand;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('the error page does not wait for web fonts either', function () {
    $html = $this->get('/halaman-yang-tidak-ada')->assertNotFound()->getContent();

    expect($html)->not->toMatch('/<link[^>]+href="https:\/\/fonts\./');
});

test('exam:optimize makes the password check at login light enough for a whole class at once', function () {
    $envPath = base_path('.env');
    $original = file_exists($envPath) ? file_get_contents($envPath) : null;
    config(['hashing.bcrypt.rounds' => 12]);

    try {
        file_put_contents($envPath, ($original ?? '')."\nBCRYPT_ROUNDS=12\n");

        $this->artisan('exam:optimize')->assertSuccessful();

        expect(file_get_contents($envPath))->toContain('BCRYPT_ROUNDS='.OptimizeExamCommand::BCRYPT_ROUNDS)
            ->not->toContain('BCRYPT_ROUNDS=12');
    } finally {
        $original === null ? @unlink($envPath) : file_put_contents($envPath, $original);
    }
});

test('a password stored with the heavier setting is made lighter at the next login', function () {
    config(['hashing.bcrypt.rounds' => 12]);
    $student = User::factory()->create(['role' => 'siswa', 'nis' => '2024999', 'password' => Hash::make('rahasia123')]);
    expect(password_get_info($student->password)['options']['cost'])->toBe(12);

    config(['hashing.bcrypt.rounds' => OptimizeExamCommand::BCRYPT_ROUNDS]);
    app()->forgetInstance('hash');
    Hash::clearResolvedInstances();

    $this->post(route('login'), ['identifier' => '2024999', 'password' => 'rahasia123'])->assertRedirect();

    expect(password_get_info($student->refresh()->password)['options']['cost'])->toBe(OptimizeExamCommand::BCRYPT_ROUNDS);
});
