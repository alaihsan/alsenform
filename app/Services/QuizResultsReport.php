<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\QuizForm;
use App\Models\QuizResponse;
use App\Support\Addon;
use App\Support\DisplayTime;
use App\Support\PageFailure;
use App\Support\QuizItems;
use App\Support\QuizScoring;
use App\Support\XlsxWriter;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The downloadable Excel report of a quiz: a grade recap and every student's answers.
 */
class QuizResultsReport
{
    protected const SCORE_COLUMNS = 16;

    public function __construct(protected QuizScoring $scoring, protected ExamDelivery $delivery) {}

    public function filename(QuizForm $quizForm): string
    {
        $slug = Str::slug($quizForm->title) ?: 'formulir';

        return "hasil_jawaban_{$slug}_".$this->now()->format('Ymd_His').'.xlsx';
    }

    /**
     * Build the workbook and write it to the given path.
     */
    public function save(QuizForm $quizForm, string $path): void
    {
        // Sections and "title and description" items are not questions: no column, no points.
        $questions = QuizItems::questions($quizForm->questions);

        // Only questions with an answer key can be earned, so a fully correct sheet is 100.
        $maxScore = $this->scoring->maxPoints($questions);
        $kkm = $quizForm->kkm();
        $students = $this->students($quizForm, $questions, $maxScore);

        $workbook = (new XlsxWriter)
            ->addSheet('Rekap Nilai', ...$this->scoreSheet($quizForm, $students, $maxScore, $kkm))
            ->addSheet('Jawaban Siswa', ...$this->answerSheet($quizForm, $questions, $students));

        // Angket berskor: the subtotal of every section, like every number of an interview sheet.
        if ($quizForm->isScoredQuestionnaire()) {
            $workbook->addSheet('Skor per Bagian', ...$this->sectionSheet($quizForm, $students));
        }

        // Sheets of the add-ons that are on for this exam.
        if ($quizForm->usesAddon(Addon::ItemAnalysis)) {
            $workbook->addSheet('Analisis Butir Soal', ...$this->analysisSheet($quizForm));
        }
        if ($quizForm->usesAddon(Addon::Remedial)) {
            $workbook->addSheet('Remedial & Pengayaan', ...$this->remedialSheet($quizForm, $kkm));
        }
        if ($quizForm->usesAddon(Addon::Misconception)) {
            $workbook->addSheet('Peta Miskonsepsi', ...$this->misconceptionSheet($quizForm));
        }

        // Whether the answers got to the server, and every error the students met, as on the page.
        $workbook->addSheet('Pengiriman & Galat', ...$this->deliverySheet($quizForm));

        $workbook->save($path, 'Hasil Jawaban - '.$this->plainText((string) $quizForm->title));
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $students
     * @return array{0: list<list<mixed>|null>, 1: array<string, mixed>}
     */
    protected function scoreSheet(QuizForm $quizForm, Collection $students, int $maxScore, float $kkm): array
    {
        $lastColumn = XlsxWriter::columnLetter(self::SCORE_COLUMNS);
        $passed = $students->filter(fn (array $student) => $student['grade'] >= $kkm)->count();
        $average = $students->isEmpty() ? 0 : round($students->avg('grade'), 1);
        // A scored questionnaire has a total score, with no pass or fail.
        $isQuestionnaire = $quizForm->isScoredQuestionnaire();

        $rows = [
            [XlsxWriter::cell('Rekap Nilai: '.$this->plainText((string) $quizForm->title), 'title')],
            [XlsxWriter::cell(implode('   •   ', [
                'Diekspor '.$this->now()->format('d/m/Y H:i').' '.$this->now()->format('T'),
                $students->count().' peserta',
                $students->contains(fn (array $student): bool => $student['questionIds'] !== null)
                    ? 'Soal diacak per siswa, skor maksimal mengikuti soal yang didapat'
                    : 'Skor maksimal '.$maxScore,
                ...($isQuestionnaire ? [
                    'Angket berskor: skor tiap jawaban dijumlahkan',
                    'Rata-rata skor '.$this->decimal($students->isEmpty() ? 0 : round($students->avg('score'), 1)),
                ] : [
                    'Rata-rata nilai '.$this->decimal($average),
                    'KKM '.$this->decimal($kkm),
                    "Tuntas {$passed} dari {$students->count()}",
                ]),
            ]), 'subtitle')],
            null,
            array_map(fn (string $title) => XlsxWriter::cell($title, 'header'), [
                'No', $this->identityLabels($quizForm)[0], 'Nama Lengkap', $this->identityLabels($quizForm)[1], 'Email',
                $isQuestionnaire ? 'Total Skor' : 'Skor', 'Skor Maksimal', $isQuestionnaire ? 'Persentase' : 'Nilai (0-100)',
                'Keterangan', 'Status', 'Waktu Selesai', 'Percobaan', 'Waktu Mulai', 'Lama (menit)', 'Pindah Tab', 'Tanda Terima',
            ]),
        ];

        foreach ($students->values() as $index => $student) {
            $isPassed = $student['grade'] >= $kkm;
            $rows[] = [
                XlsxWriter::cell($index + 1, 'integer'),
                XlsxWriter::cell($student['nis'], 'textCenter'),
                XlsxWriter::cell($student['name'], 'text'),
                XlsxWriter::cell($student['kelas'], 'textCenter'),
                XlsxWriter::cell($student['email'], 'text'),
                XlsxWriter::cell($student['score'], floor($student['score']) === (float) $student['score'] ? 'integer' : 'decimal'),
                XlsxWriter::cell($student['maxScore'], 'integer'),
                XlsxWriter::cell($student['grade'], 'decimal'),
                $isQuestionnaire ? XlsxWriter::cell('-', 'textCenter') : XlsxWriter::cell($isPassed ? 'Tuntas' : 'Belum Tuntas', $isPassed ? 'pass' : 'fail'),
                XlsxWriter::cell($this->statusText($student), 'textCenter'),
                XlsxWriter::cell($student['submittedAt'], 'datetime'),
                XlsxWriter::cell("{$student['attempt']} dari {$student['attempts']}", 'textCenter'),
                $student['startedAt'] !== null ? XlsxWriter::cell($student['startedAt'], 'datetime') : XlsxWriter::cell('-', 'textCenter'),
                $student['durationMinutes'] !== null ? XlsxWriter::cell($student['durationMinutes'], 'integer') : XlsxWriter::cell('-', 'textCenter'),
                $student['tabSwitches'] !== null ? XlsxWriter::cell($student['tabSwitches'], 'integer') : XlsxWriter::cell('-', 'textCenter'),
                XlsxWriter::cell($student['receipt'] ?? '-', 'textCenter'),
            ];
        }

        $lastRow = max(4, count($rows));

        return [$rows, [
            'columns' => [5, 14, 30, 12, 28, 8, 10, 13, 14, 13, 17, 11, 17, 10, 10, 13],
            'freeze' => 'A5',
            'autoFilter' => "A4:{$lastColumn}{$lastRow}",
            'merge' => ["A1:{$lastColumn}1", "A2:{$lastColumn}2"],
            'rowHeights' => [1 => 28, 2 => 20],
            'repeatRows' => [4, 4],
        ]];
    }

    /**
     * Pengiriman Jawaban: the three figures of the results page, then every error the students met
     * (when, who, on what device and why), newest first.
     *
     * @return array{0: list<list<mixed>|null>, 1: array<string, mixed>}
     */
    protected function deliverySheet(QuizForm $quizForm): array
    {
        $figures = $this->delivery->of($quizForm);
        $percent = fn (?float $value): array => $value === null ? XlsxWriter::cell('-', 'textCenter') : XlsxWriter::cell($value / 100, 'summaryPercent');
        $headers = ['No', 'Waktu', 'Jenis', 'Nama', 'Kelas / Kelompok', 'Perangkat', 'Penyebab', 'Keterangan Teknis'];
        $lastColumn = XlsxWriter::columnLetter(count($headers));

        $rows = [
            [XlsxWriter::cell('Pengiriman & Galat: '.$this->plainText((string) $quizForm->title), 'title')],
            [XlsxWriter::cell("Seberapa lancar jawaban {$figures['attempts']} pengerjaan sampai ke server, dan galat yang dialami siswa (bukan saat guru menyunting ujian)", 'subtitle')],
            null,
            [XlsxWriter::cell('Tersimpan di server ≤ '.ExamDelivery::SAVED_WITHIN_SECONDS.' detik', 'summaryLabel'), $percent($figures['savedInTime']), XlsxWriter::cell("dari {$figures['savedInTimeOf']} siswa, terlama ".($figures['slowestSaveSeconds'] ?? 0).' detik', 'text')],
            [XlsxWriter::cell('Terkirim pada percobaan pertama', 'summaryLabel'), $percent($figures['firstTry']), XlsxWriter::cell("dari {$figures['firstTryOf']} pengiriman, {$figures['autoSubmitted']} dikirim otomatis oleh server", 'text')],
            [XlsxWriter::cell('Galat per 100 siswa', 'summaryLabel'), $figures['errorsPer100'] === null ? XlsxWriter::cell('-', 'textCenter') : XlsxWriter::cell($figures['errorsPer100'], 'decimal'), XlsxWriter::cell("{$figures['errors']} halaman gagal dimuat atau galat server", 'text')],
            null,
            array_map(fn (string $title) => XlsxWriter::cell($title, 'header'), $headers),
        ];
        $headerRow = count($rows);

        $timezone = DisplayTime::timezone();
        $this->delivery->errors($quizForm)->with('user:id,name,kelas')->latest()->limit(1000)->get()
            ->each(function (ActivityLog $log, int $index) use (&$rows, $timezone): void {
                $rows[] = [
                    XlsxWriter::cell($index + 1, 'integer'),
                    XlsxWriter::cell($log->created_at?->copy()->setTimezone($timezone), 'datetime'),
                    XlsxWriter::cell(ActivityLog::EVENT_LABELS[$log->event] ?? $log->event, 'textCenter'),
                    XlsxWriter::cell($log->user?->name ?? ($log->respondent_name ?: 'Peserta tamu'), 'text'),
                    XlsxWriter::cell($log->user?->kelas ?? $log->respondent_class ?? '-', 'textCenter'),
                    XlsxWriter::cell(implode(' · ', array_filter([$log->device, $log->os, $log->browser])) ?: '-', 'text'),
                    XlsxWriter::cell($log->event === ActivityLog::PAGE_FAILED
                        ? (PageFailure::tryFrom((string) ($log->meta['code'] ?? ''))?->title() ?? 'Halaman gagal dimuat').' ('.($log->meta['code'] ?? '?').')'
                        : 'Galat server '.($log->meta['reference'] ?? '').' (HTTP '.($log->meta['status'] ?? 500).')', 'text'),
                    XlsxWriter::cell(implode(' · ', array_filter([$log->meta['path'] ?? null, $log->meta['message'] ?? null, $log->meta['asset'] ?? null])) ?: '-', 'text'),
                ];
            });

        if (count($rows) === $headerRow) {
            $rows[] = [XlsxWriter::cell('Tidak ada galat yang dialami siswa.', 'text')];
        }

        return [$rows, [
            'columns' => [34, 17, 20, 26, 16, 30, 34, 60],
            'freeze' => 'A'.($headerRow + 1),
            'merge' => ["A1:{$lastColumn}1", "A2:{$lastColumn}2"],
            'rowHeights' => [1 => 28, 2 => 20],
            'repeatRows' => [$headerRow, $headerRow],
        ]];
    }

    /**
     * Angket berskor: every student's score in each section, and the total.
     *
     * @param  Collection<int, array<string, mixed>>  $students
     * @return array{0: list<list<mixed>|null>, 1: array<string, mixed>}
     */
    protected function sectionSheet(QuizForm $quizForm, Collection $students): array
    {
        $items = is_array($quizForm->questions) ? $quizForm->questions : [];
        $sections = $this->scoring->sectionScores($items, []);
        $fixedColumns = ['No', $this->identityLabels($quizForm)[0], 'Nama Lengkap', $this->identityLabels($quizForm)[1]];
        $lastColumn = XlsxWriter::columnLetter(count($fixedColumns) + count($sections) + 1);

        $rows = [
            [XlsxWriter::cell('Skor per Bagian: '.$this->plainText((string) $quizForm->title), 'title')],
            [XlsxWriter::cell('Jumlah skor jawaban yang dipilih di setiap bagian, dan totalnya', 'subtitle')],
            null,
            [
                ...array_map(fn (string $title) => XlsxWriter::cell($title, 'header'), $fixedColumns),
                ...array_map(fn (array $section) => XlsxWriter::cell($this->plainText($section['title'])." (maks. {$section['max']})", 'header'), $sections),
                XlsxWriter::cell('Total Skor (maks. '.array_sum(array_column($sections, 'max')).')', 'header'),
            ],
        ];

        foreach ($students->values() as $index => $student) {
            $scores = array_column($this->scoring->sectionScores($items, $student['answers'], $student['questionIds']), 'score', 'index');
            $rows[] = [
                XlsxWriter::cell($index + 1, 'integer'),
                XlsxWriter::cell($student['nis'], 'textCenter'),
                XlsxWriter::cell($student['attempts'] > 1 ? "{$student['name']} (percobaan {$student['attempt']})" : $student['name'], 'text'),
                XlsxWriter::cell($student['kelas'], 'textCenter'),
                ...array_map(fn (array $section) => isset($scores[$section['index']]) ? XlsxWriter::cell($scores[$section['index']], 'integer') : XlsxWriter::cell('-', 'textCenter'), $sections),
                XlsxWriter::cell($student['score'], floor($student['score']) === (float) $student['score'] ? 'integer' : 'decimal'),
            ];
        }

        return [$rows, [
            'columns' => [5, 14, 30, 12, ...array_fill(0, count($sections), 18), 16],
            'freeze' => 'E5',
            'autoFilter' => "A4:{$lastColumn}".max(4, count($rows)),
            'merge' => ["A1:{$lastColumn}1", "A2:{$lastColumn}2"],
            'rowHeights' => [1 => 28, 2 => 20],
            'repeatRows' => [4, 4],
        ]];
    }

    /**
     * @param  list<array<string, mixed>>  $questions
     * @param  Collection<int, array<string, mixed>>  $students
     * @return array{0: list<list<mixed>|null>, 1: array<string, mixed>}
     */
    protected function answerSheet(QuizForm $quizForm, array $questions, Collection $students): array
    {
        $fixedColumns = ['No', $this->identityLabels($quizForm)[0], 'Nama Lengkap', $this->identityLabels($quizForm)[1], 'Nilai'];
        $lastColumn = XlsxWriter::columnLetter(count($fixedColumns) + max(1, count($questions)));

        $header = array_map(fn (string $title) => XlsxWriter::cell($title, 'header'), $fixedColumns);
        foreach ($questions as $number => $question) {
            $header[] = XlsxWriter::cell($this->questionHeader($number + 1, $question), 'headerLeft');
        }

        $rows = [
            [XlsxWriter::cell('Jawaban Siswa: '.$this->plainText((string) $quizForm->title), 'title')],
            [XlsxWriter::cell($quizForm->isScoredQuestionnaire()
                ? 'Angket berskor: setiap jawaban tertulis bersama skornya, tanpa benar atau salah'
                : 'Hijau = benar   •   Merah = salah atau tidak dijawab   •   Abu-abu = tidak dinilai otomatis (esai / tanpa kunci jawaban)', 'subtitle')],
            null,
            $header,
        ];

        $correctCounts = array_fill(0, count($questions), 0);
        $givenCounts = array_fill(0, count($questions), 0);
        foreach ($students->values() as $index => $student) {
            $row = [
                XlsxWriter::cell($index + 1, 'integer'),
                XlsxWriter::cell($student['nis'], 'textCenter'),
                XlsxWriter::cell($student['attempts'] > 1 ? "{$student['name']} (percobaan {$student['attempt']})" : $student['name'], 'text'),
                XlsxWriter::cell($student['kelas'], 'textCenter'),
                XlsxWriter::cell($student['grade'], 'decimal'),
            ];

            foreach ($questions as $questionIndex => $question) {
                $cell = $this->answerCell($question, $student);

                if ($cell['mark'] !== 'absent') {
                    $givenCounts[$questionIndex]++;
                }
                if ($cell['mark'] === 'correct') {
                    $correctCounts[$questionIndex]++;
                }

                $row[] = XlsxWriter::cell($cell['text'], match ($cell['mark']) {
                    'correct' => 'correct',
                    'wrong' => 'wrong',
                    default => 'ungraded',
                });
            }

            $rows[] = $row;
        }

        $lastStudentRow = max(4, count($rows));

        // Share of students who answered each automatically scored question correctly.
        $hasSummary = $students->isNotEmpty() && $questions !== [] && ! $quizForm->isScoredQuestionnaire();
        if ($hasSummary) {
            $rows[] = null;
            $summary = [XlsxWriter::cell('Persentase jawaban benar', 'summaryLabel')];
            for ($column = 1; $column < count($fixedColumns); $column++) {
                $summary[] = XlsxWriter::cell(null, 'summaryLabel');
            }
            foreach ($questions as $questionIndex => $question) {
                $summary[] = $this->scoring->isAutoScored($question) && $givenCounts[$questionIndex] > 0
                    ? XlsxWriter::cell($correctCounts[$questionIndex] / $givenCounts[$questionIndex], 'summaryPercent')
                    : XlsxWriter::cell('-', 'summaryPercent');
            }
            $rows[] = $summary;
        }

        $merge = ["A1:{$lastColumn}1", "A2:{$lastColumn}2"];
        if ($hasSummary) {
            $merge[] = 'A'.count($rows).':'.XlsxWriter::columnLetter(count($fixedColumns)).count($rows);
        }

        return [$rows, [
            'columns' => [5, 14, 28, 12, 9, ...array_fill(0, max(1, count($questions)), 26)],
            'freeze' => 'F5',
            'autoFilter' => "A4:{$lastColumn}{$lastStudentRow}",
            'merge' => $merge,
            'rowHeights' => [1 => 28, 2 => 20],
            'repeatRows' => [4, 4],
        ]];
    }

    /**
     * Analisis butir soal: difficulty, discrimination, distractors and a decision per question.
     *
     * @return array{0: list<list<mixed>|null>, 1: array<string, mixed>}
     */
    protected function analysisSheet(QuizForm $quizForm): array
    {
        $analysis = app(ItemAnalysis::class)->analyze($quizForm);
        $headers = ['No', 'Soal', 'Peserta', 'Benar', 'Tingkat Kesukaran (P)', 'Kategori', 'Daya Pembeda (D)', 'Kategori', 'Pengecoh Tidak Berfungsi', 'Keputusan', 'Catatan'];
        $lastColumn = XlsxWriter::columnLetter(count($headers));

        $rows = [
            [XlsxWriter::cell('Analisis Butir Soal: '.$this->plainText((string) $quizForm->title), 'title')],
            [XlsxWriter::cell(implode('   •   ', [
                $analysis['respondents'].' peserta',
                'P: > 0,70 mudah, 0,31–0,70 sedang, ≤ 0,30 sukar',
                'D: kelompok atas – bawah 27%; > 0,40 baik, 0,21–0,40 cukup, ≤ 0,20 jelek',
                'Pengecoh berfungsi bila dipilih ≥ 5% peserta',
                'D, pengecoh dan keputusan dari minimal '.ItemAnalysis::MIN_TAKERS.' peserta per soal (percobaan pertama)',
            ]), 'subtitle')],
            null,
            array_map(fn (string $title) => XlsxWriter::cell($title, 'header'), $headers),
        ];

        foreach ($analysis['questions'] as $question) {
            $rows[] = [
                XlsxWriter::cell($question['number'], 'integer'),
                XlsxWriter::cell($question['title'], 'text'),
                XlsxWriter::cell($question['takers'], 'integer'),
                XlsxWriter::cell($question['correct'], 'integer'),
                XlsxWriter::cell($question['p'], 'decimal'),
                XlsxWriter::cell($question['difficulty']['label'] ?? '-', 'textCenter'),
                XlsxWriter::cell($question['d'], 'decimal'),
                XlsxWriter::cell($question['discrimination']['label'] ?? '-', 'textCenter'),
                XlsxWriter::cell($question['weakDistractors'] === [] ? '-' : implode(', ', $question['weakDistractors']), 'textCenter'),
                XlsxWriter::cell($question['decision']['label'] ?? '-', match ($question['decision']['key'] ?? null) {
                    'dipakai' => 'pass',
                    'ganti' => 'fail',
                    default => 'textCenter',
                }),
                XlsxWriter::cell($question['note'] ?? '', 'text'),
            ];
        }

        $lastRow = max(4, count($rows));

        return [$rows, [
            'columns' => [5, 48, 9, 8, 12, 11, 12, 12, 14, 12, 40],
            'freeze' => 'C5',
            'autoFilter' => "A4:{$lastColumn}{$lastRow}",
            'merge' => ["A1:{$lastColumn}1", "A2:{$lastColumn}2"],
            'rowHeights' => [1 => 28, 2 => 20],
            'repeatRows' => [4, 4],
        ]];
    }

    /**
     * Remedial & Pengayaan: each student once, with the original grade (best attempt), the grade
     * of the remedial or enrichment exam kept apart, and the grade that counts after remedial.
     *
     * @return array{0: list<list<mixed>|null>, 1: array<string, mixed>}
     */
    protected function remedialSheet(QuizForm $quizForm, float $kkm): array
    {
        $students = app(Remedial::class)->students($quizForm);
        $headers = ['No', 'NIS', 'Nama Lengkap', 'Kelas', 'Nilai Asli', 'Status', 'Tindak Lanjut', 'Nilai Remedial', 'Nilai Pengayaan', 'Nilai Akhir', 'Keterangan'];
        $lastColumn = XlsxWriter::columnLetter(count($headers));
        $belowKkm = $students->where('passed', false);
        $passedAfterRemedial = $belowKkm->filter(fn (array $student): bool => $student['remedialGrade'] !== null && $student['remedialGrade'] >= $kkm)->count();

        $rows = [
            [XlsxWriter::cell('Remedial & Pengayaan: '.$this->plainText((string) $quizForm->title), 'title')],
            [XlsxWriter::cell(implode('   •   ', [
                'KKM '.$this->decimal($kkm),
                'Belum tuntas '.$belowKkm->count().' (remedial)',
                'Tuntas '.($students->count() - $belowKkm->count()).' (pengayaan)',
                "Tuntas setelah remedial {$passedAfterRemedial}",
                'Nilai asli: nilai terbaik di ujian ini',
                'Nilai akhir remedial paling tinggi sama dengan KKM; nilai asli tetap tercatat',
            ]), 'subtitle')],
            null,
            array_map(fn (string $title) => XlsxWriter::cell($title, 'header'), $headers),
        ];

        foreach ($students->values() as $index => $student) {
            $remedialGrade = $student['remedialGrade'];
            $enrichmentGrade = $student['enrichmentGrade'];
            $note = match (true) {
                $student['passed'] => $enrichmentGrade !== null ? 'Mengikuti pengayaan' : 'Tuntas',
                $remedialGrade === null => 'Belum mengikuti remedial',
                $remedialGrade >= $kkm => 'Tuntas setelah remedial',
                default => 'Belum tuntas setelah remedial',
            };

            $rows[] = [
                XlsxWriter::cell($index + 1, 'integer'),
                XlsxWriter::cell($student['nis'], 'textCenter'),
                XlsxWriter::cell($student['name'], 'text'),
                XlsxWriter::cell($student['kelas'], 'textCenter'),
                XlsxWriter::cell($student['grade'], 'decimal'),
                XlsxWriter::cell($student['passed'] ? 'Tuntas' : 'Belum Tuntas', $student['passed'] ? 'pass' : 'fail'),
                XlsxWriter::cell($student['passed'] ? 'Pengayaan' : 'Remedial', 'textCenter'),
                $remedialGrade !== null ? XlsxWriter::cell($remedialGrade, 'decimal') : XlsxWriter::cell('-', 'textCenter'),
                $enrichmentGrade !== null ? XlsxWriter::cell($enrichmentGrade, 'decimal') : XlsxWriter::cell('-', 'textCenter'),
                XlsxWriter::cell($student['finalGrade'], 'decimal'),
                XlsxWriter::cell($note, match ($note) {
                    'Tuntas setelah remedial' => 'pass',
                    'Belum tuntas setelah remedial', 'Belum mengikuti remedial' => 'fail',
                    default => 'textCenter',
                }),
            ];
        }

        $lastRow = max(4, count($rows));

        return [$rows, [
            'columns' => [5, 14, 30, 12, 11, 14, 14, 13, 14, 11, 28],
            'freeze' => 'D5',
            'autoFilter' => "A4:{$lastColumn}{$lastRow}",
            'merge' => ["A1:{$lastColumn}1", "A2:{$lastColumn}2"],
            'rowHeights' => [1 => 28, 2 => 20],
            'repeatRows' => [4, 4],
        ]];
    }

    /**
     * Peta Miskonsepsi: per question the four groups of the CRI method, then per student the
     * questions with a misconception and those not understood.
     *
     * @return array{0: list<list<mixed>|null>, 1: array<string, mixed>}
     */
    protected function misconceptionSheet(QuizForm $quizForm): array
    {
        $map = app(Misconceptions::class)->analyze($quizForm);
        $headers = ['No', 'Soal', 'Peserta', 'Paham', 'Menebak', 'Belum Paham', 'Miskonsepsi', '% Miskonsepsi', 'Jawaban Keliru Paling Diyakini', 'Catatan'];
        $lastColumn = XlsxWriter::columnLetter(count($headers));

        $rows = [
            [XlsxWriter::cell('Peta Miskonsepsi: '.$this->plainText((string) $quizForm->title), 'title')],
            [XlsxWriter::cell(implode('   •   ', array_filter([
                $map['respondents'].' peserta (percobaan pertama)',
                "{$map['usedDoubt']} memakai tombol Ragu-ragu",
                'Paham = benar & yakin; Menebak = benar & ragu; Belum paham = salah & ragu atau kosong; Miskonsepsi = salah & yakin',
                'Ditandai miskonsepsi bila ≥ '.(int) (Misconceptions::FLAG_SHARE * 100).'% peserta salah tapi yakin (minimal '.Misconceptions::MIN_TAKERS.' peserta)',
                $map['trustworthy'] ? null : 'PERHATIAN: sedikit siswa memakai tombol Ragu-ragu, sehingga miskonsepsi bisa terlalu tinggi',
            ])), 'subtitle')],
            null,
            array_map(fn (string $title) => XlsxWriter::cell($title, 'header'), $headers),
        ];

        foreach ($map['questions'] as $question) {
            $rows[] = [
                XlsxWriter::cell($question['number'], 'integer'),
                XlsxWriter::cell($question['title'], 'text'),
                XlsxWriter::cell($question['takers'], 'integer'),
                XlsxWriter::cell($question['paham'], 'integer'),
                XlsxWriter::cell($question['menebak'], 'integer'),
                XlsxWriter::cell($question['belumPaham'], 'integer'),
                XlsxWriter::cell($question['miskonsepsi'], $question['flagged'] ? 'fail' : 'integer'),
                XlsxWriter::cell($question['shares']['miskonsepsi'], 'summaryPercent'),
                XlsxWriter::cell($question['commonWrong'] !== null ? "{$question['commonWrong']['answer']} ({$question['commonWrong']['count']} siswa)" : '-', 'text'),
                XlsxWriter::cell($question['note'] ?? '', 'text'),
            ];
        }

        $lastQuestionRow = max(4, count($rows));

        // Per student: where to follow up.
        $rows[] = null;
        $studentHeaderRow = count($rows) + 1;
        $rows[] = array_map(fn (string $title) => XlsxWriter::cell($title, 'header'), ['No', 'Nama Lengkap', 'NIS', 'Kelas', 'Soal Miskonsepsi', 'Soal Belum Paham']);
        foreach ($map['students'] as $index => $student) {
            $rows[] = [
                XlsxWriter::cell($index + 1, 'integer'),
                XlsxWriter::cell($student['name'], 'text'),
                XlsxWriter::cell($student['nis'], 'textCenter'),
                XlsxWriter::cell($student['kelas'], 'textCenter'),
                XlsxWriter::cell($student['miskonsepsi'] === [] ? '-' : implode(', ', $student['miskonsepsi']), $student['miskonsepsi'] === [] ? 'textCenter' : 'fail'),
                XlsxWriter::cell($student['belumPaham'] === [] ? '-' : implode(', ', $student['belumPaham']), 'textCenter'),
            ];
        }

        return [$rows, [
            'columns' => [5, 48, 9, 9, 10, 12, 12, 13, 34, 48],
            'freeze' => 'C5',
            'autoFilter' => "A4:{$lastColumn}{$lastQuestionRow}",
            'merge' => ["A1:{$lastColumn}1", "A2:{$lastColumn}2"],
            'rowHeights' => [1 => 28, 2 => 34, $studentHeaderRow => 20],
            'repeatRows' => [4, 4],
        ]];
    }

    /**
     * The students who responded, sorted by class and name.
     *
     * @param  list<array<string, mixed>>  $questions
     * @return Collection<int, array<string, mixed>>
     */
    protected function students(QuizForm $quizForm, array $questions, int $maxScore): Collection
    {
        $timezone = DisplayTime::timezone();

        $responses = $quizForm->responses()
            ->with(['user:id,name,email,nis,kelas', 'quizSession:id,started_at,blur_count'])
            ->get(['id', 'quiz_form_id', 'quiz_session_id', 'receipt_hash', 'user_id', 'respondent_identifier', 'respondent_name', 'respondent_class', 'respondent_number', 'respondent_school', 'filled_for_respondent', 'email', 'score', 'manual_scores', 'is_timeout', 'is_auto_submitted', 'late_seconds', 'answers', 'question_ids', 'created_at']);

        // Every attempt of a student is listed, numbered in the order they were handed in.
        $attemptNumbers = QuizResponse::attemptNumbers($responses);

        return $responses
            ->map(function (QuizResponse $response) use ($questions, $timezone, $attemptNumbers): array {
                // Scored against the current answer keys, so a corrected key re-grades earlier answers,
                // with what the teacher graded by hand. With questions drawn per student, the maximum
                // is that of the questions they got.
                $result = $this->scoring->result($questions, is_array($response->answers) ? $response->answers : [], $response->question_ids, $response->manual_scores);
                $score = is_array($response->answers) ? $result['score'] : (int) ($response->score ?? 0);
                $studentMax = $result['max'];

                return [
                    'id' => $response->id,
                    // Guests on a public quiz: the name and class they gave before starting. Mode
                    // pewawancara: the candidate's name, registration number and school.
                    'nis' => $response->respondentDisplayNumber(),
                    'name' => $response->respondentDisplayName(),
                    'kelas' => $response->respondentDisplayGroup(),
                    'email' => $response->email ?: (($response->filled_for_respondent ? null : $response->user?->email) ?: '-'),
                    'score' => $score,
                    'maxScore' => $studentMax,
                    'grade' => $this->scoring->grade($score, $studentMax),
                    'questionIds' => $response->question_ids !== null ? array_map('strval', $response->question_ids) : null,
                    'isTimeout' => (bool) $response->is_timeout,
                    'isAutoSubmitted' => (bool) $response->is_auto_submitted,
                    'lateMinutes' => $response->late_seconds !== null ? (int) ceil($response->late_seconds / 60) : null,
                    'submittedAt' => $response->created_at?->copy()->setTimezone($timezone),
                    'startedAt' => $response->quizSession?->started_at?->copy()->setTimezone($timezone),
                    'durationMinutes' => $response->quizSession?->started_at !== null && $response->created_at !== null
                        ? max(0, (int) round($response->quizSession->started_at->diffInSeconds($response->created_at) / 60))
                        : null,
                    // How often the student left the exam page (another tab or app).
                    'tabSwitches' => $response->quizSession?->blur_count,
                    // The code the student got after handing in; marked when the answers kept no longer match it.
                    'receipt' => $response->receiptCode() !== null
                        ? $response->receiptCode().($response->hasIntactReceipt() === false ? ' (tidak cocok)' : '')
                        : null,
                    'answers' => is_array($response->answers) ? $response->answers : [],
                    'manualScores' => is_array($response->manual_scores) ? $response->manual_scores : [],
                    'ungraded' => $result['ungraded'],
                    'attempt' => $attemptNumbers[$response->id]['attempt'] ?? 1,
                    'attempts' => $attemptNumbers[$response->id]['attempts'] ?? 1,
                ];
            })
            ->sort(fn (array $a, array $b): int => ($a['kelas'] === '-') <=> ($b['kelas'] === '-')
                ?: strnatcasecmp($a['kelas'], $b['kelas'])
                ?: strnatcasecmp($a['name'], $b['name'])
                ?: $a['attempt'] <=> $b['attempt'])
            ->values();
    }

    /**
     * The workbook as one table for the Filter Cerdas add-on, to find, filter and sort in the app:
     * the columns of the grade recap, then each question with the student's answer and whether it
     * is right, with the same values as the Excel workbook.
     *
     * @return array{columns: list<array{key: string, label: string, type: string, hint?: string}>, rows: list<array{id: int, cells: list<mixed>, marks: list<string>}>, kkm: float}
     */
    public function grid(QuizForm $quizForm): array
    {
        $questions = QuizItems::questions($quizForm->questions);
        $kkm = $quizForm->kkm();
        $students = $this->students($quizForm, $questions, $this->scoring->maxPoints($questions));

        $columns = [
            ['key' => 'no', 'label' => 'No', 'type' => 'number'],
            ['key' => 'name', 'label' => 'Nama Lengkap', 'type' => 'text'],
            ['key' => 'nis', 'label' => $this->identityLabels($quizForm)[0], 'type' => 'text'],
            ['key' => 'kelas', 'label' => $this->identityLabels($quizForm)[1], 'type' => 'text'],
            ['key' => 'email', 'label' => 'Email', 'type' => 'text'],
            ['key' => 'grade', 'label' => 'Nilai (0-100)', 'type' => 'number'],
            ['key' => 'score', 'label' => 'Skor', 'type' => 'number'],
            ['key' => 'maxScore', 'label' => 'Skor Maksimal', 'type' => 'number'],
            ['key' => 'result', 'label' => 'Keterangan', 'type' => 'text'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'text'],
            ['key' => 'submittedAt', 'label' => 'Waktu Selesai', 'type' => 'datetime'],
            ['key' => 'attempt', 'label' => 'Percobaan', 'type' => 'text'],
        ];
        foreach ($questions as $index => $question) {
            // "3. Title (10 poin)", and the answer key (or "Esai, dinilai guru") as the hint.
            [$label, $hint] = array_pad(explode("\n", $this->questionHeader($index + 1, $question), 2), 2, '');
            $columns[] = ['key' => 'q:'.$question['id'], 'label' => $label, 'type' => 'answer', 'hint' => $hint];
        }

        $rows = $students->values()->map(function (array $student, int $index) use ($questions, $kkm, $quizForm): array {
            $answers = array_map(fn (array $question): array => $this->answerCell($question, $student), $questions);

            return [
                'id' => $student['id'],
                'cells' => [
                    $index + 1,
                    $student['name'],
                    $student['nis'],
                    $student['kelas'],
                    $student['email'],
                    $student['grade'],
                    $student['score'],
                    $student['maxScore'],
                    $quizForm->isScoredQuestionnaire() ? '-' : ($student['grade'] >= $kkm ? 'Tuntas' : 'Belum Tuntas'),
                    $this->statusText($student),
                    $student['submittedAt']?->format('Y-m-d H:i'),
                    "{$student['attempt']} dari {$student['attempts']}",
                    ...array_column($answers, 'text'),
                ],
                'marks' => array_column($answers, 'mark'),
            ];
        })->all();

        return ['columns' => $columns, 'rows' => $rows, 'kkm' => $kkm];
    }

    /**
     * What a student's answer to a question shows and whether it is right, in the workbook and in
     * the Filter Cerdas add-on alike.
     *
     * @param  array<string, mixed>  $question
     * @param  array<string, mixed>  $student
     * @return array{text: string, mark: 'correct'|'wrong'|'ungraded'|'absent'}
     */
    public function answerCell(array $question, array $student): array
    {
        if ($student['questionIds'] !== null && ! in_array((string) $question['id'], $student['questionIds'], true)) {
            return ['text' => '— (soal tidak didapat)', 'mark' => 'absent'];
        }

        $answer = $student['answers'][$question['id']] ?? null;
        $text = $this->answerText($question, $answer);

        // A scored questionnaire: no right or wrong, the score the answer earned.
        if ($this->scoring->isOptionScored($question)) {
            return [
                'text' => $text === '' ? '(tidak dijawab)' : $text."\nSkor: ".$this->scoring->earned($question, $answer),
                'mark' => 'ungraded',
            ];
        }

        $isCorrect = $this->scoring->isCorrect($question, $answer);
        $manual = $student['manualScores'][$question['id']] ?? null;

        // Graded by the teacher: that grade decides, and is shown with the answer.
        if (is_numeric($manual)) {
            $points = $this->scoring->points($question);
            $isCorrect = (float) $manual >= $points ? true : ((float) $manual <= 0 ? false : null);
            $text = trim(($text === '' ? '(tidak dijawab)' : $text)."\nNilai guru: ".$this->decimal((float) $manual)."/{$points}");
        }

        return match ($isCorrect) {
            true => ['text' => $text, 'mark' => 'correct'],
            false => ['text' => $text === '' ? '(tidak dijawab)' : $text, 'mark' => 'wrong'],
            null => ['text' => $text, 'mark' => 'ungraded'],
        };
    }

    /**
     * How the attempt was handed in, and whether its grade is still provisional.
     *
     * @param  array<string, mixed>  $student
     */
    protected function statusText(array $student): string
    {
        return match (true) {
            $student['isAutoSubmitted'] => 'Waktu habis (dikirim otomatis)',
            $student['lateMinutes'] !== null => "Waktu habis (terlambat {$student['lateMinutes']} menit)",
            $student['isTimeout'] => 'Waktu habis',
            default => 'Selesai',
        }.($student['ungraded'] > 0 ? "; {$student['ungraded']} esai/berkas belum dinilai (nilai sementara)" : '');
    }

    /**
     * "3. Question title (10 poin)" followed by the answer key on the next line.
     *
     * @param  array<string, mixed>  $question
     */
    protected function questionHeader(int $number, array $question): string
    {
        $title = Str::limit($this->plainText((string) ($question['title'] ?? '')), 140) ?: 'Tanpa judul';
        $header = "{$number}. {$title} (".$this->scoring->points($question).' poin)';

        if (($question['type'] ?? '') === 'Paragraph') {
            return $header."\nEsai, dinilai guru";
        }

        if ($this->scoring->isOptionScored($question)) {
            return $header."\nSkor: ".Str::limit($this->choiceScoresText($question), 140);
        }

        if (! $this->scoring->isAutoScored($question)) {
            return $header."\nTanpa kunci jawaban";
        }

        return $header."\nKunci: ".Str::limit($this->answerKeyText($question), 140);
    }

    /**
     * @param  array<string, mixed>  $question
     */
    public function answerKeyText(array $question): string
    {
        $key = $question['answer'] ?? null;
        $options = is_array($question['options'] ?? null) ? $question['options'] : [];
        $type = $question['type'] ?? '';

        if (in_array($type, ['Multiple choice', 'Drop-down', 'Dropdown'], true) && is_numeric($key) && isset($options[(int) $key])) {
            return $this->plainText((string) $options[(int) $key]);
        }

        if ($type === 'Checkboxes' && is_array($key)) {
            return implode(', ', array_map(
                fn ($item) => $this->plainText((string) (is_numeric($item) && isset($options[(int) $item]) ? $options[(int) $item] : $item)),
                $key,
            ));
        }

        return $this->answerText($question, $key);
    }

    /**
     * The headers of the NIS and class columns: Mode pewawancara has the candidate's registration
     * number and school there.
     *
     * @return array{0: string, 1: string}
     */
    protected function identityLabels(QuizForm $quizForm): array
    {
        return $quizForm->isInterview() ? ['No. Pendaftaran', 'Asal Sekolah'] : ['NIS', 'Kelas'];
    }

    /**
     * The score of each choice of a scored questionnaire: "Kemauan sendiri = 3; Orang tua = 1".
     *
     * @param  array<string, mixed>  $question
     */
    public function choiceScoresText(array $question): string
    {
        $choices = array_values((array) ($question[($question['type'] ?? '') === 'Multiple-choice grid' ? 'columns' : 'options'] ?? []));

        return implode('; ', array_map(
            fn (int $score, int $index): string => $this->plainText((string) (is_scalar($choices[$index] ?? null) ? $choices[$index] : '')).' = '.$score,
            $this->scoring->choiceScores($question),
            array_keys($this->scoring->choiceScores($question)),
        ));
    }

    /**
     * A student's answer (or an answer key) as readable text.
     *
     * @param  array<string, mixed>  $question
     */
    public function answerText(array $question, mixed $answer): string
    {
        if ($answer === null || $answer === '' || $answer === []) {
            return '';
        }

        if (in_array($question['type'] ?? '', ['Multiple-choice grid', 'Tick box grid'], true) && is_array($answer)) {
            $rows = is_array($question['rows'] ?? null) ? $question['rows'] : [];
            $columns = is_array($question['columns'] ?? null) ? $question['columns'] : [];
            $lines = [];
            foreach ($answer as $row => $selected) {
                $selectedColumns = array_map(
                    fn ($column) => $this->plainText((string) (is_numeric($column) && isset($columns[(int) $column]) ? $columns[(int) $column] : $column)),
                    array_filter(is_array($selected) ? $selected : [$selected], 'is_scalar'),
                );
                if ($selectedColumns !== []) {
                    $lines[] = $this->plainText((string) ($rows[$row] ?? 'Baris '.((int) $row + 1))).': '.implode(', ', $selectedColumns);
                }
            }

            return implode("\n", $lines);
        }

        // A file handed in for a "File upload" question.
        if (($question['type'] ?? '') === 'File upload' && is_array($answer)) {
            return implode(', ', array_map(
                fn (mixed $file): string => is_array($file) ? $this->plainText((string) ($file['name'] ?? 'berkas')) : '',
                array_is_list($answer) ? $answer : [$answer],
            ));
        }

        if (is_array($answer)) {
            return implode(', ', array_map(
                fn ($item) => is_scalar($item) ? $this->plainText((string) $item) : json_encode($item, JSON_UNESCAPED_UNICODE),
                $answer,
            ));
        }

        return $this->plainText((string) $answer);
    }

    /**
     * Question text may contain HTML from the editor or imports.
     */
    protected function plainText(string $value): string
    {
        $text = html_entity_decode(strip_tags(str_ireplace(['<br>', '<br/>', '<br />', '</p>'], "\n", $value)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $lines = array_map(fn (string $line) => trim((string) preg_replace('/[ \t\x{00A0}]+/u', ' ', $line)), explode("\n", $text));

        return trim(implode("\n", array_filter($lines, fn (string $line) => $line !== '')));
    }

    protected function decimal(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
    }

    protected function now(): CarbonInterface
    {
        return DisplayTime::now();
    }
}
