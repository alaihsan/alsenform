<?php

namespace App\Support;

/**
 * Why a page could not be shown in the browser, instead of leaving it blank white. The page guard
 * of resources/views/app.blade.php shows the code, what it means and what to do, and reports it
 * to the activity report (Laporan) with the device and browser it happened on.
 */
enum PageFailure: string
{
    case JavascriptOff = 'FF-101';
    case OldBrowser = 'FF-102';
    case AssetUnreachable = 'FF-201';
    case AssetMissing = 'FF-202';
    case StartError = 'FF-301';
    case RenderError = 'FF-302';
    case SlowLoad = 'FF-401';

    public function title(): string
    {
        return match ($this) {
            self::JavascriptOff => 'JavaScript dimatikan',
            self::OldBrowser => 'Browser terlalu lama',
            self::AssetUnreachable => 'Berkas aplikasi gagal diunduh',
            self::AssetMissing => 'Berkas aplikasi tidak ditemukan di server',
            self::StartError => 'Aplikasi gagal dimulai di browser ini',
            self::RenderError => 'Halaman gagal ditampilkan',
            self::SlowLoad => 'Halaman masih dimuat',
        };
    }

    public function explanation(): string
    {
        return match ($this) {
            self::JavascriptOff => 'Falcon Forms membutuhkan JavaScript, tetapi JavaScript di browser ini dimatikan.',
            self::OldBrowser => 'Versi browser ini belum dapat menjalankan Falcon Forms. Dibutuhkan Chrome 73, Safari/iOS 12.2, Firefox 68, Edge 79, atau yang lebih baru.',
            self::AssetUnreachable => 'Koneksi ke server terputus saat berkas tampilan aplikasi diunduh, atau server sedang sangat sibuk.',
            self::AssetMissing => 'Server tidak memiliki berkas tampilan yang diminta halaman ini. Biasanya terjadi tepat saat aplikasi sedang diperbarui.',
            self::StartError => 'Terjadi kesalahan di browser saat membuka halaman.',
            self::RenderError => 'Terjadi kesalahan di browser saat menampilkan halaman ini.',
            self::SlowLoad => 'Jaringan sangat lambat atau server sedang sibuk. Halaman akan muncul sendiri begitu selesai dimuat.',
        };
    }

    /**
     * What the person in front of the screen can do, in order.
     *
     * @return list<string>
     */
    public function steps(): array
    {
        return match ($this) {
            self::JavascriptOff => [
                'Nyalakan JavaScript di pengaturan browser, lalu buka ulang halaman ini.',
                'Atau buka dengan browser lain, misalnya Chrome.',
            ],
            self::OldBrowser => [
                'Perbarui browser (Chrome lewat Play Store, Safari lewat Pengaturan → Umum → Pembaruan Perangkat Lunak).',
                'Atau buka dengan Chrome versi terbaru, atau perangkat lain.',
            ],
            self::AssetUnreachable => [
                'Pastikan perangkat masih tersambung ke Wi-Fi sekolah, lalu tekan Muat ulang.',
                'Bila sering terjadi, buka Cek Koneksi untuk mengukur sinyal perangkat ini.',
            ],
            self::AssetMissing => [
                'Tunggu sebentar lalu tekan Muat ulang.',
                'Bila tetap muncul, minta admin menjalankan "npm run build" di server, lalu menyalakan ulang server.',
            ],
            self::StartError, self::RenderError => [
                'Tekan Muat ulang.',
                'Bila tetap muncul, tutup tab ini dan buka alamatnya lagi, atau coba browser lain (Chrome).',
            ],
            self::SlowLoad => [
                'Tunggu sebentar, atau tekan Muat ulang.',
                'Bila sering terjadi, dekatkan perangkat ke router atau buka Cek Koneksi.',
            ],
        };
    }

    /**
     * Every code with its texts, for the page guard.
     *
     * @return array<string, array{title: string, text: string, steps: list<string>}>
     */
    public static function catalog(): array
    {
        $catalog = [];
        foreach (self::cases() as $failure) {
            $catalog[$failure->value] = ['title' => $failure->title(), 'text' => $failure->explanation(), 'steps' => $failure->steps()];
        }

        return $catalog;
    }
}
