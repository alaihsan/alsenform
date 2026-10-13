<?php

namespace App\Support;

/**
 * Automatic scoring of quiz answers against the answer keys stored in the questions.
 * Used when a response is submitted and when the results report marks answers right or wrong.
 */
class QuizScoring
{
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

            if ($this->isCorrect($question, $answers[$id]) === true) {
                $total += $this->points($question);
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
            } elseif ($this->isAutoScored($question)) {
                $max += $points;
                if (array_key_exists($id, $answers) && $this->isCorrect($question, $answers[$id]) === true) {
                    $score += $points;
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
     * Questions only the teacher can grade: essays and files, worth points.
     *
     * @param  array<string, mixed>  $question
     */
    public function needsGrading(array $question): bool
    {
        return in_array($question['type'] ?? '', ['Paragraph', 'File upload'], true) && $this->points($question) > 0;
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
     * Points a question is worth (1 when not set).
     *
     * @param  array<string, mixed>  $question
     */
    public function points(array $question): int
    {
        return isset($question['points']) ? (int) $question['points'] : 1;
    }

    /**
     * Points that can be earned automatically: every question with an answer key. Essays and
     * questions without a key are left out, so a student who answers everything correctly gets 100.
     *
     * @param  array<int, mixed>  $questions
     */
    public function maxPoints(array $questions): int
    {
        $total = 0;
        foreach ($questions as $question) {
            if (is_array($question) && $this->isAutoScored($question)) {
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
     * Whether the question is scored automatically: it has an answer key and is not an essay
     * (which the teacher grades manually).
     *
     * @param  array<string, mixed>  $question
     */
    public function isAutoScored(array $question): bool
    {
        $answerKey = $question['answer'] ?? null;

        return ! ($answerKey === null || $answerKey === '' || $answerKey === [] || ($question['type'] ?? '') === 'Paragraph');
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
