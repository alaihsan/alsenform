<?php

namespace App\Services;

use App\Support\DocxWriter;

/**
 * The Word template teachers download to write questions for the DOCX import. It explains the
 * rules and contains one working example of every question type; DocxImportService reads
 * everything below the "MULAI SOAL" line, so the template itself imports cleanly.
 */
class DocxImportTemplate
{
    protected const INDIGO = '4F46E5';

    protected const MUTED = '64748B';

    protected const NOTE = '94A3B8';

    public function filename(): string
    {
        return 'template_import_soal_alsenform.docx';
    }

    public function save(string $path): void
    {
        $doc = new DocxWriter;

        $this->introduction($doc);
        $this->rules($doc);
        $this->typeReference($doc);
        $this->optionalLines($doc);
        $this->examples($doc);

        $doc->save($path, 'Templat Import Soal Alsenform');
    }

    protected function introduction(DocxWriter $doc): void
    {
        $doc->paragraph([['text' => 'TEMPLAT IMPORT SOAL ALSENFORM', 'bold' => true, 'size' => 18, 'color' => self::INDIGO]], ['align' => 'center', 'after' => 40]);
        $doc->paragraph([['text' => 'Panduan dan contoh semua tipe soal yang dapat diimpor dari Word (.docx)', 'color' => self::MUTED, 'size' => 11]], ['align' => 'center', 'after' => 240]);
        $doc->paragraph([
            ['text' => 'Cara pakai: ', 'bold' => true],
            ['text' => 'baca aturan di bawah, lalu hapus contoh soal di bawah baris '],
            ['text' => 'MULAI SOAL', 'bold' => true],
            ['text' => ' dan ganti dengan soal Anda. Semua yang berada di atas baris MULAI SOAL (termasuk halaman panduan ini) tidak ikut diimpor, jadi jangan hapus baris tersebut.'],
        ], ['fill' => 'EEF2FF', 'border' => 'C7D2FE', 'after' => 240]);
    }

    protected function rules(DocxWriter $doc): void
    {
        $this->heading($doc, 'A. Aturan Penulisan');

        $rules = [
            [['text' => 'Nomor soal diketik manual ', 'bold' => true], 'di awal soal, misalnya "1." atau "1)". Jangan memakai penomoran atau bullet otomatis Word, karena nomor otomatis tidak ikut terbaca.'],
            [['text' => 'Setiap soal diakhiri satu baris kunci ', 'bold' => true], '"Jawaban: ..." (boleh juga "Kunci: ..."). Baris ini menjadi pemisah antarsoal. Soal tanpa kunci (survei atau esai tanpa contoh) tetap ditulis "Jawaban: -".'],
            [['text' => 'Pilihan jawaban ', 'bold' => true], 'diketik dengan huruf "A." sampai "E.", masing-masing di paragraf sendiri.'],
            [['text' => 'Tipe soal ', 'bold' => true], 'ditulis dengan baris "Tipe: ...". Untuk Pilihan Ganda dan Isian Singkat baris ini boleh tidak ditulis: soal dengan pilihan A–E otomatis menjadi Pilihan Ganda, dan soal tanpa pilihan menjadi Isian Singkat.'],
            [['text' => 'Satu baris satu paragraf: ', 'bold' => true], 'teks soal, setiap pilihan, baris Tipe/Poin/Wajib/Skala, dan baris Jawaban masing-masing diakhiri tombol Enter.'],
            [['text' => 'Gambar ', 'bold' => true], '(Insert › Pictures) diletakkan di bawah teks soal, sebelum baris Jawaban. Gambar ikut terimpor ke soal tersebut.'],
            [['text' => 'Rumus matematika ', 'bold' => true], 'ditulis di antara tanda dolar, contoh: $x^2 + 2x + 1$.'],
            [['text' => 'Catatan pribadi ', 'bold' => true], 'boleh ditulis di baris yang diawali "//". Baris seperti ini tidak ikut diimpor.'],
        ];

        foreach ($rules as $rule) {
            $doc->paragraph([['text' => '•  ', 'color' => self::INDIGO, 'bold' => true], ...array_map(fn ($run) => is_array($run) ? $run : ['text' => $run], $rule)], ['indent' => 240, 'after' => 60]);
        }
    }

