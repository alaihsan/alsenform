<?php

namespace App\Support;

use App\Database\MySqlConnection;
use App\Models\ServerError;

/**
 * What a known kind of server error means and what to do about it, shown with the error in Galat
 * Server: when it happens again, the admin reads the way out there instead of looking it up.
 */
class ServerErrorAdvice
{
    /**
     * @return array{title: string, explanation: string, steps: list<array{text: string, command?: string}>}|null
     */
    public static function for(ServerError $serverError): ?array
    {
        $exception = class_basename($serverError->exception);

        if ($exception === 'QueryException' && preg_match('/SQLSTATE\[(40001|40P01)\]|Deadlock found|deadlock detected/i', $serverError->message)) {
            return self::deadlock($serverError);
        }

        if (in_array($exception, ['CommandNotFoundException', 'NamespaceNotFoundException'], true)) {
            return str_contains($serverError->message, '"boost"') ? self::boostIsOff() : self::unknownCommand();
        }

        return null;
    }

    /**
     * @return array{title: string, explanation: string, steps: list<array{text: string, command?: string}>}
     */
    protected static function deadlock(ServerError $serverError): array
    {
        $explanation = 'Saat banyak siswa mengerjakan ujian bersamaan, dua perintah database bisa saling menunggu data yang sedang dipegang yang lain, lalu MySQL membatalkan salah satunya. '
            .'Server mengulang perintah yang dibatalkan itu otomatis hingga '.MySqlConnection::DEADLOCK_RETRIES.' kali; galat ini hanya tercatat bila semuanya tetap gagal.';

        if (preg_match('#^/forms/[^/?]+/status(\?|$)#', (string) $serverError->last_url)) {
            $explanation .= ' Yang kena di sini adalah cek status ujian berkala: cek itu tidak menyimpan jawaban, jadi tidak ada jawaban yang hilang, dan halaman ujian mencobanya lagi pada cek berikutnya.';
        }

        $redis = preg_match('/[`"]cache(_locks)?[`"]/', $serverError->message)
            ? ['text' => 'Galat di tabel cache berarti cache sedang memakai database karena Redis mati. Buka Kesehatan Server; bila Cache tertulis "Redis mati", nyalakan Redis di komputer server:', 'command' => 'brew services start redis']
            : ['text' => 'Pastikan Redis menyala (Kesehatan Server, bagian Cache) agar cache tidak ikut membebani database saat ujian. Bila mati, nyalakan di komputer server:', 'command' => 'brew services start redis'];

        return [
            'title' => 'Deadlock database: dua permintaan berebut data yang sama',
            'explanation' => $explanation,
            'steps' => [
                ['text' => 'Hanya sesekali saat ujian ramai: tidak perlu tindakan. Klik Tandai selesai; bila terjadi lagi, galat ini terbuka sendiri.'],
                $redis,
                ['text' => 'Tetap muncul berkali-kali dalam satu ujian: kirim kode ERR-... dan jam ujiannya ke pengembang.'],
            ],
        ];
    }

    /**
     * @return array{title: string, explanation: string, steps: list<array{text: string, command?: string}>}
     */
    protected static function boostIsOff(): array
    {
        return [
            'title' => 'Bukan gangguan aplikasi: Laravel Boost mati di server produksi',
            'explanation' => 'Laravel Boost adalah alat bantu AI untuk pengembang (Codex, OpenCode). Boost sengaja mati selama APP_ENV=production, sehingga perintah php artisan boost:... tidak ada. Siswa dan guru tidak terdampak.',
            'steps' => [
                ['text' => 'Codex dan OpenCode di proyek ini (.codex/config.toml, opencode.json) menjalankan Boost dengan --env=local, yang menyalakannya hanya di proses alat itu. Bila galat ini muncul lagi (misalnya setelah php artisan boost:install menulis ulang kedua berkas itu), pastikan perintahnya:', 'command' => 'php artisan boost:mcp --env=local'],
                ['text' => 'Perintah Boost yang Anda jalankan sendiri juga perlu --env=local, misalnya:', 'command' => 'php artisan boost:update --env=local'],
                ['text' => 'Jangan mengubah APP_ENV atau APP_DEBUG di .env untuk ini: siswa akan melihat rincian galat, dan skrip Boost ikut termuat di setiap halaman.'],
                ['text' => 'Lalu klik Tandai selesai.'],
            ],
        ];
    }

    /**
     * @return array{title: string, explanation: string, steps: list<array{text: string, command?: string}>}
     */
    protected static function unknownCommand(): array
    {
        return [
            'title' => 'Bukan gangguan aplikasi: perintah artisan itu tidak ada',
            'explanation' => 'Seseorang atau sebuah alat menjalankan perintah php artisan yang tidak dikenal, biasanya karena salah ketik. Siswa dan guru tidak terdampak.',
            'steps' => [
                ['text' => 'Lihat perintah yang ada:', 'command' => 'php artisan list'],
                ['text' => 'Lalu klik Tandai selesai.'],
            ],
        ];
    }
}
