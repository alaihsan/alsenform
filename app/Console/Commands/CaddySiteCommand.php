<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CaddySiteCommand extends Command
{
    /**
     * Written at the top of every site this command makes: such a file may be written again.
     */
    public const MARKER = 'Dibuat oleh "php artisan lan:caddy"';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lan:caddy
                            {--write= : Simpan situs ke berkas ini, misalnya /etc/caddy/sites/falcon-forms.caddy}
                            {--https : HTTPS dengan sertifikat lokal Caddy (tls internal); sertifikatnya perlu dipasang di perangkat siswa}
                            {--port='.ServeLanCommand::DEFAULT_PORT.' : Port lan:serve yang diteruskan Caddy}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Buat situs Caddy untuk alamat intranet (http://falcon.intranet tanpa port), agar beberapa aplikasi web berbagi satu server';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $host = strtolower(trim((string) config('app.lan_host')));

        if (! preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $host)) {
            $this->error('LAN_HOST di .env kosong atau tidak valid. Isi dulu, misalnya LAN_HOST=falcon.intranet.');

            return self::INVALID;
        }

        $port = (int) $this->option('port');
        $isHttps = (bool) $this->option('https');
        $site = $this->site($host, $port, $isHttps);
        $path = $this->option('write');

        if (is_string($path) && $path !== '') {
            if (is_file($path) && ! str_contains((string) File::get($path), self::MARKER)) {
                $this->error("{$path} sudah ada dan tidak dibuat oleh lan:caddy: tidak ditimpa. Pilih nama berkas lain.");

                return self::FAILURE;
            }

            File::ensureDirectoryExists(dirname($path));

            if (@file_put_contents($path, $site) === false) {
                $this->error("Tidak dapat menulis {$path}. Jalankan dengan sudo, atau pilih folder yang dapat ditulis.");

                return self::FAILURE;
            }

            $this->info("✓ Situs Caddy disimpan: {$path}");
        } else {
            $this->line($site);
        }

        $publicPort = $isHttps ? 443 : 80;
        $folder = is_string($path) && $path !== '' ? dirname($path) : (PHP_OS_FAMILY === 'Darwin' ? '/opt/homebrew/etc/caddy/sites' : '/etc/caddy/sites');

        $this->newLine();
        $this->line(' <options=bold>Langkah berikutnya</>');
        if (! is_string($path) || $path === '') {
            $this->line('   1. Simpan situs ini: <fg=yellow>php artisan lan:caddy'.($isHttps ? ' --https' : '')." --write={$folder}/falcon-forms.caddy</>");
        } else {
            $this->line('   1. Situs sudah disimpan.');
        }
        $this->line("   2. Di Caddyfile utama, satu baris untuk semua aplikasi: <fg=yellow>import {$folder}/*.caddy</>");
        $this->line('   3. Muat ulang Caddy: <fg=yellow>caddy reload --config <Caddyfile></> (Linux: sudo systemctl reload caddy)');
        $this->line("   4. Di .env: <fg=yellow>LAN_PUBLIC_PORT={$publicPort}</> dan <fg=yellow>APP_URL=".($isHttps ? 'https' : 'http')."://{$host}</>, lalu jalankan ulang php artisan lan:serve.");
        $this->line('   5. Router: satu DNS statis untuk semua aplikasi, *.'.$this->domainOf($host).' → IP server (MikroTik: /ip dns static add regexp="'.$this->wildcardPattern($host).'" address=IP-server).');
        $this->line('   Periksa semuanya: <fg=yellow>php artisan lan:check</>');

        return self::SUCCESS;
    }

    /**
     * The Caddy site of this server: the intranet name on port 80 (or 443) to lan:serve.
     */
    public function site(string $host, int $port, bool $isHttps): string
    {
        $address = ($isHttps ? 'https://' : 'http://').$host;
        $tls = $isHttps ? "\n\t# Sertifikat dari CA lokal Caddy: pasang sertifikat akarnya di perangkat siswa.\n\ttls internal\n" : '';

        return <<<CADDY
            # Falcon Forms. {$this->markerLine()}
            # Siswa membuka {$address}; Caddy meneruskannya ke php artisan lan:serve (port {$port}).
            {$address} {{$tls}
            \t# Paket ExamView (64 MB) dan video soal muat; sama dengan batas lan:serve.
            \trequest_body {
            \t\tmax_size 80MB
            \t}

            \treverse_proxy 127.0.0.1:{$port}
            }

            CADDY;
    }

    protected function markerLine(): string
    {
        return self::MARKER.'; boleh dibuat ulang kapan saja.';
    }

    /**
     * "intranet" of falcon.intranet, "sekolah.lan" of ujian.sekolah.lan.
     */
    protected function domainOf(string $host): string
    {
        return explode('.', $host, 2)[1] ?? $host;
    }

    /**
     * The regular expression for every name of the domain, as MikroTik's static DNS takes it.
     */
    protected function wildcardPattern(string $host): string
    {
        return '.*\\\\.'.str_replace('.', '\\\\.', $this->domainOf($host)).'\\$';
    }
}
