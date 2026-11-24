<?php

namespace App\Support;

/**
 * Automatic scoring of quiz answers against the answer keys stored in the questions.
 * Used when a response is submitted and when the results report marks answers right or wrong.
 *
 * A scored questionnaire (Angket berskor) has no answer key: each choice carries its own score
 * (optionScores, or columnScores for a grid), and an answer earns the score of what was chosen.
 */
class QuizScoring
{
    /**
     * The question types whose choices can carry their own score.
     *
     * @var list<string>
     */
    public const OPTION_SCORED_TYPES = ['Multiple choice', 'Drop-down', 'Dropdown', 'Checkboxes', 'Multiple-choice grid'];

    /**
     * The highest score one choice can carry.
     */
    public const MAX_OPTION_SCORE = 100;

    /**
     * The question types whose score an interviewer may type for each choice made ("Skor diisi
     * pewawancara"): one choice, or the ticked ones.
     *
     * @var list<string>
     */
    public const SCORE_ENTRY_TYPES = ['Multiple choice', 'Drop-down', 'Dropdown', 'Checkboxes'];

    /**
     * Total points earned by a set of answers keyed by question id.
     *
     * @param  array<int, array<string, mixed>>  $questions
     * @param  array<int|string, mixed>  $answers
     */
    public function score(array $questions, array $answers): int
    {
        $total = 0;

        foreach ($questions as $question) {
            $id = $question['id'] ?? null;
            if (! $id || ! array_key_exists($id, $answers)) {
                continue;
            }

            $total += $this->earned($question, $answers[$id]);
        }

        return $total;
    }

    /**
     * The points an answer earns without the teacher: the score of the chosen choices on a scored
     * questionnaire, otherwise all of the question's points when it is correct.
     *
     * @param  array<string, mixed>  $question
     */
    public function earned(array $question, mixed $answer): int
    {
        if ($this->isOptionScored($question)) {
            return $this->optionScore($question, $answer);
        }

        return $this->isCorrect($question, $answer) === true ? $this->points($question) : 0;
    }

    /**
     * Whether the question is scored by the score of each choice (a scored questionnaire).
     *
     * @param  array<string, mixed>  $question
     */
    public function isOptionScored(array $question): bool
    {
        $type = $question['type'] ?? '';
        if (! in_array($type, self::OPTION_SCORED_TYPES, true)) {
            return false;
        }

        return $this->choiceScores($question) !== [];
    }

    /**
     * Whether the interviewer types the score of each choice made, from 0 up to the score of that
     * choice (its maximum), instead of the choice earning its score. Only in Mode pewawancara.
     *
     * @param  array<string, mixed>  $question
     */
    public function isScoreEntry(array $question): bool
    {
        return ($question['scoreEntry'] ?? false) === true
            && in_array($question['type'] ?? '', self::SCORE_ENTRY_TYPES, true)
            && $this->isOptionScored($question);
    }

    /**
     * The score of each choice by its position (an option, or a grid column); a choice without
     * a score counts 0.
     *
     * @param  array<string, mixed>  $question
     * @return array<int, int>
     */
    public function choiceScores(array $question): array
    {
        $isGrid = ($question['type'] ?? '') === 'Multiple-choice grid';
        $scores = $question[$isGrid ? 'columnScores' : 'optionScores'] ?? null;
        $choices = $question[$isGrid ? 'columns' : 'options'] ?? null;
        if (! is_array($scores) || ! is_array($choices) || ! array_filter($scores, 'is_numeric')) {
            return [];
        }

        $byChoice = [];
        foreach (array_keys(array_values($choices)) as $index) {
            $score = $scores[$index] ?? null;
            $byChoice[$index] = is_numeric($score) ? max(0, min(self::MAX_OPTION_SCORE, (int) round((float) $score))) : 0;
        }

        return $byChoice;
    }

    /**
     * The score of the choices an answer made: one option, the ticked options, or one column per row.
     *
     * @param  array<string, mixed>  $question
     */
    protected function optionScore(array $question, mixed $answer): int
    {
        $scores = $this->choiceScores($question);

        if (($question['type'] ?? '') === 'Multiple-choice grid') {
            $total = 0;
            foreach (is_array($answer) ? $answer : [] as $column) {
                if (is_numeric($column)) {
                    $total += $scores[(int) $column] ?? 0;
                }
            }

            return $total;
        }

        $options = array_map(fn (mixed $option): string => is_scalar($option) ? (string) $option : '', array_values((array) ($question['options'] ?? [])));
        $chosen = ($question['type'] ?? '') === 'Checkboxes' ? (is_array($answer) ? $answer : []) : [$answer];
        $total = 0;
        foreach (array_unique(array_map(fn (mixed $choice): string => is_scalar($choice) ? (string) $choice : '', $chosen)) as $choice) {
            $index = array_search($choice, $options, true);
            if ($choice !== '' && $index !== false) {
                $total += $scores[$index] ?? 0;
            }
        }

        return $total;
    }

