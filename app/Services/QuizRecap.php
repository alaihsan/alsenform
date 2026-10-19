<?php

namespace App\Services;

use App\Models\QuizForm;
use App\Models\QuizResponse;
use App\Models\User;
use App\Support\DisplayTime;
use App\Support\QuizItems;
use App\Support\QuizScoring;
use App\Support\XlsxWriter;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Rekap Kuis add-on: one table of a teacher's grades, a row per student and a column per quiz
 * (graded form) of that teacher, oldest first. A cell is the student's best grade on the quiz
 * (0–100), like the Remedial add-on counts it; empty when the student has not handed it in.
 *
 * With a class chosen, every student of that class has a row, so the ones who still owe a quiz
 * show up too. Without one, the students who handed in at least one quiz.
 */
class QuizRecap
{
    public function __construct(protected QuizScoring $scoring) {}

    /**
     * @return array{
     *     quizzes: list<array{id: int, title: string}>,
     *     students: list<array{key: string, name: string, kelas: string, grades: array<int, array{grade: float, provisional: bool}|null>}>,
     *     classes: list<string>,
     *     kelas: string|null
     * }
     */
    public function build(User $teacher, ?string $kelas = null): array
    {
        $quizzes = $this->quizzesOf($teacher);
        $students = $this->studentsWithGrades($quizzes);

        $classes = $students->pluck('kelas')
            ->merge(User::query()->students()->whereNotNull('kelas')->where('kelas', '!=', '')->distinct()->pluck('kelas'))
            ->filter(fn (?string $class): bool => filled($class) && $class !== '-')
            ->unique()
            ->sort(fn (string $a, string $b): int => strnatcasecmp($a, $b))
            ->values();

        $kelas = filled($kelas) && $classes->contains($kelas) ? $kelas : null;

        if ($kelas !== null) {
            $students = $students->where('kelas', $kelas);
            $missing = User::query()->students()->where('kelas', $kelas)->get(['id', 'name', 'kelas'])
                ->reject(fn (User $student): bool => $students->has('user:'.$student->id))
                ->mapWithKeys(fn (User $student): array => ['user:'.$student->id => [
                    'key' => 'user:'.$student->id,
                    'name' => $student->name,
                    'kelas' => $student->kelas,
                    'grades' => [],
                ]]);
            $students = $students->union($missing);
        }

        $quizIds = $quizzes->pluck('id')->all();

        return [
            'quizzes' => $quizzes->map(fn (QuizForm $quiz): array => ['id' => $quiz->id, 'title' => $quiz->title ?: 'Formulir tanpa judul'])->values()->all(),
            'students' => $students
                ->sort(fn (array $a, array $b): int => ($a['kelas'] === '-') <=> ($b['kelas'] === '-')
                    ?: strnatcasecmp($a['kelas'], $b['kelas'])
                    ?: strnatcasecmp($a['name'], $b['name']))
                ->map(fn (array $student): array => [
                    ...$student,
                    // One entry per quiz, in the order of the columns.
                    'grades' => array_map(fn (int $id): ?array => $student['grades'][$id] ?? null, $quizIds),
                ])
                ->values()
                ->all(),
            'classes' => $classes->all(),
            'kelas' => $kelas,
        ];
    }

    /**
     * The recap as an Excel workbook: No, Nama, Kelas, then a column per quiz. Returns the class
     * it was filtered by (null: every class).
     */
    public function save(User $teacher, ?string $kelas, string $path): ?string
    {
        $recap = $this->build($teacher, $kelas);
        $headers = ['No', 'Nama', 'Kelas', ...array_column($recap['quizzes'], 'title')];
        $lastColumn = XlsxWriter::columnLetter(count($headers));
        $now = DisplayTime::now();

        $rows = [
            [XlsxWriter::cell('Rekap Kuis', 'title')],
            [XlsxWriter::cell(implode('   •   ', [
                $teacher->name,
                $recap['kelas'] !== null ? 'Kelas '.$recap['kelas'] : 'Semua kelas',
                count($recap['students']).' siswa, '.count($recap['quizzes']).' kuis',
                'Nilai terbaik tiap siswa, 0–100',
                'Diekspor '.$now->format('d/m/Y H:i').' '.$now->format('T'),
            ]), 'subtitle')],
            null,
            array_map(fn (string $title) => XlsxWriter::cell($title, 'header'), $headers),
        ];

        foreach ($recap['students'] as $index => $student) {
            $rows[] = [
                XlsxWriter::cell($index + 1, 'integer'),
                XlsxWriter::cell($student['name'], 'text'),
                XlsxWriter::cell($student['kelas'], 'textCenter'),
                ...array_map(fn (?array $grade) => XlsxWriter::cell($grade['grade'] ?? null, 'decimal'), $student['grades']),
            ];
        }

        (new XlsxWriter)
            ->addSheet('Rekap Kuis', $rows, [
                'columns' => [6, 30, 10, ...array_fill(0, count($recap['quizzes']), 16)],
                'freeze' => 'D5',
                'autoFilter' => "A4:{$lastColumn}".max(4, count($rows)),
                'merge' => ["A1:{$lastColumn}1", "A2:{$lastColumn}2"],
                'rowHeights' => [1 => 28, 2 => 20],
                'repeatRows' => [4, 4],
                'landscape' => count($recap['quizzes']) > 4,
            ])
            ->save($path, 'Rekap Kuis');

        return $recap['kelas'];
    }

