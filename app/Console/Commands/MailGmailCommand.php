<?php

namespace App\Console\Commands;

use App\Mail\MailSetupCheck;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * Connects the Gmail (or Google Workspace) account that sends the app's emails, such as the links
 * of "Lupa kata sandi". It sends a test email first and keeps the settings in .env only once Gmail
 * took it, so a mistyped app password never replaces settings that work. The app password is typed
 * hidden, in the server's own terminal.
 */
class MailGmailCommand extends Command
{
    public const HOST = 'smtp.gmail.com';

    public const PORT = 587;

    protected $signature = 'mail:gmail
                            {address? : Alamat Gmail atau Google Workspace yang mengirim email}
                            {--to= : Penerima email uji (bawaan: alamat pengirim)}';

    protected $description = 'Menyambungkan akun Gmail yang mengirim email aplikasi (tautan atur ulang kata sandi)';

    public function handle(): int
    {
        $address = trim((string) ($this->argument('address') ?: $this->ask('Alamat Gmail pengirim', $this->currentAddress())));
        $recipient = trim((string) ($this->option('to') ?: $address));

        foreach ([$address, $recipient] as $email) {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->error("Alamat email tidak valid: {$email}");

                return self::INVALID;
            }
        }

        $this->newLine();
        $this->line("Buat Sandi Aplikasi (App Password) Google untuk <options=bold>{$address}</>:");
        $this->line('  1. Buka https://myaccount.google.com/apppasswords, masuk sebagai alamat itu.');
        $this->line('  2. Bila diminta, aktifkan dulu Verifikasi 2 Langkah di akun itu.');
        $this->line('  3. Buat sandi bernama "Falcon Forms", lalu salin 16 hurufnya (spasi boleh ikut).');
        $this->newLine();

        $appPassword = (string) preg_replace('/\s+/', '', (string) $this->secret('Sandi Aplikasi (16 huruf, tidak tampil saat diketik)'));
        if (! preg_match('/^[a-zA-Z]{16}$/', $appPassword)) {
            $this->error('Sandi Aplikasi Google terdiri dari 16 huruf. Kata sandi biasa akun Google tidak dapat dipakai.');

            return self::INVALID;
        }

        $this->info("Mengirim email uji dari {$address} ke {$recipient}...");

        try {
            $this->sendTestEmail($address, $appPassword, $recipient);
        } catch (TransportExceptionInterface $exception) {
            $this->error('Email uji gagal dikirim: '.$exception->getMessage());
            $this->line('   '.self::hint($exception->getMessage()));
            $this->line('   Setelan email yang lama tidak diubah.');

            return self::FAILURE;
        }

        $settings = [
            'MAIL_MAILER' => 'smtp',
            'MAIL_SCHEME' => 'null',
            'MAIL_HOST' => self::HOST,
            'MAIL_PORT' => (string) self::PORT,
            'MAIL_USERNAME' => "\"{$address}\"",
            'MAIL_PASSWORD' => "\"{$appPassword}\"",
            'MAIL_FROM_ADDRESS' => "\"{$address}\"",
        ];
        $this->writeEnvironment($settings);
        $this->refreshConfigCache(array_keys($settings));

        $this->info("✓ Email uji terkirim ke {$recipient}: periksa kotak masuk (atau folder Spam).");
        $this->info("✓ Tautan atur ulang kata sandi kini dikirim dari {$address}.");

        return self::SUCCESS;
    }

    /**
     * What to do about a test email Gmail did not take.
     */
    public static function hint(string $error): string
    {
        if (str_contains($error, '535') || str_contains($error, 'Username and Password not accepted') || str_contains($error, '534')) {
            return 'Google menolak sandinya: pakai Sandi Aplikasi 16 huruf (bukan kata sandi biasa), dari akun yang Verifikasi 2 Langkah-nya aktif. Akun Google Workspace sekolah mungkin perlu izin admin Workspace untuk Sandi Aplikasi.';
        }

        if (preg_match('/Connection|connect|timed out|getaddrinfo|resolve|refused/i', $error)) {
            return 'Server tidak dapat menjangkau '.self::HOST.':'.self::PORT.'. Pastikan server tersambung ke internet; jaringan sekolah mungkin memblokir port '.self::PORT.'.';
        }

        return 'Periksa alamat pengirim dan Sandi Aplikasinya, lalu jalankan perintah ini lagi.';
    }

    /**
     * Sends the test email with the new account, before anything is saved.
     */
    protected function sendTestEmail(string $address, string $appPassword, string $recipient): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.scheme' => null,
            'mail.mailers.smtp.url' => null,
            'mail.mailers.smtp.host' => self::HOST,
            'mail.mailers.smtp.port' => self::PORT,
            'mail.mailers.smtp.username' => $address,
            'mail.mailers.smtp.password' => $appPassword,
            'mail.from.address' => $address,
        ]);
        Mail::purge('smtp');

        Mail::mailer('smtp')->to($recipient)->send(new MailSetupCheck($address));
    }

    /**
     * Writes the given values in .env, in place of the ones there.
     *
     * @param  array<string, string>  $values
     */
    protected function writeEnvironment(array $values): void
    {
        $path = $this->laravel->environmentFilePath();
        $content = is_file($path) ? (string) file_get_contents($path) : '';

        foreach ($values as $key => $value) {
            $line = "{$key}={$value}";
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

            $content = preg_match($pattern, $content)
                ? (string) preg_replace_callback($pattern, fn (): string => $line, $content)
                : rtrim($content, "\n")."\n{$line}\n";
        }

        file_put_contents($path, $content);
    }

    /**
     * The running server reads its settings from the configuration cache (lan:serve's workers keep
     * the environment they started with), so a cached configuration is built again from the new .env.
     * It is built in a process of its own, without this one's values of the changed keys: those read
     * from .env when this command started would win over the new ones (the environment is immutable).
     *
     * @param  list<string>  $changedKeys
     */
    protected function refreshConfigCache(array $changedKeys): void
    {
        if (! $this->isConfigurationCached()) {
            $this->line('   Jalankan ulang server (lan:serve) agar setelan email baru dipakai.');

            return;
        }

        $result = Process::path(base_path())
            ->env(array_fill_keys($changedKeys, false))
            ->run([PHP_BINARY, 'artisan', 'config:cache']);

        $result->successful()
            ? $this->info('✓ Cache konfigurasi server diperbarui; setelan email baru langsung dipakai.')
            : $this->warn('Cache konfigurasi belum diperbarui. Jalankan: php artisan config:cache');
    }

    protected function isConfigurationCached(): bool
    {
        return $this->laravel->configurationIsCached();
    }

    protected function currentAddress(): ?string
    {
        $address = config('mail.mailers.smtp.username');

        return is_string($address) && filter_var($address, FILTER_VALIDATE_EMAIL) ? $address : null;
    }
}