    /**
     * The result of a response, with what the teacher graded by hand: a question the teacher graded
     * counts with that grade (an essay, a file, or a short answer the key judged too strictly). An
     * essay or file not graded yet is left out of both the points and the maximum, and counted as
     * waiting, so the grade is provisional until then.
     *
     * @param  array<int, mixed>  $questions  All questions of the quiz.
     * @param  array<int|string, mixed>  $answers
     * @param  list<int|string>|null  $questionIds  The questions the student got, when drawn.
     * @param  array<int|string, mixed>|null  $manualScores  Points by question id.
     * @return array{score: int|float, max: int, grade: float, ungraded: int}
     */
    public function result(array $questions, array $answers, ?array $questionIds = null, ?array $manualScores = null): array
    {
        $score = 0.0;
        $max = 0;
        $ungraded = 0;

        foreach ($this->questionsGiven($questions, $questionIds) as $question) {
            $id = $question['id'] ?? null;
            if ($id === null) {
                continue;
            }

            $points = max(0, $this->points($question));
            $manual = $manualScores[$id] ?? null;

            if (is_numeric($manual)) {
                $score += min($points, max(0, (float) $manual));
                $max += $points;
            } elseif ($this->isAutoScored($question) || $this->isOptionScored($question)) {
                $max += $points;
                if (array_key_exists($id, $answers)) {
                    $score += $this->earned($question, $answers[$id]);
                }
            } elseif ($this->needsGrading($question)) {
                $ungraded++;
            }
        }

        // Whole points stay whole (only half points from the teacher make a fraction).
        $score = round($score, 2);
        $score = floor($score) === $score ? (int) $score : $score;

        return ['score' => $score, 'max' => $max, 'grade' => $this->grade($score, $max), 'ungraded' => $ungraded];
    }

    /**
     * Questions only the teacher can grade: essays, files and spoken answers, worth points.
     *
     * @param  array<string, mixed>  $question
     */
    public function needsGrading(array $question): bool
    {
        return in_array($question['type'] ?? '', ['Paragraph', ...FileAnswers::TYPES], true) && $this->points($question) > 0;
    }

    /**
     * Questions the teacher may grade by hand: those, and short answers, whose key may be too strict.
     *
     * @param  array<string, mixed>  $question
     */
    public function isGradableByHand(array $question): bool
    {
        return $this->needsGrading($question) || (($question['type'] ?? '') === 'Short answer' && $this->points($question) > 0);
    }

    /**
     * Points a question is worth (1 when not set). On a scored questionnaire: the highest score an
     * answer can earn (the best option, all ticked options, or the best column in every row).
     *
     * @param  array<string, mixed>  $question
     */
    public function points(array $question): int
    {
        if ($this->isOptionScored($question)) {
            $scores = $this->choiceScores($question);

            return match ($question['type'] ?? '') {
                'Checkboxes' => array_sum($scores),
                'Multiple-choice grid' => max($scores) * count(is_array($question['rows'] ?? null) ? $question['rows'] : []),
                default => max($scores),
            };
        }

        return isset($question['points']) ? (int) $question['points'] : 1;
    }

    /**
     * Points that can be earned automatically: every question with an answer key, or with a score
     * for each choice. Essays and questions without a key are left out, so a student who answers
     * everything correctly gets 100.
     *
     * @param  array<int, mixed>  $questions
     */
    public function maxPoints(array $questions): int
    {
        $total = 0;
        foreach ($questions as $question) {
            if (is_array($question) && ($this->isAutoScored($question) || $this->isOptionScored($question))) {
                $total += max(0, $this->points($question));
            }
        }

        return $total;
    }

