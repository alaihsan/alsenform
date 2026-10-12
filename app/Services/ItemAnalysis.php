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
 */
class ItemAnalysis
{
    /**
     * Below this many students the indices are shown but marked as not yet reliable.
     */
    public const RELIABLE_FROM = 10;

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
        $ranked = $responses
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
            ->sortByDesc('grade')
            ->values();

        $groupSize = $ranked->count() >= 2 ? max(1, (int) round($ranked->count() * self::GROUP_SHARE)) : 0;
        $upper = $ranked->take($groupSize);
        $lower = $groupSize > 0 ? $ranked->slice($ranked->count() - $groupSize) : collect();

        $analyzed = array_map(fn (array $question, int $index): array => $this->analyzeQuestion($question, $index + 1, $ranked, $upper, $lower), $questions, array_keys($questions));

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
            'respondents' => $ranked->count(),
            'reliable' => $ranked->count() >= self::RELIABLE_FROM,
            'summary' => $summary,
            'questions' => $analyzed,
        ];
    }

    /**
     * @param  array<string, mixed>  $question
     * @param  Collection<int, array<string, mixed>>  $all
     * @param  Collection<int, array<string, mixed>>  $upper
     * @param  Collection<int, array<string, mixed>>  $lower
     * @return array<string, mixed>
     */
    protected function analyzeQuestion(array $question, int $number, Collection $all, Collection $upper, Collection $lower): array
    {
        $id = (string) $question['id'];
        $takers = $all->filter(fn (array $student): bool => $student['given'] === null || in_array($id, $student['given'], true));
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

        $correctOf = fn (Collection $students): int => $students->filter(fn (array $student): bool => $this->scoring->isCorrect($question, $student['answers'][$id] ?? $student['answers'][(int) $id] ?? null) === true)->count();
        $correct = $correctOf($takers);
        $p = round($correct / $takers->count(), 2);

        $upperTakers = $upper->filter(fn (array $student): bool => $student['given'] === null || in_array($id, $student['given'], true));
        $lowerTakers = $lower->filter(fn (array $student): bool => $student['given'] === null || in_array($id, $student['given'], true));
        $d = $upperTakers->isNotEmpty() && $lowerTakers->isNotEmpty()
            ? round($correctOf($upperTakers) / $upperTakers->count() - $correctOf($lowerTakers) / $lowerTakers->count(), 2)
            : null;

        [$options, $weakDistractors] = $this->distractors($question, $takers, $id);

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
            'decision' => $d !== null ? $this->decision($d, $weakDistractors !== []) : null,
            'note' => $d !== null && $d < 0 ? 'Siswa bernilai tinggi lebih sering salah: periksa kunci jawabannya.' : null,
        ];
    }

    /**
     * How often each choice was picked, and the wrong choices hardly anyone picked.
     *
     * @param  array<string, mixed>  $question
     * @param  Collection<int, array<string, mixed>>  $takers
     * @return array{0: list<array<string, mixed>>, 1: list<string>}
     */
    protected function distractors(array $question, Collection $takers, string $id): array
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
            $share = round($picked / $takers->count(), 2);
            $letter = chr(65 + $index);

            $options[] = [
                'letter' => $letter,
                'text' => Str::limit($this->plain($text), 80),
                'picked' => $picked,
                'share' => $share,
                'isKey' => $isKey,
                'works' => $isKey ? null : $share >= self::DISTRACTOR_MIN_SHARE,
            ];

            if (! $isKey && $share < self::DISTRACTOR_MIN_SHARE) {
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
     * Keep the question, improve it (weak discrimination or distractors), or replace it.
     *
     * @return array{key: string, label: string}
     */
    protected function decision(float $d, bool $hasWeakDistractors): array
    {
        return match (true) {
            $d <= 0.20 => ['key' => 'ganti', 'label' => 'Ganti'],
            $d <= 0.40 || $hasWeakDistractors => ['key' => 'revisi', 'label' => 'Revisi'],
            default => ['key' => 'dipakai', 'label' => 'Dipakai'],
        };
    }

    protected function plain(mixed $value): string
    {
        $text = html_entity_decode(strip_tags(is_scalar($value) ? (string) $value : ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/[\s\x{00A0}]+/u', ' ', $text));
    }
}
