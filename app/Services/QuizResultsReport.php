<?php

namespace App\Services;

use App\Models\QuizForm;
use App\Models\QuizResponse;
use App\Support\Addon;
use App\Support\QuizScoring;
use App\Support\XlsxWriter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The downloadable Excel report of a quiz: a grade recap and every student's answers.
 */
class QuizResultsReport
{
    protected const SCORE_COLUMNS = 12;

    public function __construct(protected QuizScoring $scoring) {}

    public function filename(QuizForm $quizForm): string
    {
        $slug = Str::slug($quizForm->title) ?: 'kuis';

        return "hasil_jawaban_{$slug}_".$this->now()->format('Ymd_His').'.xlsx';
    }

    /**
     * Build the workbook and write it to the given path.
     */
    public function save(QuizForm $quizForm, string $path): void
    {
        $questions = collect($quizForm->questions ?? [])
            ->filter(fn ($question) => is_array($question) && isset($question['id']))
            ->values()
            ->all();

        // Only questions with an answer key can be earned, so a fully correct sheet is 100.
        $maxScore = $this->scoring->maxPoints($questions);
        $kkm = $quizForm->kkm();
        $students = $this->students($quizForm, $questions, $maxScore);

        $workbook = (new XlsxWriter)
            ->addSheet('Rekap Nilai', ...$this->scoreSheet($quizForm, $students, $maxScore, $kkm))
            ->addSheet('Jawaban Siswa', ...$this->answerSheet($quizForm, $questions, $students));

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

        $rows = [
            [XlsxWriter::cell('Rekap Nilai: '.$this->plainText((string) $quizForm->title), 'title')],
            [XlsxWriter::cell(implode('   •   ', [
                'Diekspor '.$this->now()->format('d/m/Y H:i').' '.$this->now()->format('T'),
                $students->count().' peserta',
                $students->contains(fn (array $student): bool => $student['questionIds'] !== null)
                    ? 'Soal diacak per siswa, skor maksimal mengikuti soal yang didapat'
                    : 'Skor maksimal '.$maxScore,
                'Rata-rata nilai '.$this->decimal($average),
                'KKM '.$this->decimal($kkm),
                "Tuntas {$passed} dari {$students->count()}",
            ]), 'subtitle')],
            null,
            array_map(fn (string $title) => XlsxWriter::cell($title, 'header'), [
                'No', 'NIS', 'Nama Lengkap', 'Kelas', 'Email', 'Skor', 'Skor Maksimal', 'Nilai (0-100)', 'Keterangan', 'Status', 'Waktu Selesai', 'Percobaan',
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
                XlsxWriter::cell($student['score'], 'integer'),
                XlsxWriter::cell($student['maxScore'], 'integer'),
                XlsxWriter::cell($student['grade'], 'decimal'),
                XlsxWriter::cell($isPassed ? 'Tuntas' : 'Belum Tuntas', $isPassed ? 'pass' : 'fail'),
                XlsxWriter::cell(match (true) {
                    $student['isAutoSubmitted'] => 'Waktu habis (dikirim otomatis)',
                    $student['lateMinutes'] !== null => "Waktu habis (terlambat {$student['lateMinutes']} menit)",
                    $student['isTimeout'] => 'Waktu habis',
                    default => 'Selesai',
                }, 'textCenter'),
                XlsxWriter::cell($student['submittedAt'], 'datetime'),
                XlsxWriter::cell("{$student['attempt']} dari {$student['attempts']}", 'textCenter'),
            ];
        }

        $lastRow = max(4, count($rows));

        return [$rows, [
            'columns' => [5, 14, 30, 12, 28, 8, 10, 13, 14, 13, 17, 11],
            'freeze' => 'A5',
            'autoFilter' => "A4:{$lastColumn}{$lastRow}",
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
        $fixedColumns = ['No', 'NIS', 'Nama Lengkap', 'Kelas', 'Nilai'];
        $lastColumn = XlsxWriter::columnLetter(count($fixedColumns) + max(1, count($questions)));

        $header = array_map(fn (string $title) => XlsxWriter::cell($title, 'header'), $fixedColumns);
        foreach ($questions as $number => $question) {
            $header[] = XlsxWriter::cell($this->questionHeader($number + 1, $question), 'headerLeft');
        }

        $rows = [
            [XlsxWriter::cell('Jawaban Siswa: '.$this->plainText((string) $quizForm->title), 'title')],
            [XlsxWriter::cell('Hijau = benar   •   Merah = salah atau tidak dijawab   •   Abu-abu = tidak dinilai otomatis (esai / tanpa kunci jawaban)', 'subtitle')],
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
                if ($student['questionIds'] !== null && ! in_array((string) $question['id'], $student['questionIds'], true)) {
                    $row[] = XlsxWriter::cell('— (soal tidak didapat)', 'ungraded');

                    continue;
                }

                $givenCounts[$questionIndex]++;
                $answer = $student['answers'][$question['id']] ?? null;
                $text = $this->answerText($question, $answer);
                $isCorrect = $this->scoring->isCorrect($question, $answer);

                if ($isCorrect === true) {
                    $correctCounts[$questionIndex]++;
                }

                $row[] = match ($isCorrect) {
                    true => XlsxWriter::cell($text, 'correct'),
                    false => XlsxWriter::cell($text === '' ? '(tidak dijawab)' : $text, 'wrong'),
                    null => XlsxWriter::cell($text, 'ungraded'),
                };
            }

            $rows[] = $row;
        }

        $lastStudentRow = max(4, count($rows));

        // Share of students who answered each automatically scored question correctly.
        if ($students->isNotEmpty() && $questions !== []) {
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
        if ($students->isNotEmpty() && $questions !== []) {
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
        $timezone = (string) config('app.display_timezone', config('app.timezone'));

        $responses = $quizForm->responses()
            ->with(['user:id,name,email,nis,kelas'])
            ->get(['id', 'quiz_form_id', 'user_id', 'respondent_identifier', 'respondent_name', 'respondent_class', 'email', 'score', 'is_timeout', 'is_auto_submitted', 'late_seconds', 'answers', 'question_ids', 'created_at']);

        // Every attempt of a student is listed, numbered in the order they were handed in.
        $attemptNumbers = QuizResponse::attemptNumbers($responses);

        return $responses
            ->map(function (QuizResponse $response) use ($questions, $maxScore, $timezone, $attemptNumbers): array {
                // Scored against the current answer keys, so a corrected key re-grades earlier answers.
                $score = is_array($response->answers) ? $this->scoring->score($questions, $response->answers) : (int) ($response->score ?? 0);
                // With questions drawn per student, the maximum is that of the questions they got.
                $studentMax = $response->question_ids !== null
                    ? $this->scoring->maxPoints($this->scoring->questionsGiven($questions, $response->question_ids))
                    : $maxScore;

                return [
                    'nis' => $response->user?->nis ?: '-',
                    // Guests on a public quiz: the name and class they gave before starting.
                    'name' => $response->user?->name ?: ($response->respondent_name ?: 'Anonim'),
                    'kelas' => $response->user?->kelas ?: ($response->respondent_class ?: '-'),
                    'email' => $response->email ?: ($response->user?->email ?: '-'),
                    'score' => $score,
                    'maxScore' => $studentMax,
                    'grade' => $this->scoring->grade($score, $studentMax),
                    'questionIds' => $response->question_ids !== null ? array_map('strval', $response->question_ids) : null,
                    'isTimeout' => (bool) $response->is_timeout,
                    'isAutoSubmitted' => (bool) $response->is_auto_submitted,
                    'lateMinutes' => $response->late_seconds !== null ? (int) ceil($response->late_seconds / 60) : null,
                    'submittedAt' => $response->created_at?->copy()->setTimezone($timezone),
                    'answers' => is_array($response->answers) ? $response->answers : [],
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

        if (! $this->scoring->isAutoScored($question)) {
            return $header."\nTanpa kunci jawaban";
        }

        return $header."\nKunci: ".Str::limit($this->answerKeyText($question), 140);
    }

    /**
     * @param  array<string, mixed>  $question
     */
    protected function answerKeyText(array $question): string
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
     * A student's answer (or an answer key) as readable text.
     *
     * @param  array<string, mixed>  $question
     */
    protected function answerText(array $question, mixed $answer): string
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

    protected function now(): Carbon
    {
        return now()->setTimezone((string) config('app.display_timezone', config('app.timezone')));
    }
}