    /**
     * The questions a response was given: all of them, or the selection drawn for that student
     * (see ExamLayout), whose points alone make up the student's maximum.
     *
     * @param  array<int, mixed>  $questions
     * @param  list<int|string>|null  $questionIds
     * @return list<array<string, mixed>>
     */
    public function questionsGiven(array $questions, ?array $questionIds): array
    {
        $questions = array_values(array_filter($questions, 'is_array'));

        if ($questionIds === null) {
            return $questions;
        }

        $ids = array_map('strval', $questionIds);

        return array_values(array_filter($questions, fn (array $question): bool => in_array((string) ($question['id'] ?? ''), $ids, true)));
    }

    /**
     * The grade on a 0–100 scale: points earned ÷ points that can be earned × 100, two decimals.
     */
    public function grade(int|float $points, int $maxPoints): float
    {
        if ($maxPoints <= 0) {
            return 0.0;
        }

        return round(min(100, max(0, $points / $maxPoints * 100)), 2);
    }

    /**
     * Whether the question is scored automatically against an answer key: it has one, is not an
     * essay (which the teacher grades manually) and is not scored by its choices (which have no
     * right or wrong answer, see isOptionScored).
     *
     * @param  array<string, mixed>  $question
     */
    public function isAutoScored(array $question): bool
    {
        $answerKey = $question['answer'] ?? null;

        return ! ($this->isOptionScored($question) || $answerKey === null || $answerKey === '' || $answerKey === [] || ($question['type'] ?? '') === 'Paragraph');
    }

    /**
     * Whether an answer is correct, or null when the question is not scored automatically.
     *
     * @param  array<string, mixed>  $question
     */
    public function isCorrect(array $question, mixed $answer): ?bool
    {
        if (! $this->isAutoScored($question)) {
            return null;
        }

        $answerKey = $question['answer'];
        $options = is_array($question['options'] ?? null) ? $question['options'] : [];

        return match ($question['type'] ?? '') {
            'Multiple choice', 'Drop-down', 'Dropdown' => $this->isChoiceCorrect($answer, $answerKey, $options),
            'Checkboxes' => $this->areCheckboxesCorrect($answer, $answerKey, $options),
            'Short answer' => is_scalar($answer) && $this->isShortAnswerCorrect((string) $answer, (string) $answerKey),
            'Multiple-choice grid' => $this->isGridCorrect($answer, $answerKey),
            'Tick box grid' => $this->isTickBoxGridCorrect($answer, $answerKey),
            default => $this->isExactMatch($answer, $answerKey),
        };
    }

    /**
     * The score of each section (Bagian) of the form, like the subtotal of every number of an
     * interview sheet. The start of the form is the first section, unless a section comes first
     * (as in SectionFlow); sections without scored questions are left out. Grades given by hand
     * are not counted here: a scored questionnaire has none.
     *
     * @param  array<int, mixed>  $items  All items of the form, sections included.
     * @param  array<int|string, mixed>  $answers
     * @param  list<int|string>|null  $questionIds  The questions the student got, when drawn.
     * @param  array<int|string, mixed>|null  $manualScores  Points by question id: the scores an interviewer typed.
     * @return list<array{index: int, title: string, score: int|float, max: int}>
     */
    public function sectionScores(array $items, array $answers, ?array $questionIds = null, ?array $manualScores = null): array
    {
        $given = $questionIds === null ? null : array_map('strval', $questionIds);
        $sections = [['title' => '', 'score' => 0, 'max' => 0]];
        $isFirstItem = true;

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            if (($item['type'] ?? '') === QuizItems::SECTION) {
                $section = ['title' => trim(strip_tags((string) ($item['title'] ?? ''))), 'score' => 0, 'max' => 0];
                $isFirstItem ? $sections[0] = $section : $sections[] = $section;
                $isFirstItem = false;

                continue;
            }
            $isFirstItem = false;

            $id = $item['id'] ?? null;
            if (! ($this->isAutoScored($item) || $this->isOptionScored($item)) || ($given !== null && ! in_array((string) $id, $given, true))) {
                continue;
            }
            $current = array_key_last($sections);
            $points = max(0, $this->points($item));
            $sections[$current]['max'] += $points;
            if ($id !== null && is_numeric($manualScores[$id] ?? null)) {
                $sections[$current]['score'] += min($points, max(0, (float) $manualScores[$id]));
            } elseif ($id !== null && array_key_exists($id, $answers)) {
                $sections[$current]['score'] += $this->earned($item, $answers[$id]);
            }
        }

        // Typed scores may have decimals; whole sums stay whole.
        foreach ($sections as $index => $section) {
            $score = round((float) $section['score'], 2);
            $sections[$index]['score'] = floor($score) === $score ? (int) $score : $score;
        }

