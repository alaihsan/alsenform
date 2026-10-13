<?php

namespace App\Services;

use App\Models\QuizForm;
use App\Models\QuizResponse;
use App\Support\QuizScoring;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Item analysis (analisis butir soal) of an exam from the answers of its students:
 * - difficulty index P: the share of students who answered correctly;
 * - discrimination index D: P of the top 27% of students minus P of the bottom 27%;
 * - distractors: how often each wrong choice was picked (it works when picked by at least 5%).
 * Categories follow Arikunto (Dasar-Dasar Evaluasi Pendidikan), common in Indonesian schools.
 *
 * To give the same result however the answers came in:
 * - each question is analysed over the students who got it (questions may be drawn per student),
 *   and its top and bottom groups are 27% of those students;
 * - students with the same grade at the edge of a group share its last places, each counting for
 *   a part, instead of the group taking whoever came first;
 * - a student who took the quiz more than once counts with their first attempt.
 * Below MIN_TAKERS students a question gets its P and the picks of each choice, but no D, no
 * verdict on its distractors and no decision: with so few students they would be chance.
 */
class ItemAnalysis
{
    /**
     * Below this many students the indices are shown but marked as not yet reliable.
     */
    public const RELIABLE_FROM = 10;

    /**
     * Students a question needs before its discrimination, distractors and decision are given.
     */
    public const MIN_TAKERS = 10;

    public const GROUP_SHARE = 0.27;

    public const DISTRACTOR_MIN_SHARE = 0.05;

    protected const CHOICE_TYPES = ['Multiple choice', 'Drop-down', 'Dropdown', 'Checkboxes'];

    public function __construct(protected QuizScoring $scoring = new QuizScoring) {}

    /**
     * @return array{respondents: int, reliable: bool, summary: array<string, int>, questions: list<array<string, mixed>>}
     */
    public function analyze(QuizForm $quizForm): array
    {
        $questions = array_values(array_filter(is_array($quizForm->questions) ? $quizForm->questions : [], fn (mixed $question): bool => is_array($question) && isset($question['id'])));
        $responses = $quizForm->responses()->get(['id', 'user_id', 'respondent_identifier', 'answers', 'question_ids']);

        return $this->analyzeResponses($questions, $responses);
    }

    /**
     * @param  list<array<string, mixed>>  $questions
     * @param  Collection<int, QuizResponse>  $responses
     * @return array{respondents: int, reliable: bool, summary: array<string, int>, questions: list<array<string, mixed>>}
     */
    public function analyzeResponses(array $questions, Collection $responses): array
    {
        $maxAll = $this->scoring->maxPoints($questions);

        // A student who took the quiz more than once counts with their first attempt: the one made
        // without having seen the questions before.
        $responses = $responses
            ->sortBy('id')
            ->unique(fn (QuizResponse $response): string => $response->studentKey())
            ->values();

        // Each student's grade (0–100, out of the questions they got) ranks them for the groups.
        $students = $responses
            ->map(function (QuizResponse $response) use ($questions, $maxAll): array {
                $answers = is_array($response->answers) ? $response->answers : [];
                $given = $response->question_ids !== null ? array_map('strval', $response->question_ids) : null;
                $max = $given !== null ? $this->scoring->maxPoints($this->scoring->questionsGiven($questions, $given)) : $maxAll;

                return [
                    'answers' => $answers,
                    'given' => $given,
                    'grade' => $this->scoring->grade($this->scoring->score($questions, $answers), $max),
                ];
            })
            ->values();

        $analyzed = array_map(fn (array $question, int $index): array => $this->analyzeQuestion($question, $index + 1, $students), $questions, array_keys($questions));

        $summary = ['mudah' => 0, 'sedang' => 0, 'sukar' => 0, 'dipakai' => 0, 'revisi' => 0, 'ganti' => 0];
        foreach ($analyzed as $item) {
            if ($item['difficulty'] !== null) {
                $summary[$item['difficulty']['key']]++;
            }
            if ($item['decision'] !== null) {
                $summary[$item['decision']['key']]++;
            }
        }

        return [
            'respondents' => $students->count(),
            'reliable' => $students->count() >= self::RELIABLE_FROM,
            'summary' => $summary,
            'questions' => $analyzed,
        ];
    }

