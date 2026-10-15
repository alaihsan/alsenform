<?php

namespace App\Services;

use App\Models\QuizForm;
use App\Models\QuizResponse;
use App\Support\QuizItems;
use App\Support\QuizScoring;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Peta Miskonsepsi add-on, after the Certainty of Response Index (Hasan, Bagayoko & Kelley): each
 * answer is right or wrong, and given sure or not sure (the student marked "Ragu-ragu"):
 * - right and sure: understood (paham);
 * - right but not sure: a guess (menebak);
 * - wrong and not sure, or left empty: not understood, and the student knows it (belum paham);
 * - wrong and sure: a misconception, held as true, which teaching again alone rarely fixes.
 * A student counts with their first attempt; answers handed in before the "Ragu-ragu" button
 * existed say nothing about certainty and are left out.
 */
class Misconceptions
{
    public const CATEGORIES = ['paham', 'menebak', 'belumPaham', 'miskonsepsi'];

    /**
     * Students that must have answered a question before it is judged.
     */
    public const MIN_TAKERS = 5;

    /**
     * Share of the takers answering wrong while sure from which a question shows a misconception.
     */
    public const FLAG_SHARE = 0.25;

    /**
     * Below this share of students using the "Ragu-ragu" button the results are not trustworthy:
     * a student who never marks anything counts every wrong answer as sure.
     */
    public const MIN_USAGE_SHARE = 0.3;

    protected const CHOICE_TYPES = ['Multiple choice', 'Drop-down', 'Dropdown', 'Checkboxes'];

    public function __construct(protected QuizScoring $scoring = new QuizScoring) {}

    /**
     * @return array<string, mixed>
     */
    public function analyze(QuizForm $quizForm): array
    {
        $questions = QuizItems::questions($quizForm->questions);

        $responses = $quizForm->responses()
            ->with('user:id,name,nis,kelas')
            ->get(['id', 'quiz_form_id', 'user_id', 'respondent_identifier', 'respondent_name', 'respondent_class', 'answers', 'doubtful_question_ids', 'question_ids']);

        return $this->analyzeResponses($questions, $responses);
    }

    /**
     * @param  list<array<string, mixed>>  $questions
     * @param  Collection<int, QuizResponse>  $responses
     * @return array<string, mixed>
     */
    public function analyzeResponses(array $questions, Collection $responses): array
    {
        // A student counts with their first attempt: made without having seen the questions.
        $firstAttempts = $responses
            ->sortBy('id')
            ->unique(fn (QuizResponse $response): string => $response->studentKey())
            ->values();
        $withData = $firstAttempts->filter(fn (QuizResponse $response): bool => is_array($response->doubtful_question_ids))->values();
        $usedDoubt = $withData->filter(fn (QuizResponse $response): bool => $response->doubtful_question_ids !== [])->count();
        $scored = array_values(array_filter($questions, fn (array $question): bool => $this->scoring->isAutoScored($question)));

        $numbers = [];
        foreach ($questions as $index => $question) {
            $numbers[(string) $question['id']] = $index + 1;
        }

        $students = [];
        $tallies = [];
        foreach ($withData as $response) {
            $answers = is_array($response->answers) ? $response->answers : [];
            $doubts = array_map('strval', $response->doubtful_question_ids);
            $given = $response->question_ids !== null ? array_map('strval', $response->question_ids) : null;
            $student = ['miskonsepsi' => [], 'belumPaham' => []];

            foreach ($scored as $question) {
                $id = (string) $question['id'];
                if ($given !== null && ! in_array($id, $given, true)) {
                    continue;
                }

                $answer = $answers[$question['id']] ?? null;
                $category = $this->categoryOf($question, $answer, in_array($id, $doubts, true));
                $tallies[$id][$category] = ($tallies[$id][$category] ?? 0) + 1;

                if ($category === 'miskonsepsi') {
                    $label = $this->answerLabel($question, $answer);
                    $tallies[$id]['wrongAnswers'][$label] = ($tallies[$id]['wrongAnswers'][$label] ?? 0) + 1;
                }
                if ($category === 'miskonsepsi' || $category === 'belumPaham') {
                    $student[$category][] = $numbers[$id];
                }
            }

            $students[] = [
                'name' => $response->user?->name ?: ($response->respondent_name ?: 'Anonim'),
                'nis' => $response->user?->nis ?: '-',
                'kelas' => $response->user?->kelas ?: ($response->respondent_class ?: '-'),
                'miskonsepsi' => $student['miskonsepsi'],
                'belumPaham' => $student['belumPaham'],
            ];
        }

        $analyzed = array_map(fn (array $question): array => $this->question($question, $numbers[(string) $question['id']], $tallies[(string) $question['id']] ?? []), $scored);

        usort($students, fn (array $a, array $b): int => count($b['miskonsepsi']) <=> count($a['miskonsepsi'])
            ?: strnatcasecmp($a['kelas'], $b['kelas'])
            ?: strnatcasecmp($a['name'], $b['name']));

        $respondents = $withData->count();

        return [
            'respondents' => $respondents,
            // First attempts handed in before the button existed: nothing to say about certainty.
            'withoutData' => $firstAttempts->count() - $respondents,
            'usedDoubt' => $usedDoubt,
            'usageShare' => $respondents > 0 ? round($usedDoubt / $respondents, 2) : 0.0,
            'trustworthy' => $respondents > 0 && $usedDoubt / $respondents >= self::MIN_USAGE_SHARE,
            'flagged' => count(array_filter($analyzed, fn (array $question): bool => $question['flagged'])),
            'questions' => $analyzed,
            'students' => $students,
        ];
    }