        $numbered = array_map(
            fn (array $section, int $index): array => ['index' => $index, ...$section, 'title' => $section['title'] !== '' ? $section['title'] : 'Bagian '.($index + 1)],
            $sections,
            array_keys($sections),
        );

        return array_values(array_filter($numbered, fn (array $section): bool => $section['max'] > 0));
    }

    /**
     * Short answers accept alternatives ("a | b"), equal numbers ("3,5" = "3.50") and ranges ("9.5..10.5").
     */
    public function isShortAnswerCorrect(string $userAnswer, string $answerKey): bool
    {
        $normalize = fn (string $value): string => (string) preg_replace('/\s+/u', ' ', trim(mb_strtolower($value)));
        $number = function (string $value): ?float {
            $value = str_replace([' ', ','], ['', '.'], trim($value));

            return is_numeric($value) ? (float) $value : null;
        };

        $given = $normalize($userAnswer);
        if ($given === '') {
            return false;
        }

        $givenNumber = $number($given);

        foreach (explode('|', $answerKey) as $accepted) {
            $accepted = $normalize($accepted);
            if ($accepted === '') {
                continue;
            }

            if (preg_match('/^(-?[\d.,]+)\s*\.\.\s*(-?[\d.,]+)$/', $accepted, $range) && $givenNumber !== null) {
                $minimum = $number($range[1]);
                $maximum = $number($range[2]);
                if ($minimum !== null && $maximum !== null && $givenNumber >= min($minimum, $maximum) && $givenNumber <= max($minimum, $maximum)) {
                    return true;
                }

                continue;
            }

            $acceptedNumber = $number($accepted);
            if ($givenNumber !== null && $acceptedNumber !== null) {
                if (abs($givenNumber - $acceptedNumber) < 1e-9) {
                    return true;
                }

                continue;
            }

            if ($given === $accepted) {
                return true;
            }
        }

        return false;
    }

    /**
     * The answer key is an option index or the option text.
     *
     * @param  array<int, mixed>  $options
     */
    protected function isChoiceCorrect(mixed $answer, mixed $answerKey, array $options): bool
    {
        if (! is_scalar($answer) || ! is_scalar($answerKey)) {
            return false;
        }

        if (is_numeric($answerKey) && isset($options[(int) $answerKey])) {
            return $answer === $options[(int) $answerKey] || (string) $answer === (string) $answerKey;
        }

        return (string) $answer === (string) $answerKey;
    }

    /**
     * All and only the correct options must be ticked.
     *
     * @param  array<int, mixed>  $options
     */
    protected function areCheckboxesCorrect(mixed $answer, mixed $answerKey, array $options): bool
    {
        $given = is_array($answer) ? array_values($answer) : [];
        $expected = [];
        foreach (is_array($answerKey) ? $answerKey : [] as $key) {
            $expected[] = is_numeric($key) && isset($options[(int) $key]) ? $options[(int) $key] : (string) $key;
        }

        sort($given);
        sort($expected);

        return $given == $expected;
    }

    /**
     * Every row of the key must have the expected column.
     */
    protected function isGridCorrect(mixed $answer, mixed $answerKey): bool
    {
        if (! is_array($answerKey) || ! is_array($answer)) {
            return false;
        }

        foreach ($answerKey as $row => $column) {
            if (! isset($answer[$row]) || ! is_scalar($answer[$row]) || ! is_scalar($column) || (string) $answer[$row] !== (string) $column) {
                return false;
            }
        }

        return true;
    }

    /**
     * Every row must have exactly the expected set of ticked columns.
     */
    protected function isTickBoxGridCorrect(mixed $answer, mixed $answerKey): bool
    {
        if (! is_array($answerKey) || ! is_array($answer)) {
            return false;
        }

        $normalize = function (array $grid): array {
            $rows = [];
            foreach ($grid as $row => $columns) {
                $columns = array_map('strval', array_filter(is_array($columns) ? $columns : [$columns], 'is_scalar'));
                sort($columns);
                if ($columns !== []) {
                    $rows[(string) $row] = $columns;
                }
            }
            ksort($rows);

            return $rows;
        };

        return $normalize($answer) === $normalize($answerKey);
    }

    protected function isExactMatch(mixed $answer, mixed $answerKey): bool
    {
        if (is_array($answer) || is_array($answerKey)) {
            return is_array($answer) && is_array($answerKey) && json_encode($answer) === json_encode($answerKey);
        }

        return (string) $answer === (string) $answerKey;
    }
}