    /**
     * @param  array<string, mixed>  $question
     * @param  Collection<int, array<string, mixed>>  $students
     * @return array<string, mixed>
     */
    protected function analyzeQuestion(array $question, int $number, Collection $students): array
    {
        $id = (string) $question['id'];
        $takers = $students->filter(fn (array $student): bool => $student['given'] === null || in_array($id, $student['given'], true))->values();
        $base = [
            'id' => $question['id'],
            'number' => $number,
            'title' => Str::limit($this->plain($question['title'] ?? ''), 160) ?: 'Tanpa judul',
            'type' => $question['type'] ?? '',
            'takers' => $takers->count(),
            'analyzed' => false,
            'correct' => null,
            'p' => null,
            'd' => null,
            'difficulty' => null,
            'discrimination' => null,
            'options' => [],
            'weakDistractors' => [],
            'decision' => null,
            'note' => null,
        ];

        if (! $this->scoring->isAutoScored($question)) {
            return [...$base, 'note' => ($question['type'] ?? '') === 'Paragraph' ? 'Esai, dinilai guru' : 'Tanpa kunci jawaban'];
        }

        if ($takers->isEmpty()) {
            return [...$base, 'note' => 'Belum ada siswa yang mengerjakan'];
        }

        $isCorrect = fn (array $student): bool => $this->scoring->isCorrect($question, $student['answers'][$id] ?? $student['answers'][(int) $id] ?? null) === true;
        $correct = $takers->filter($isCorrect)->count();
        $p = round($correct / $takers->count(), 2);
        $hasEnoughTakers = $takers->count() >= self::MIN_TAKERS;

        $d = null;
        if ($hasEnoughTakers) {
            $groupSize = max(1, (int) round($takers->count() * self::GROUP_SHARE));
            $d = round($this->shareCorrect($this->group($takers, $groupSize, top: true), $groupSize, $isCorrect)
                - $this->shareCorrect($this->group($takers, $groupSize, top: false), $groupSize, $isCorrect), 2);
        }

        [$options, $weakDistractors] = $this->distractors($question, $takers, $id, $hasEnoughTakers);
        $decision = $d !== null ? $this->decision($p, $d, $weakDistractors) : null;

        return [
            ...$base,
            'analyzed' => true,
            'correct' => $correct,
            'p' => $p,
            'd' => $d,
            'difficulty' => $this->difficulty($p),
            'discrimination' => $d !== null ? $this->discrimination($d) : null,
            'options' => $options,
            'weakDistractors' => $weakDistractors,
            'decision' => $decision !== null ? ['key' => $decision['key'], 'label' => $decision['label']] : null,
            'note' => match (true) {
                ! $hasEnoughTakers => 'Baru '.$takers->count().' siswa mengerjakan soal ini: daya pembeda, pengecoh dan keputusan muncul setelah minimal '.self::MIN_TAKERS.' siswa.',
                $decision['key'] === 'dipakai' => null,
                default => $decision['reason'],
            },
        ];
    }

    /**
     * The top (or bottom) $size students by grade. Students with the grade at the edge of the group
     * share the places left: each counts for that part, so ties never depend on the order the
     * answers came in.
     *
     * @param  Collection<int, array<string, mixed>>  $takers
     * @return list<array{student: array<string, mixed>, weight: float}>
     */
    protected function group(Collection $takers, int $size, bool $top): array
    {
        $sorted = $takers->sortBy('grade', SORT_REGULAR, $top)->values();
        $edgeGrade = $sorted[$size - 1]['grade'];
        $isBeyondEdge = fn (array $student): bool => $top ? $student['grade'] > $edgeGrade : $student['grade'] < $edgeGrade;

        $beyond = $sorted->filter($isBeyondEdge);
        $atEdge = $sorted->filter(fn (array $student): bool => $student['grade'] == $edgeGrade);
        $edgeWeight = ($size - $beyond->count()) / $atEdge->count();

        return [
            ...$beyond->map(fn (array $student): array => ['student' => $student, 'weight' => 1.0])->all(),
            ...$atEdge->map(fn (array $student): array => ['student' => $student, 'weight' => $edgeWeight])->all(),
        ];
    }

    /**
     * The share of a group that answered correctly.
     *
     * @param  list<array{student: array<string, mixed>, weight: float}>  $group
     */
    protected function shareCorrect(array $group, int $size, callable $isCorrect): float
    {
        $correct = 0.0;
        foreach ($group as $member) {
            if ($isCorrect($member['student'])) {
                $correct += $member['weight'];
            }
        }

        return $correct / $size;
    }

