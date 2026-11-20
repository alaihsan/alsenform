<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

test('reset password link screen can be rendered', function () {
    $response = $this->get('/forgot-password');

    $response->assertStatus(200);
});

test('reset password link can be requested', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class);
});

test('reset password screen can be rendered', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
        $response = $this->get('/reset-password/'.$notification->token);

        $response->assertStatus(200);

        return true;
    });
});

test('password can be reset with valid token', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $response = $this->post('/reset-password', [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        return true;
    });
});

test('the answer to a reset request is in Indonesian, the same whether the address has an account or not', function () {
    Notification::fake();
    $user = User::factory()->create();

    foreach ([$user->email, 'tidak-terdaftar@example.com'] as $email) {
        $this->post('/forgot-password', ['email' => $email])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Jika alamat itu terdaftar, tautan atur ulang kata sandi sudah dikirim. Periksa kotak masuk atau folder Spam.');
    }
});

test('the reset email is in Indonesian, with the link and how long it works', function () {
    $user = User::factory()->create(['name' => 'Admin Sekolah', 'email' => 'admin@smpi-alazhar22.sch.id']);

    $mail = (new ResetPassword('token-uji'))->toMail($user);
    $html = (string) $mail->render();

    expect($mail->subject)->toBe('Atur ulang kata sandi '.config('app.name'))
        ->and($html)->toContain('Halo Admin Sekolah', 'Atur ulang kata sandi', 'berlaku 60 menit', 'abaikan saja email ini')
        ->and($html)->toContain(e(url('/reset-password/token-uji?email='.urlencode('admin@smpi-alazhar22.sch.id'))))
        ->not->toContain('You are receiving this email', 'If you\'re having trouble clicking');
});

test('an email the server cannot send is told on the page, with how to connect Gmail, instead of an error page', function () {
    // An SMTP server nothing answers for.
    config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1]);
    $user = User::factory()->create();

    $this->from('/forgot-password')
        ->post('/forgot-password', ['email' => $user->email])
        ->assertRedirect('/forgot-password')
        ->assertSessionHasErrors(['email' => 'Email belum dapat dikirim dari server. Admin perlu menyambungkan akun Gmail pengirim dengan perintah "php artisan mail:gmail".']);
});
