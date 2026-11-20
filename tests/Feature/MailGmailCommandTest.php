<?php

use App\Console\Commands\MailGmailCommand;
use App\Mail\MailSetupCheck;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Mailer\Exception\TransportException;

/*
 * php artisan mail:gmail: the Gmail (or Google Workspace) account that sends the links of "Lupa
 * kata sandi". Its settings are kept in .env only once Gmail took a test email. The tests write a
 * .env of their own, never the server's.
 */

const APP_PASSWORD_QUESTION = 'Sandi Aplikasi (16 huruf, tidak tampil saat diketik)';

beforeEach(function () {
    $this->liveEnvChangedAt = @filemtime(base_path('.env'));
    $this->directory = storage_path('framework/testing/mail-env-'.uniqid());
    File::ensureDirectoryExists($this->directory);
    app()->useEnvironmentPath($this->directory);
    $this->envFile = app()->environmentFilePath();
    $this->logOnly = "APP_NAME=\"Falcon Forms\"\nMAIL_MAILER=log\nMAIL_SCHEME=null\nMAIL_HOST=127.0.0.1\nMAIL_PORT=2525\nMAIL_USERNAME=null\nMAIL_PASSWORD=null\nMAIL_FROM_ADDRESS=\"hello@example.com\"\nMAIL_FROM_NAME=\"\${APP_NAME}\"\n";
    file_put_contents($this->envFile, $this->logOnly);
});

afterEach(function () {
    File::deleteDirectory($this->directory);
    clearstatcache();
    expect(@filemtime(base_path('.env')))->toBe($this->liveEnvChangedAt);
});

/**
 * mail:gmail on a server whose configuration is cached (or not), and whose test email Gmail takes
 * (or refuses with $error).
 */
function mailGmailOnServer(bool $isConfigurationCached, ?string $error = null): void
{
    app()->bind(MailGmailCommand::class, fn () => new class($isConfigurationCached, $error) extends MailGmailCommand
    {
        public function __construct(private bool $cached, private ?string $error)
        {
            parent::__construct();
        }

        protected function isConfigurationCached(): bool
        {
            return $this->cached;
        }

        protected function sendTestEmail(string $address, string $appPassword, string $recipient): void
        {
            if ($this->error !== null) {
                throw new TransportException($this->error);
            }

            parent::sendTestEmail($address, $appPassword, $recipient);
        }
    });
}

test('the Gmail account is kept once Gmail took the test email', function () {
    Mail::fake();

    $this->artisan('mail:gmail', ['address' => 'admin@smpi-alazhar22.sch.id'])
        ->expectsQuestion(APP_PASSWORD_QUESTION, 'abcd efgh ijkl mnop')
        ->expectsOutputToContain('Email uji terkirim ke admin@smpi-alazhar22.sch.id')
        ->assertSuccessful();

    Mail::assertSent(MailSetupCheck::class, fn (MailSetupCheck $mail): bool => $mail->hasTo('admin@smpi-alazhar22.sch.id') && $mail->mailer === 'smtp');
    expect(file_get_contents($this->envFile))->toContain(
        "MAIL_MAILER=smtp\n",
        "MAIL_HOST=smtp.gmail.com\n",
        "MAIL_PORT=587\n",
        'MAIL_USERNAME="admin@smpi-alazhar22.sch.id"',
        'MAIL_PASSWORD="abcdefghijklmnop"',
        'MAIL_FROM_ADDRESS="admin@smpi-alazhar22.sch.id"',
        'MAIL_FROM_NAME="${APP_NAME}"',
    )->not->toContain('MAIL_MAILER=log', 'MAIL_HOST=127.0.0.1');
});

test('the test email can go to another address, the sender asked when not given', function () {
    Mail::fake();

    $this->artisan('mail:gmail', ['--to' => 'tu@smpi-alazhar22.sch.id'])
        ->expectsQuestion('Alamat Gmail pengirim', 'admin@smpi-alazhar22.sch.id')
        ->expectsQuestion(APP_PASSWORD_QUESTION, 'abcdefghijklmnop')
        ->assertSuccessful();

    Mail::assertSent(MailSetupCheck::class, fn (MailSetupCheck $mail): bool => $mail->hasTo('tu@smpi-alazhar22.sch.id')
        && $mail->senderAddress === 'admin@smpi-alazhar22.sch.id');
});

test('a running server gets the new settings: its configuration is cached again without the old values', function () {
    Mail::fake();
    mailGmailOnServer(isConfigurationCached: true);

    $this->artisan('mail:gmail', ['address' => 'admin@smpi-alazhar22.sch.id'])
        ->expectsQuestion(APP_PASSWORD_QUESTION, 'abcdefghijklmnop')
        ->expectsOutputToContain('Cache konfigurasi server diperbarui')
        ->assertSuccessful();

    // In a process of its own, where the values this one read from .env at its start are unset.
    Process::assertRan(fn (PendingProcess $process): bool => $process->command === [PHP_BINARY, 'artisan', 'config:cache']
        && $process->path === base_path()
        && $process->environment['MAIL_MAILER'] === false
        && $process->environment['MAIL_PASSWORD'] === false);
});

test('a password Gmail refuses changes nothing, and says to use an app password', function () {
    mailGmailOnServer(isConfigurationCached: true, error: 'Failed to authenticate on SMTP server with username "admin@smpi-alazhar22.sch.id": Expected response code "235" but got code "535", with message "535-5.7.8 Username and Password not accepted."');

    $this->artisan('mail:gmail', ['address' => 'admin@smpi-alazhar22.sch.id'])
        ->expectsQuestion(APP_PASSWORD_QUESTION, 'abcdefghijklmnop')
        ->expectsOutputToContain('Sandi Aplikasi 16 huruf (bukan kata sandi biasa)')
        ->expectsOutputToContain('Setelan email yang lama tidak diubah.')
        ->assertFailed();

    expect(file_get_contents($this->envFile))->toBe($this->logOnly);
    Process::assertDidntRun(fn (PendingProcess $process): bool => $process->command === [PHP_BINARY, 'artisan', 'config:cache']);
});

test('an account password instead of the 16 letters of an app password is refused before sending', function () {
    Mail::fake();

    $this->artisan('mail:gmail', ['address' => 'admin@smpi-alazhar22.sch.id'])
        ->expectsQuestion(APP_PASSWORD_QUESTION, 'Rahasia#2026')
        ->expectsOutputToContain('Sandi Aplikasi Google terdiri dari 16 huruf')
        ->assertExitCode(MailGmailCommand::INVALID);

    Mail::assertNothingSent();
    expect(file_get_contents($this->envFile))->toBe($this->logOnly);
});

test('a server that cannot reach Gmail is told to check its internet', function () {
    expect(MailGmailCommand::hint('Connection could not be established with host "smtp.gmail.com:587": stream_socket_client(): php_network_getaddresses: getaddrinfo failed'))
        ->toContain('tersambung ke internet', 'port 587');
});

test('the test email says in Indonesian which account sends the app\'s emails', function () {
    expect((new MailSetupCheck('admin@smpi-alazhar22.sch.id'))->render())
        ->toContain('sudah tersambung', 'admin@smpi-alazhar22.sch.id', 'tautan atur ulang kata sandi');
});