    /**
     * How often each choice was picked, and the wrong choices hardly anyone picked.
     *
     * @param  array<string, mixed>  $question
     * @param  Collection<int, array<string, mixed>>  $takers
     * @return array{0: list<array<string, mixed>>, 1: list<string>}
     */
    protected function distractors(array $question, Collection $takers, string $id, bool $judge = true): array
    {
        if (! in_array($question['type'] ?? '', self::CHOICE_TYPES, true) || ! is_array($question['options'] ?? null)) {
            return [[], []];
        }

        $options = [];
        $weak = [];

        foreach (array_values($question['options']) as $index => $option) {
            $text = is_scalar($option) ? (string) $option : '';
            $picked = $takers->filter(function (array $student) use ($id, $text): bool {
                $answer = $student['answers'][$id] ?? $student['answers'][(int) $id] ?? null;

                return is_array($answer) ? in_array($text, $answer, true) : $answer === $text;
            })->count();
            $isKey = $this->isKey($question, $index, $text);
            $works = $picked / $takers->count() >= self::DISTRACTOR_MIN_SHARE;
            $share = round($picked / $takers->count(), 2);
            $letter = chr(65 + $index);

            $options[] = [
                'letter' => $letter,
                'text' => Str::limit($this->plain($text), 80),
                'picked' => $picked,
                'share' => $share,
                'isKey' => $isKey,
                // Too few students to say whether a choice works: only the picks are shown.
                'works' => $isKey || ! $judge ? null : $works,
            ];

            if ($judge && ! $isKey && ! $works) {
                $weak[] = $letter;
            }
        }

        return [$options, $weak];
    }

    /**
     * @param  array<string, mixed>  $question
     */
    protected function isKey(array $question, int $index, string $text): bool
    {
        $keys = is_array($question['answer'] ?? null) ? $question['answer'] : [$question['answer'] ?? null];

        foreach ($keys as $key) {
            if ((is_int($key) || (is_string($key) && ctype_digit($key))) && (int) $key === $index) {
                return true;
            }
            if (is_string($key) && ! ctype_digit($key) && $key === $text) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{key: string, label: string}
     */
    protected function difficulty(float $p): array
    {
        return match (true) {
            $p > 0.70 => ['key' => 'mudah', 'label' => 'Mudah'],
            $p > 0.30 => ['key' => 'sedang', 'label' => 'Sedang'],
            default => ['key' => 'sukar', 'label' => 'Sukar'],
        };
    }

    /**
     * @return array{key: string, label: string}
     */
    protected function discrimination(float $d): array
    {
        return match (true) {
            $d < 0 => ['key' => 'negatif', 'label' => 'Negatif'],
            $d <= 0.20 => ['key' => 'jelek', 'label' => 'Jelek'],
            $d <= 0.40 => ['key' => 'cukup', 'label' => 'Cukup'],
            $d <= 0.70 => ['key' => 'baik', 'label' => 'Baik'],
            default => ['key' => 'baik_sekali', 'label' => 'Baik sekali'],
        };
    }

    /**
     * Keep the question, improve it, or replace it, and why. The discrimination decides
     * (D ≤ 0,20 replace, D ≤ 0,40 revise); a question that discriminates well but has distractors
     * nobody picks is revised too.
     *
     * @param  list<string>  $weakDistractors
     * @return array{key: string, label: string, reason: string}
     */
    protected function decision(float $p, float $d, array $weakDistractors): array
    {
        $distractors = $weakDistractors !== []
            ? ' Pengecoh '.implode(', ', $weakDistractors).' hampir tidak dipilih: ganti dengan pengecoh yang lebih masuk akal.'
            : '';

        return match (true) {
            $d < 0 => ['key' => 'ganti', 'label' => 'Ganti', 'reason' => 'Siswa bernilai tinggi lebih sering salah daripada yang bernilai rendah: periksa kunci jawabannya.'],
            $d <= 0.20 => ['key' => 'ganti', 'label' => 'Ganti', 'reason' => match (true) {
                $p >= 0.90 => 'Terlalu mudah: hampir semua siswa menjawab benar, sehingga tidak membedakan siswa.',
                $p <= 0.10 => 'Terlalu sukar: hampir semua siswa menjawab salah, sehingga tidak membedakan siswa.',
                default => 'Daya pembeda jelek: siswa bernilai tinggi dan rendah sama seringnya menjawab benar.',
            }],
            $d <= 0.40 => ['key' => 'revisi', 'label' => 'Revisi', 'reason' => 'Daya pembeda cukup: perjelas kalimat soal atau perbaiki pilihannya.'.$distractors],
            $weakDistractors !== [] => ['key' => 'revisi', 'label' => 'Revisi', 'reason' => 'Daya pembeda baik.'.$distractors],
            default => ['key' => 'dipakai', 'label' => 'Dipakai', 'reason' => 'Soal baik: membedakan siswa bernilai tinggi dan rendah.'],
        };
    }

    protected function plain(mixed $value): string
    {
        $text = html_entity_decode(strip_tags(is_scalar($value) ? (string) $value : ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/[\s\x{00A0}]+/u', ' ', $text));
    }
}