    protected function typeReference(DocxWriter $doc): void
    {
        $this->heading($doc, 'B. Daftar Tipe Soal');

        $code = fn (string $text): array => ['text' => $text, 'font' => 'Consolas', 'color' => '3730A3'];
        $doc->table([
            ['Tipe di Alsenform', 'Baris Tipe', 'Bentuk soal', 'Contoh kunci'],
            ['Pilihan Ganda', [$code('Tipe: Pilihan Ganda'), ['text' => ' (boleh tidak ditulis)', 'color' => self::MUTED]], 'Pilihan A–E, satu jawaban benar', [$code('Jawaban: B')]],
            ['Pilihan Ganda Kompleks (Kotak Centang)', [$code('Tipe: Kotak Centang')], 'Pilihan A–E, jawaban benar lebih dari satu', [$code('Jawaban: A, C')]],
            ['Drop-down', [$code('Tipe: Drop-down')], 'Pilihan A–E, tampil sebagai daftar pilihan', [$code('Jawaban: C')]],
            ['Benar / Salah', [$code('Tipe: Benar Salah')], 'Satu pernyataan, tanpa pilihan', [$code('Jawaban: Salah')]],
            ['Isian Singkat', [$code('Tipe: Isian Singkat'), ['text' => ' (boleh tidak ditulis)', 'color' => self::MUTED]], 'Tanpa pilihan. Beberapa jawaban benar dipisah "|", angka boleh "3,5", rentang "9.5..10.5"', [$code('Jawaban: Bandung | Kota Bandung')]],
            ['Uraian / Esai', [$code('Tipe: Uraian')], 'Tanpa pilihan, dinilai guru. Jawaban berisi contoh jawaban atau rubrik (satu paragraf)', [$code('Jawaban: Air menguap ...')]],
            ['Benar / Salah beberapa pernyataan', [$code('Tipe: Benar Salah')], 'Tabel 3 kolom: No | Pernyataan | Benar/Salah', [$code('Jawaban: B, S, B')]],
            ['Menjodohkan', [$code('Tipe: Menjodohkan')], 'Tabel 3 kolom: No | Soal | Pasangan. Huruf kunci = urutan baris kolom Pasangan (A, B, C, ...)', [$code('Jawaban: 1C, 2A, 3B')]],
            ['Kisi Pilihan Ganda', [$code('Tipe: Kisi Pilihan Ganda')], 'Tabel: baris pertama berisi judul kolom pilihan, kolom pertama berisi pernyataan. Tandai jawaban benar dengan X (satu per baris)', [$code('Jawaban: lihat tabel')]],
            ['Kisi Kotak Centang', [$code('Tipe: Kisi Kotak Centang')], 'Seperti kisi pilihan ganda, tetapi boleh lebih dari satu X per baris', [$code('Jawaban: lihat tabel')]],
            ['Skala Linear', [$code('Tipe: Skala Linear')], 'Tanpa pilihan. Atur rentang dengan "Skala: 1-5" (0 sampai 10)', [$code('Jawaban: -')]],
            ['Rating (bintang)', [$code('Tipe: Rating')], 'Tanpa pilihan. Jumlah bintang diatur dengan "Skala: 1-5"', [$code('Jawaban: -')]],
            ['Tanggal', [$code('Tipe: Tanggal')], 'Tanpa pilihan. Kunci ditulis hari-bulan-tahun', [$code('Jawaban: 17-08-1945')]],
            ['Waktu', [$code('Tipe: Waktu')], 'Tanpa pilihan. Kunci ditulis jam:menit', [$code('Jawaban: 07:00')]],
        ], ['header' => true, 'widths' => [2300, 2450, 3088, 1800], 'size' => 9.5]);
    }

    protected function optionalLines(DocxWriter $doc): void
    {
        $this->heading($doc, 'C. Baris Tambahan (Opsional)');
        $doc->paragraph('Baris berikut boleh ditulis di mana saja di dalam soal sebelum baris Jawaban (disarankan tepat di bawah teks soal).', ['after' => 100]);

        $code = fn (string $text): array => ['text' => $text, 'font' => 'Consolas', 'color' => '3730A3'];
        $doc->table([
            ['Baris', 'Arti', 'Contoh'],
            [[$code('Poin:')], 'Bobot nilai soal. Bawaan 10. Tulis 0 untuk soal survei.', [$code('Poin: 5')]],
            [[$code('Wajib:')], 'Soal wajib dijawab sebelum jawaban bisa dikirim. Bawaan: Tidak.', [$code('Wajib: Ya')]],
            [[$code('Skala:')], 'Rentang Skala Linear atau jumlah bintang Rating. Bawaan 1-5.', [$code('Skala: 1-10')]],
        ], ['header' => true, 'widths' => [1600, 5838, 2200], 'size' => 9.5]);
    }

