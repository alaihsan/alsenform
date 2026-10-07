<?php

namespace App\Services;

use App\Support\DocxWriter;

/**
 * The Word template teachers download to write questions for the DOCX import: one working sample
 * of every question type and nothing else. The same samples, with their explanation, are shown
 * in the Help page (see guide()), so the template and the guide never disagree.
 */
class DocxImportTemplate
{
    protected const META_COLOR = '3730A3';

    public function filename(): string
    {
        return 'template_import_soal_alsenform.docx';
    }

    public function save(string $path): void
    {
        $doc = new DocxWriter;

        foreach ($this->samples() as $index => $sample) {
            $this->writeSample($doc, $sample, $index === 0);
        }

        $doc->save($path, 'Templat Import Soal Alsenform');
    }

    /**
     * The samples with the words accepted after "Tipe:" for their type, for the Help page.
     *
     * @return list<array{title: string, summary: string, key: string, note: string|null, typeAliases: list<string>, lines: list<string>, table: list<list<string>>|null, answer: string|null}>
     */
    public function guide(): array
    {
        $aliases = [];
        foreach (DocxImportService::TYPE_ALIASES as $alias => $type) {
            $aliases[$type][] = $alias;
        }

        return array_map(fn (array $sample): array => [
            'title' => $sample['title'],
            'summary' => $sample['summary'],
            'key' => $sample['key'],
            'note' => $sample['note'] ?? null,
            'typeAliases' => $aliases[$sample['type']] ?? [],
            'lines' => $sample['lines'],
            'table' => $sample['table'] ?? null,
            'answer' => $sample['answer'] ?? null,
        ], $this->samples());
    }

