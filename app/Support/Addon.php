<?php

namespace App\Support;

/**
 * The add-ons a teacher can install, like add-ons in Google Workspace. Installed for one teacher
 * (or by an admin for every teacher), an add-on is then switched on or off per exam; its effects
 * (panels, Excel sheets, actions) only exist while it is on. An add-on that is not per exam
 * (Rekap Kuis) is simply there while installed.
 */
enum Addon: string
{
    case ItemAnalysis = 'item-analysis';
    case ExamToken = 'exam-token';
    case Remedial = 'remedial';
    case Misconception = 'misconception';
    case FormDesigner = 'form-designer';
    case QuizRecap = 'quiz-recap';

    public function label(): string
    {
        return match ($this) {
            self::ItemAnalysis => 'Analisis Butir Soal',
            self::ExamToken => 'Token Ujian',
            self::Remedial => 'Remedial & Pengayaan',
            self::Misconception => 'Peta Miskonsepsi',
            self::FormDesigner => 'Desainer Formulir',
            self::QuizRecap => 'Rekap Kuis',
        };
    }

    public function tagline(): string
    {
        return match ($this) {
            self::ItemAnalysis => 'Tingkat kesukaran, daya pembeda dan pengecoh tiap soal, dengan saran Dipakai, Revisi atau Ganti.',
            self::ExamToken => 'Siswa baru bisa mulai setelah mengetik token 6 huruf dari pengawas. Token berganti setiap 15 menit dan berlaku paling lama 20 menit, jadi token yang bocor ke luar ruang ujian cepat kedaluwarsa.',
            self::Remedial => 'Daftar siswa di bawah KKM/KKTP tersusun otomatis; ujian remedial dan pengayaan dibuat dalam beberapa klik.',
            self::Misconception => 'Bedakan siswa yang menebak, yang belum paham, dan yang yakin pada konsep keliru, dari tombol Ragu-ragu.',
            self::FormDesigner => 'Beri ujian wajah lembaga Anda: tema siap pakai, warna, huruf, gambar header dan logo, sampai kode tampilan (CSS) sendiri.',
            self::QuizRecap => 'Nilai semua kuis Anda dalam satu tabel per kelas, siap diunduh sebagai Excel.',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ItemAnalysis => 'Setelah siswa mengirim jawaban, setiap soal pilihan dianalisis seperti di buku Arikunto: berapa siswa yang benar (P), seberapa baik soal membedakan siswa bernilai tinggi dan rendah (D, kelompok atas dan bawah 27%), dan pengecoh mana yang tidak dipilih siapa pun. Setiap soal mendapat keputusan beserta alasannya.',
            self::ExamToken => 'Pengawas menampilkan token di kelas (bisa layar penuh untuk proyektor) dan siswa mengetiknya di layar konfirmasi ujian. Tanpa token yang berlaku ujian tidak dapat dimulai, jadi ujian tidak dapat dikerjakan dari luar ruangan atau sebelum waktunya. Token berganti setiap 15 menit (menit :00, :15, :30 dan :45); token sebelumnya masih diterima 5 menit lagi agar siswa yang sedang mengetik tidak tertolak. Siswa yang sudah mulai tidak perlu mengetik token lagi saat halaman dimuat ulang. Guru pemilik dan kolaborator tidak perlu token saat mencoba ujiannya.',
            self::Remedial => 'Setelah ulangan, siswa dibagi menjadi Belum Tuntas (di bawah KKM/KKTP) dan Tuntas menurut nilai terbaiknya. Ujian remedial dibuat dari soal yang dijawab salah oleh siswa remedial, atau dari soal lain setopik di Bank Soal; ujian pengayaan dari soal sulit setopik. Ujian tindak lanjut dibuat sebagai draf dan hanya dapat dikerjakan siswa yang dipilih. Nilainya dicatat terpisah: nilai asli tidak berubah, dan laporan Excel menampilkan nilai asli, nilai remedial dan nilai akhir berdampingan.',
            self::Misconception => 'Memakai metode CRI (Certainty of Response Index). Saat ujian, siswa menandai soal yang belum diyakininya dengan tombol Ragu-ragu. Setiap jawaban lalu masuk satu dari empat kelompok: benar dan yakin (paham), benar tapi ragu (menebak), salah dan ragu atau kosong (belum paham), serta salah tapi yakin (miskonsepsi). Miskonsepsi berbeda dari belum paham: siswa merasa benar, jadi mengulang materi saja tidak cukup. Soal dengan banyak miskonsepsi ditandai, lengkap dengan jawaban keliru yang paling diyakini siswa. Hasilnya bermakna bila siswa memakai tombol Ragu-ragu dengan jujur; panel memberi tahu bila terlalu sedikit siswa memakainya.',
            self::FormDesigner => 'Mulai dari salah satu tema siap pakai (Klasik Falcon, Ungu Tua, Malam, Madrasah, Ceria SD, Kertas Ujian, Biru Tegas, Kontras Tinggi, dan lainnya), lalu atur sendiri warna aksen, latar (warna atau foto dengan peredup), warna dan transparansi kartu, warna teks, gambar header di atas judul, logo sekolah, huruf judul dan soal, ukuran teks, sudut dan bayangan kartu, bentuk tombol, serta lebar halaman. Guru yang mahir dapat menambah CSS sendiri; CSS itu hanya berlaku di halaman ujian ini dan tidak dapat memuat apa pun dari luar server sekolah. Desain yang sama tampil di editor guru, di semua halaman siswa (layar mulai, soal, selesai, hasil) dan di kartu ujian di beranda. Mematikan add-on mengembalikan tampilan biasa tanpa menghapus desainnya.',
            self::QuizRecap => 'Halaman Rekap Kuis menyusun nilai siswa dari semua kuis milik Anda: satu baris per siswa dan satu kolom per kuis, urut dari kuis paling lama. Isinya nilai terbaik tiap siswa (0–100); kosong bila siswa belum mengerjakan. Pilih kelas untuk melihat semua siswa kelas itu, termasuk yang belum mengerjakan, lalu unduh tabelnya sebagai berkas Excel. Formulir yang tidak dinilai (survei) tidak ikut.',
        };
    }

