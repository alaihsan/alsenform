<?php

namespace App\Support;

/**
 * The add-ons a teacher can install, like add-ons in Google Workspace. Installed for one teacher
 * (or by an admin for every teacher), an add-on is then switched on or off per exam; its effects
 * (panels, Excel sheets, actions) only exist while it is on.
 */
enum Addon: string
{
    case ItemAnalysis = 'item-analysis';

    public function label(): string
    {
        return match ($this) {
            self::ItemAnalysis => 'Analisis Butir Soal',
        };
    }

    public function tagline(): string
    {
        return match ($this) {
            self::ItemAnalysis => 'Tingkat kesukaran, daya pembeda dan pengecoh tiap soal, dengan saran Dipakai, Revisi atau Ganti.',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ItemAnalysis => 'Setelah siswa mengirim jawaban, setiap soal pilihan dianalisis seperti di buku Arikunto: berapa siswa yang benar (P), seberapa baik soal membedakan siswa bernilai tinggi dan rendah (D, kelompok atas dan bawah 27%), dan pengecoh mana yang tidak dipilih siapa pun. Setiap soal mendapat keputusan beserta alasannya.',
        };
    }

    /**
     * Lucide icon name, drawn by the page.
     */
    public function icon(): string
    {
        return match ($this) {
            self::ItemAnalysis => 'flask-conical',
        };
    }

    /**
     * What the add-on adds to the app while it is on: shown before installing.
     *
     * @return list<string>
     */
    public function adds(): array
    {
        return match ($this) {
            self::ItemAnalysis => [
                'Tab Responses: panel Analisis Butir Soal di bawah rekap nilai.',
                'Laporan Excel: lembar ketiga "Analisis Butir Soal".',
                'Tombol "Jadikan label kesulitan" untuk mengisi label tingkat kesulitan di Bank Soal.',
            ],
        };
    }

    /**
     * What the add-on reads, like the permissions of a Google Workspace add-on.
     *
     * @return list<string>
     */
    public function reads(): array
    {
        return match ($this) {
            self::ItemAnalysis => [
                'Soal dan kunci jawaban ujian Anda.',
                'Jawaban siswa di ujian Anda (tidak dikirim ke mana pun; semua dihitung di server sekolah).',
            ],
        };
    }

    /**
     * Whether an installed add-on is on for an exam that has not switched it either way.
     */
    public function onByDefault(): bool
    {
        return match ($this) {
            self::ItemAnalysis => true,
        };
    }
}