    /**
     * @param  array<string, mixed>  $question
     */
    public function categoryOf(array $question, mixed $answer, bool $isDoubtful): string
    {
        $isEmpty = $answer === null || $answer === '' || $answer === [];

        return match (true) {
            $isEmpty => 'belumPaham',
            $this->scoring->isCorrect($question, $answer) === true => $isDoubtful ? 'menebak' : 'paham',
            default => $isDoubtful ? 'belumPaham' : 'miskonsepsi',
        };
    }

    /**
     * @param  array<string, mixed>  $question
     * @param  array<string, mixed>  $tally
     * @return array<string, mixed>
     */
    protected function question(array $question, int $number, array $tally): array
    {
        $counts = array_combine(self::CATEGORIES, array_map(fn (string $category): int => $tally[$category] ?? 0, self::CATEGORIES));
        $takers = array_sum($counts);
        $share = fn (string $category): float => $takers > 0 ? round($counts[$category] / $takers, 2) : 0.0;
        $isJudged = $takers >= self::MIN_TAKERS;
        $flagged = $isJudged && $share('miskonsepsi') >= self::FLAG_SHARE;

        $wrongAnswers = $tally['wrongAnswers'] ?? [];
        arsort($wrongAnswers);
        $commonWrong = $wrongAnswers !== [] ? ['answer' => (string) array_key_first($wrongAnswers), 'count' => reset($wrongAnswers)] : null;

        return [
            'id' => $question['id'],
            'number' => $number,
            'title' => Str::limit($this->plain($question['title'] ?? ''), 160) ?: 'Tanpa judul',
            'takers' => $takers,
            ...$counts,
            'shares' => array_combine(self::CATEGORIES, array_map($share, self::CATEGORIES)),
            'flagged' => $flagged,
            'commonWrong' => $commonWrong,
            'note' => match (true) {
                $takers === 0 => 'Belum ada jawaban.',
                ! $isJudged => "Baru {$takers} siswa: kesimpulan muncul setelah minimal ".self::MIN_TAKERS.' siswa.',
                $flagged => 'Banyak siswa yakin dengan jawaban yang salah: kemungkinan miskonsepsi. Bahas mengapa jawaban itu keliru, jangan hanya mengulang materi.',
                $share('menebak') >= 0.3 => 'Banyak jawaban benar disertai ragu: pemahaman belum mantap, beri penguatan dan latihan.',
                $share('belumPaham') >= 0.4 => 'Banyak siswa belum paham dan menyadarinya: ajarkan ulang materi ini.',
                $share('paham') >= 0.7 => 'Sebagian besar siswa paham.',
                default => null,
            },
        ];
    }

    /**
     * A wrong answer as the teacher recognises it: "B. Option" for a choice, else the text.
     *
     * @param  array<string, mixed>  $question
     */
    protected function answerLabel(array $question, mixed $answer): string
    {
        $options = is_array($question['options'] ?? null) ? array_values($question['options']) : [];
        $label = function (mixed $value) use ($question, $options): string {
            $text = $this->plain($value);
            if (in_array($question['type'] ?? '', self::CHOICE_TYPES, true)) {
                foreach ($options as $index => $option) {
                    if (is_scalar($option) && (string) $option === (is_scalar($value) ? (string) $value : null)) {
                        return chr(65 + $index).'. '.Str::limit($this->plain($option), 60);
                    }
                }
            }

            return Str::limit($text, 60);
        };

        return is_array($answer)
            ? implode(', ', array_map($label, array_filter($answer, 'is_scalar')))
            : $label($answer);
    }

    protected function plain(mixed $value): string
    {
        $text = html_entity_decode(strip_tags(is_scalar($value) ? (string) $value : ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/[\s\x{00A0}]+/u', ' ', $text));
    }
}
