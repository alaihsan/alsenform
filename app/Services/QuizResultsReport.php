<?php

namespace App\Services;

use App\Models\QuizForm;
use App\Models\QuizResponse;
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
    protected const DEFAULT_KKM = 75;

    protected const SCORE_COLUMNS = 11;

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

        $maxScore = array_sum(array_map(fn (array $question) => $this->scoring->points($question), $questions));
        $kkm = $this->kkm($quizForm);
        $students = $this->students($quizForm, $maxScore);

        (new XlsxWriter)
            ->addSheet('Rekap Nilai', ...$this->scoreSheet($quizForm, $students, $maxScore, $kkm))
            ->addSheet('Jawaban Siswa', ...$this->answerSheet($quizForm, $questions, $students))
            ->save($path, 'Hasil Jawaban - '.$this->plainText((string) $quizForm->title));
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
                'Skor maksimal '.$maxScore,
                'Rata-rata nilai '.$this->decimal($average),
                'KKM '.$this->decimal($kkm),
                "Tuntas {$passed} dari {$students->count()}",
            ]), 'subtitle')],
            null,
            array_map(fn (string $title) => XlsxWriter::cell($title, 'header'), [
                'No', 'NIS', 'Nama Lengkap', 'Kelas', 'Email', 'Skor', 'Skor Maksimal', 'Nilai (0-100)', 'Keterangan', 'Status', 'Waktu Selesai',
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
                XlsxWriter::cell($maxScore, 'integer'),
                XlsxWriter::cell($student['grade'], 'decimal'),
                XlsxWriter::cell($isPassed ? 'Tuntas' : 'Belum Tuntas', $isPassed ? 'pass' : 'fail'),
                XlsxWriter::cell($student['isTimeout'] ? 'Waktu habis' : 'Selesai', 'textCenter'),
                XlsxWriter::cell($student['submittedAt'], 'datetime'),
            ];
        }

        $lastRow = max(4, count($rows));

        return [$rows, [
            'columns' => [5, 14, 30, 12, 28, 8, 10, 13, 14, 13, 17],
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
        foreach ($students->values() as $index => $student) {
            $row = [
                XlsxWriter::cell($index + 1, 'integer'),
                XlsxWriter::cell($student['nis'], 'textCenter'),
                XlsxWriter::cell($student['name'], 'text'),
                XlsxWriter::cell($student['kelas'], 'textCenter'),
                XlsxWriter::cell($student['grade'], 'decimal'),
            ];

            foreach ($questions as $questionIndex => $question) {
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
                $summary[] = $this->scoring->isAutoScored($question)
                    ? XlsxWriter::cell($correctCounts[$questionIndex] / $students->count(), 'summaryPercent')
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
     * The students who responded, sorted by class and name.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function students(QuizForm $quizForm, int $maxScore): Collection
    {
        $timezone = (string) config('app.display_timezone', config('app.timezone'));

        return $quizForm->responses()
            ->with(['user:id,name,email,nis,kelas'])
            ->get(['id', 'quiz_form_id', 'user_id', 'email', 'score', 'is_timeout', 'answers', 'created_at'])
            ->map(function (QuizResponse $response) use ($maxScore, $timezone): array {
                $score = (int) ($response->score ?? 0);

                return [
                    'nis' => $response->user?->nis ?: '-',
                    'name' => $response->user?->name ?: 'Anonim',
                    'kelas' => $response->user?->kelas ?: '-',
                    'email' => $response->email ?: ($response->user?->email ?: '-'),
                    'score' => $score,
                    'grade' => $maxScore > 0 ? round($score / $maxScore * 100, 1) : 0.0,
                    'isTimeout' => (bool) $response->is_timeout,
                    'submittedAt' => $response->created_at?->copy()->setTimezone($timezone),
                    'answers' => is_array($response->answers) ? $response->answers : [],
                ];
            })
            ->sort(fn (array $a, array $b): int => ($a['kelas'] === '-') <=> ($b['kelas'] === '-')
                ?: strnatcasecmp($a['kelas'], $b['kelas'])
                ?: strnatcasecmp($a['name'], $b['name']))
            ->values();
    }

    /**
     * The passing grade: the quiz owner's default KKM from their profile, else 75.
     */
    protected function kkm(QuizForm $quizForm): float
    {
        $kkm = $quizForm->user?->quiz_preferences['default_kkm'] ?? null;

        return is_numeric($kkm) ? (float) $kkm : self::DEFAULT_KKM;
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
        return rtrim(rtrim(number_format($value, 1, ',', '.'), '0'), ',');
    }

    protected function now(): Carbon
    {
        return now()->setTimezone((string) config('app.display_timezone', config('app.timezone')));
    }
}