    /**
     * Lucide icon name, drawn by the page.
     */
    public function icon(): string
    {
        return match ($this) {
            self::ItemAnalysis => 'flask-conical',
            self::ExamToken => 'key-round',
            self::Misconception => 'brain',
            self::Remedial => 'graduation-cap',
            self::FormDesigner => 'paintbrush',
            self::QuizRecap => 'sheet',
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
                'Tab Jawaban: panel Analisis Butir Soal di bawah rekap nilai.',
                'Laporan Excel: lembar ketiga "Analisis Butir Soal".',
                'Tombol "Jadikan label kesulitan" untuk mengisi label tingkat kesulitan di Bank Soal.',
            ],
            self::ExamToken => [
                'Tab Keamanan: token yang berlaku, hitung mundur pergantiannya, dan tampilan layar penuh untuk proyektor.',
                'Layar konfirmasi ujian: kolom Token yang wajib diisi siswa sebelum "Kerjakan Sekarang".',
            ],
            self::Remedial => [
                'Tab Jawaban: panel Remedial & Pengayaan berisi siswa Belum Tuntas dan Tuntas beserta nilai tindak lanjutnya.',
                'Tombol "Buat ujian remedial" dan "Buat ujian pengayaan": ujian baru (draf) khusus siswa yang dipilih.',
                'Laporan Excel: lembar "Remedial & Pengayaan" dengan nilai asli, nilai remedial dan nilai akhir.',
            ],
            self::Misconception => [
                'Tab Jawaban: panel Peta Miskonsepsi berisi empat kelompok jawaban per soal dan jawaban keliru yang paling diyakini.',
                'Laporan Excel: lembar "Peta Miskonsepsi" per soal dan per siswa.',
            ],
            self::FormDesigner => [
                'Editor: tombol palet membuka Desainer Formulir dengan pratinjau langsung (tema, warna, huruf, gambar, bentuk, CSS).',
                'Halaman siswa: layar mulai, soal, layar selesai dan halaman hasil memakai desain ujian.',
                'Beranda guru dan siswa: kartu ujian memakai warna dan gambar header desainnya.',
            ],
            self::QuizRecap => [
                'Menu Aplikasi di Beranda: ikon Rekap Kuis yang membuka halaman rekap nilai.',
                'Halaman Rekap Kuis: filter kelas, tabel nilai per kuis, dan tombol unduh Excel (XLSX).',
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
            self::ExamToken => [
                'Hanya ujian dan jam server sekolah: token dihitung di server, tidak membaca data siswa.',
            ],
            self::Remedial => [
                'Nilai siswa di ujian Anda dan KKM/KKTP di profil Anda.',
                'Soal Bank Soal Anda beserta label topik dan tingkat kesulitannya.',
            ],
            self::Misconception => [
                'Jawaban siswa di ujian Anda dan soal yang mereka tandai Ragu-ragu.',
            ],
            self::FormDesigner => [
                'Hanya pengaturan tampilan ujian dan gambar yang Anda unggah ke server sekolah; tidak membaca jawaban siswa.',
            ],
            self::QuizRecap => [
                'Nilai siswa di kuis milik Anda, serta nama dan kelas siswa.',
            ],
        };
    }

    /**
     * Whether an installed add-on is on for an exam that has not switched it either way.
     */
    public function onByDefault(): bool
    {
        return match ($this) {
            self::ItemAnalysis, self::Remedial, self::Misconception, self::FormDesigner, self::QuizRecap => true,
            // Students could not start an exam without a token: switched on per exam by the teacher.
            self::ExamToken => false,
        };
    }

    /**
     * Whether the add-on is switched on or off per exam; one that is not works while installed.
     */
    public function perExam(): bool
    {
        return $this !== self::QuizRecap;
    }
}