    protected function examples(DocxWriter $doc): void
    {
        $this->heading($doc, 'D. Contoh Soal', newPage: true);
        $doc->paragraph('Contoh di bawah dapat langsung diimpor untuk mencoba. Hapus atau ganti dengan soal Anda sendiri. Semua yang ada di bawah baris berikut dibaca sebagai soal.', ['after' => 160]);
        $doc->paragraph([['text' => '===== MULAI SOAL =====', 'bold' => true, 'color' => self::INDIGO, 'size' => 12]], ['align' => 'center', 'fill' => 'EEF2FF', 'border' => 'C7D2FE', 'after' => 240]);

        $this->example($doc, 'Pilihan Ganda — baris Tipe boleh tidak ditulis', [
            '1. Ibu kota negara Republik Indonesia adalah ...',
            'Poin: 10',
            'Wajib: Ya',
            'A. Surabaya', 'B. Jakarta', 'C. Bandung', 'D. Medan',
            'Jawaban: B',
        ]);

        $this->example($doc, 'Pilihan Ganda Kompleks (Kotak Centang) — kunci lebih dari satu huruf', [
            '2. Manakah yang termasuk bilangan prima? (jawaban boleh lebih dari satu)',
            'Tipe: Kotak Centang',
            'A. 2', 'B. 4', 'C. 5', 'D. 9',
            'Jawaban: A, C',
        ]);

        $this->example($doc, 'Drop-down', [
            '3. Planet terbesar di tata surya kita adalah ...',
            'Tipe: Drop-down',
            'A. Mars', 'B. Bumi', 'C. Jupiter', 'D. Venus',
            'Jawaban: C',
        ]);

        $this->example($doc, 'Benar / Salah satu pernyataan — kunci "Benar" atau "Salah"', [
            '4. Matahari terbit dari arah barat.',
            'Tipe: Benar Salah',
            'Jawaban: Salah',
        ]);

        $this->example($doc, 'Isian Singkat — beberapa jawaban benar dipisah tanda |', [
            '5. Sebutkan ibu kota Provinsi Jawa Barat!',
            'Jawaban: Bandung | Kota Bandung',
        ]);

        $this->example($doc, 'Isian Singkat angka — rentang min..maks diterima sebagai benar', [
            '6. Panjang meja hasil pengukuran adalah ... cm (toleransi 0,5 cm)',
            'Tipe: Isian Singkat',
            'Poin: 5',
            'Jawaban: 119.5..120.5',
        ]);

        $this->example($doc, 'Uraian / Esai — Jawaban berisi contoh jawaban atau rubrik untuk guru', [
            '7. Jelaskan secara singkat proses terjadinya hujan!',
            'Tipe: Uraian',
            'Poin: 20',
            'Jawaban: Air menguap karena panas matahari, uap air mengembun menjadi awan, lalu jatuh kembali sebagai hujan.',
        ]);

        $this->example($doc, 'Benar / Salah beberapa pernyataan — tabel 3 kolom, kunci urut sesuai baris (B = Benar, S = Salah)', [
            '8. Tentukan Benar atau Salah setiap pernyataan berikut!',
            'Tipe: Benar Salah',
        ], [
            ['No', 'Pernyataan', 'Benar/Salah'],
            ['1', 'Air mendidih pada suhu 100 °C di permukaan laut.', ''],
            ['2', 'Bulan memancarkan cahayanya sendiri.', ''],
            ['3', 'Tumbuhan hijau melakukan fotosintesis.', ''],
        ], 'Jawaban: B, S, B', [700, 6938, 2000]);

        $this->example($doc, 'Menjodohkan — kunci berisi nomor soal + huruf baris pasangan (A = baris pertama kolom kanan)', [
            '9. Pasangkan negara dengan ibu kotanya!',
            'Tipe: Menjodohkan',
        ], [
            ['No', 'Negara', 'Ibu Kota'],
            ['1', 'Jepang', 'Paris'],
            ['2', 'Prancis', 'Kairo'],
            ['3', 'Mesir', 'Tokyo'],
        ], 'Jawaban: 1C, 2A, 3B', [700, 4469, 4469]);

        $this->example($doc, 'Kisi Pilihan Ganda — tandai satu jawaban benar per baris dengan X', [
            '10. Kelompokkan hewan berikut sesuai golongannya!',
            'Tipe: Kisi Pilihan Ganda',
        ], [
            ['Hewan', 'Mamalia', 'Burung', 'Reptil'],
            ['Paus', 'X', '', ''],
            ['Elang', '', 'X', ''],
            ['Kadal', '', '', 'X'],
        ], 'Jawaban: lihat tabel', [3038, 2200, 2200, 2200]);

        $this->example($doc, 'Kisi Kotak Centang — boleh lebih dari satu X per baris', [
            '11. Centang semua ciri yang dimiliki setiap hewan!',
            'Tipe: Kisi Kotak Centang',
        ], [
            ['Hewan', 'Berkaki empat', 'Menyusui', 'Bertelur'],
            ['Kucing', 'X', 'X', ''],
            ['Ayam', '', '', 'X'],
            ['Kambing', 'X', 'X', ''],
        ], 'Jawaban: lihat tabel', [3038, 2200, 2200, 2200]);

        $this->example($doc, 'Skala Linear — untuk survei, tanpa kunci dan tanpa poin', [
            '12. Seberapa paham kamu dengan materi hari ini?',
            'Tipe: Skala Linear',
            'Skala: 1-5',
            'Poin: 0',
            'Jawaban: -',
        ]);

        $this->example($doc, 'Rating (bintang)', [
            '13. Beri nilai untuk cara guru menjelaskan materi.',
            'Tipe: Rating',
            'Skala: 1-5',
            'Poin: 0',
            'Jawaban: -',
        ]);

        $this->example($doc, 'Tanggal — kunci ditulis hari-bulan-tahun', [
            '14. Kapan Proklamasi Kemerdekaan Indonesia dibacakan?',
            'Tipe: Tanggal',
            'Jawaban: 17-08-1945',
        ]);

        $this->example($doc, 'Waktu — kunci ditulis jam:menit', [
            '15. Pukul berapa upacara bendera hari Senin dimulai?',
            'Tipe: Waktu',
            'Jawaban: 07:00',
        ]);
    }