    public function filename(?string $kelas): string
    {
        return 'rekap-kuis'.(filled($kelas) ? '-'.Str::slug($kelas) : '').'-'.now()->format('Y-m-d').'.xlsx';
    }

    /**
     * The teacher's own quizzes, oldest first: graded forms that are published or were answered.
     *
     * @return Collection<int, QuizForm>
     */
    protected function quizzesOf(User $teacher): Collection
    {
        return QuizForm::query()
            ->where('user_id', $teacher->id)
            ->where(fn ($query) => $query->whereNotNull('published_at')->orWhereHas('responses'))
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'user_id', 'title', 'questions', 'settings', 'published_at', 'created_at'])
            ->filter(fn (QuizForm $quiz): bool => ($quiz->settings['isQuiz'] ?? true) !== false)
            ->values();
    }

    /**
     * Every student who handed in one of the quizzes, with their best grade on each, by student key.
     *
     * @param  Collection<int, QuizForm>  $quizzes
     * @return Collection<string, array{key: string, name: string, kelas: string, grades: array<int, array{grade: float, provisional: bool}>}>
     */
    protected function studentsWithGrades(Collection $quizzes): Collection
    {
        $questionsOf = $quizzes->mapWithKeys(fn (QuizForm $quiz): array => [$quiz->id => QuizItems::questions($quiz->questions)]);
        $maxOf = $questionsOf->map(fn (array $questions): int => $this->scoring->maxPoints($questions));

        return QuizResponse::query()
            ->whereIn('quiz_form_id', $quizzes->pluck('id'))
            ->with('user:id,name,kelas')
            ->orderBy('id')
            ->get(['id', 'quiz_form_id', 'user_id', 'respondent_identifier', 'respondent_name', 'respondent_class', 'answers', 'question_ids', 'score', 'manual_scores'])
            ->groupBy(fn (QuizResponse $response): string => $response->studentKey())
            ->map(function (Collection $responses, string $key) use ($questionsOf, $maxOf): array {
                // The latest response names the student: a guest may have corrected their name.
                $latest = $responses->last();

                return [
                    'key' => $key,
                    'name' => $latest->user?->name ?: ($latest->respondent_name ?: 'Anonim'),
                    'kelas' => $latest->user?->kelas ?: ($latest->respondent_class ?: '-'),
                    'grades' => $responses
                        ->groupBy('quiz_form_id')
                        ->map(fn (Collection $attempts, int $quizId): array => $attempts
                            ->map(fn (QuizResponse $response): array => $this->gradeOf($questionsOf[$quizId], $maxOf[$quizId], $response))
                            ->sortByDesc('grade')
                            ->first())
                        ->all(),
                ];
            });
    }

    /**
     * The grade of one attempt, with what the teacher graded by hand; provisional while essays or
     * files still wait for a grade.
     *
     * @param  list<array<string, mixed>>  $questions
     * @return array{grade: float, provisional: bool}
     */
    protected function gradeOf(array $questions, int $maxScore, QuizResponse $response): array
    {
        if (! is_array($response->answers)) {
            return ['grade' => $this->scoring->grade((int) ($response->score ?? 0), $maxScore), 'provisional' => false];
        }

        $result = $this->scoring->result($questions, $response->answers, $response->question_ids, $response->manual_scores);

        return ['grade' => $result['grade'], 'provisional' => $result['ungraded'] > 0];
    }
}