    /**
     * One sample per question type. "type" is the importer's type (a key of the alias groups),
     * "lines" the paragraphs before an optional table, "answer" the key line after that table.
     *
     * @return list<array{title: string, type: string, summary: string, key: string, note?: string, lines: list<string>, table?: list<list<string>>, widths?: list<int>, answer?: string}>
     */
    public function samples(): array
    {
        return [
            [
                'title' => 'Pilihan Ganda',
                'type' => 'Multiple choice',
                'summary' => 'Pilihan A sampai E, satu jawaban benar. Baris "Tipe:" boleh tidak ditulis: soal dengan pilihan A–E otomatis menjadi Pilihan Ganda.',
                'key' => 'Huruf pilihan yang benar, atau teks pilihannya.',
                'lines' => [
                    '1. Ibu kota negara Republik Indonesia adalah ...',
                    'Poin: 1',
                    'Wajib: Ya',
                    'A. Surabaya', 'B. Jakarta', 'C. Bandung', 'D. Medan',
                    'Jawaban: B',
                ],
            ],
            [
                'title' => 'Pilihan Ganda Kompleks (Kotak Centang)',
                'type' => 'Checkboxes',
                'summary' => 'Pilihan A sampai E, jawaban benar boleh lebih dari satu. Murid harus mencentang semua jawaban benar.',
                'key' => 'Semua huruf yang benar, dipisah koma atau "dan".',
                'lines' => [
                    '2. Manakah yang termasuk bilangan prima? (jawaban boleh lebih dari satu)',
                    'Tipe: Kotak Centang',
                    'Poin: 1',
                    'A. 2', 'B. 4', 'C. 5', 'D. 9',
                    'Jawaban: A, C',
                ],
            ],
            [
                'title' => 'Drop-down',
                'type' => 'Drop-down',
                'summary' => 'Seperti Pilihan Ganda, tetapi pilihan tampil sebagai daftar tarik-turun.',
                'key' => 'Huruf pilihan yang benar.',
                'lines' => [
                    '3. Planet terbesar di tata surya kita adalah ...',
                    'Tipe: Drop-down',
                    'Poin: 1',
                    'A. Mars', 'B. Bumi', 'C. Jupiter', 'D. Venus',
                    'Jawaban: C',
                ],
            ],
            [
                'title' => 'Benar / Salah',
                'type' => 'true-false',
                'summary' => 'Satu pernyataan tanpa pilihan. Murid memilih Benar atau Salah.',
                'key' => '"Benar" atau "Salah" (boleh juga B / S).',
                'lines' => [
                    '4. Matahari terbit dari arah barat.',
                    'Tipe: Benar Salah',
                    'Poin: 1',
                    'Jawaban: Salah',
                ],
            ],
            [
                'title' => 'Isian Singkat',
                'type' => 'Short answer',
                'summary' => 'Tanpa pilihan; murid mengetik jawaban singkat. Baris "Tipe:" boleh tidak ditulis: soal tanpa pilihan otomatis menjadi Isian Singkat.',
                'key' => 'Jawaban benar. Beberapa jawaban yang diterima dipisah tanda |. Huruf besar/kecil tidak dibedakan.',
                'lines' => [
                    '5. Sebutkan ibu kota Provinsi Jawa Barat!',
                    'Poin: 1',
                    'Jawaban: Bandung | Kota Bandung',
                ],
            ],
            [
                'title' => 'Isian Singkat (angka dan rentang)',
                'type' => 'Short answer',
                'summary' => 'Isian berupa angka. "3,5" dan "3.50" dianggap sama.',
                'key' => 'Angka, atau rentang min..maks yang dianggap benar.',
                'lines' => [
                    '6. Panjang meja hasil pengukuran adalah ... cm (toleransi 0,5 cm)',
                    'Tipe: Isian Singkat',
                    'Poin: 1',
                    'Jawaban: 119.5..120.5',
                ],
            ],
            [
                'title' => 'Uraian / Esai',
                'type' => 'Paragraph',
                'summary' => 'Jawaban panjang yang dinilai guru. Tidak dikoreksi otomatis, jadi tidak dihitung dalam nilai 0–100.',
                'key' => 'Contoh jawaban atau rubrik untuk guru dalam satu paragraf, atau "-" bila tidak ada.',
                'lines' => [
                    '7. Jelaskan secara singkat proses terjadinya hujan!',
                    'Tipe: Uraian',
                    'Poin: 1',
                    'Jawaban: Air menguap karena panas matahari, uap air mengembun menjadi awan, lalu jatuh kembali sebagai hujan.',
                ],
            ],
            [
                'title' => 'Benar / Salah beberapa pernyataan (tabel)',
                'type' => 'true-false',
                'summary' => 'Beberapa pernyataan dalam tabel Word 3 kolom: No | Pernyataan | Benar/Salah. Kolom ketiga boleh dikosongkan.',
                'key' => 'B atau S untuk setiap baris, berurutan dari atas.',
                'note' => 'Kunci juga bisa ditandai di tabel: buat kolom "Benar" dan "Salah" terpisah, beri X di kolom yang benar, lalu tulis "Jawaban: lihat tabel".',
                'lines' => [
                    '8. Tentukan Benar atau Salah setiap pernyataan berikut!',
                    'Tipe: Benar Salah',
                    'Poin: 1',
                ],
                'table' => [
                    ['No', 'Pernyataan', 'Benar/Salah'],
                    ['1', 'Air mendidih pada suhu 100 °C di permukaan laut.', ''],
                    ['2', 'Bulan memancarkan cahayanya sendiri.', ''],
                    ['3', 'Tumbuhan hijau melakukan fotosintesis.', ''],
                ],
                'widths' => [700, 6938, 2000],
                'answer' => 'Jawaban: B, S, B',
            ],
            [
                'title' => 'Menjodohkan',
                'type' => 'matching',
                'summary' => 'Tabel Word 3 kolom: No | Soal | Pasangan. Pasangan di kolom kanan ditulis acak.',
                'key' => 'Nomor soal + huruf baris pasangan. A = baris pertama kolom kanan, B = baris kedua, dan seterusnya.',
                'lines' => [
                    '9. Pasangkan negara dengan ibu kotanya!',
                    'Tipe: Menjodohkan',
                    'Poin: 1',
                ],
                'table' => [
                    ['No', 'Negara', 'Ibu Kota'],
                    ['1', 'Jepang', 'Paris'],
                    ['2', 'Prancis', 'Kairo'],
                    ['3', 'Mesir', 'Tokyo'],
                ],
                'widths' => [700, 4469, 4469],
                'answer' => 'Jawaban: 1C, 2A, 3B',
            ],
            [
                'title' => 'Kisi Pilihan Ganda',
                'type' => 'Multiple-choice grid',
                'summary' => 'Tabel: baris pertama berisi judul kolom pilihan, kolom pertama berisi pernyataan. Satu jawaban per baris.',
                'key' => 'Beri X di sel yang benar lalu tulis "Jawaban: lihat tabel". Bisa juga ditulis "1A, 2B" (nomor baris + huruf kolom).',
                'lines' => [
                    '10. Kelompokkan hewan berikut sesuai golongannya!',
                    'Tipe: Kisi Pilihan Ganda',
                    'Poin: 1',
                ],
                'table' => [
                    ['Hewan', 'Mamalia', 'Burung', 'Reptil'],
                    ['Paus', 'X', '', ''],
                    ['Elang', '', 'X', ''],
                    ['Kadal', '', '', 'X'],
                ],
                'widths' => [3038, 2200, 2200, 2200],
                'answer' => 'Jawaban: lihat tabel',
            ],
            [
                'title' => 'Kisi Kotak Centang',
                'type' => 'Tick box grid',
                'summary' => 'Seperti Kisi Pilihan Ganda, tetapi setiap baris boleh punya lebih dari satu jawaban benar.',
                'key' => 'Beri X di semua sel yang benar lalu tulis "Jawaban: lihat tabel".',
                'lines' => [
                    '11. Centang semua ciri yang dimiliki setiap hewan!',
                    'Tipe: Kisi Kotak Centang',
                    'Poin: 1',
                ],
                'table' => [
                    ['Hewan', 'Berkaki empat', 'Menyusui', 'Bertelur'],
                    ['Kucing', 'X', 'X', ''],
                    ['Ayam', '', '', 'X'],
                    ['Kambing', 'X', 'X', ''],
                ],
                'widths' => [3038, 2200, 2200, 2200],
                'answer' => 'Jawaban: lihat tabel',
            ],
            [
                'title' => 'Skala Linear',
                'type' => 'Linear scale',
                'summary' => 'Murid memilih angka pada skala, cocok untuk survei. Rentang diatur dengan "Skala:", dari 0 atau 1 sampai paling besar 10 (bawaan 1-5).',
                'key' => '"-" untuk survei (tanpa kunci, tidak dihitung dalam nilai), atau angka yang dianggap benar.',
                'lines' => [
                    '12. Seberapa paham kamu dengan materi hari ini?',
                    'Tipe: Skala Linear',
                    'Poin: 1',
                    'Skala: 1-5',
                    'Jawaban: -',
                ],
            ],
            [
                'title' => 'Rating (bintang)',
                'type' => 'Rating',
                'summary' => 'Murid memberi bintang. Jumlah bintang diatur dengan "Skala:" (bawaan 1-5).',
                'key' => '"-" untuk survei, atau jumlah bintang yang dianggap benar.',
                'lines' => [
                    '13. Beri nilai untuk cara guru menjelaskan materi.',
                    'Tipe: Rating',
                    'Poin: 1',
                    'Skala: 1-5',
                    'Jawaban: -',
                ],
            ],
            [
                'title' => 'Tanggal',
                'type' => 'Date',
                'summary' => 'Murid memilih tanggal dari kalender.',
                'key' => 'Tanggal hari-bulan-tahun, misalnya 17-08-1945 (atau 1945-08-17).',
                'lines' => [
                    '14. Kapan Proklamasi Kemerdekaan Indonesia dibacakan?',
                    'Tipe: Tanggal',
                    'Poin: 1',
                    'Jawaban: 17-08-1945',
                ],
            ],
            [
                'title' => 'Waktu',
                'type' => 'Time',
                'summary' => 'Murid memilih jam dan menit.',
                'key' => 'Jam:menit dalam format 24 jam, misalnya 07:00 atau 13:30.',
                'lines' => [
                    '15. Pukul berapa upacara bendera hari Senin dimulai?',
                    'Tipe: Waktu',
                    'Poin: 1',
                    'Jawaban: 07:00',
                ],
            ],
        ];
    }

    /**
     * @param  array{lines: list<string>, table?: list<list<string>>, widths?: list<int>, answer?: string}  $sample
     */
    protected function writeSample(DocxWriter $doc, array $sample, bool $isFirst): void
    {
        foreach ($sample['lines'] as $index => $line) {
            $isMeta = (bool) preg_match('/^(Tipe|Poin|Wajib|Skala):/', $line);
            $isAnswer = str_starts_with($line, 'Jawaban:');
            // Keep a question on one page, but let the page break after its answer line.
            $doc->paragraph([[
                'text' => $line,
                'bold' => $index === 0 || $isAnswer,
                'color' => $isMeta ? self::META_COLOR : null,
            ]], ['before' => $index === 0 && ! $isFirst ? 200 : 0, 'after' => 40, 'keepNext' => ! $isAnswer]);
        }

        if (isset($sample['table'])) {
            $doc->table($sample['table'], [
                'header' => true,
                'headerFill' => 'E0E7FF',
                'headerColor' => '312E81',
                ...(isset($sample['widths']) ? ['widths' => $sample['widths']] : []),
            ]);
        }

        if (isset($sample['answer'])) {
            $doc->paragraph([['text' => $sample['answer'], 'bold' => true]], ['after' => 40]);
        }
    }
}