    /**
     * One example question: a "//" note (not imported), its lines, an optional table and the key.
     *
     * @param  list<string>  $lines
     * @param  list<list<string>>|null  $table
     * @param  list<int>  $widths
     */
    protected function example(DocxWriter $doc, string $note, array $lines, ?array $table = null, ?string $answer = null, array $widths = []): void
    {
        $doc->paragraph([['text' => '// '.$note, 'italic' => true, 'color' => self::NOTE, 'size' => 9.5]], ['before' => 120, 'after' => 40, 'keepNext' => true]);

        foreach ($lines as $index => $line) {
            $isMeta = (bool) preg_match('/^(Tipe|Poin|Wajib|Skala|Jawaban):/', $line);
            $isAnswer = str_starts_with($line, 'Jawaban:');
            // Keep a question on one page, but let the page break after its answer line.
            $doc->paragraph([[
                'text' => $line,
                'bold' => $index === 0 || $isAnswer,
                'color' => $isMeta && ! $isAnswer ? '3730A3' : null,
            ]], ['after' => 40, 'keepNext' => ! $isAnswer]);
        }

        if ($table !== null) {
            $doc->table($table, ['header' => true, 'headerFill' => 'E0E7FF', 'headerColor' => '312E81', 'widths' => $widths]);
        }

        if ($answer !== null) {
            $doc->paragraph([['text' => $answer, 'bold' => true]], ['after' => 40]);
        }
    }

    protected function heading(DocxWriter $doc, string $text, bool $newPage = false): void
    {
        $doc->paragraph([['text' => $text, 'bold' => true, 'size' => 13, 'color' => '1E1B4B']], [
            'before' => $newPage ? 0 : 240,
            'after' => 100,
            'keepNext' => true,
            'pageBreakBefore' => $newPage,
        ]);
    }
}
