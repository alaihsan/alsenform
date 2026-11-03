<?php

namespace App\Support;

use App\Models\QuizForm;

/**
 * "Skor diisi pewawancara" (Mode pewawancara): the score of each choice is its maximum, and the
 * interviewer types the score the candidate earns for every choice made, from 0 up to it, with
 * decimals. The scores are kept by question and choice ({"12": {"Fluent": 4.5}}); their sum per
 * question counts like a grade given by hand (manual_scores), so the totals, the sections, the
 * results page and the Excel report add them up.
 */
class InterviewScores
{
    /**
     * Decimals a typed score keeps.
     */
    public const DECIMALS = 2;

    public function __construct(protected QuizScoring $scoring) {}

    /**
     * The questions whose scores the interviewer types, by id: none outside Mode pewawancara.
     *
     * @return array<string, array<string, mixed>>
     */
    public function questions(QuizForm $quizForm): array
    {
        if (! $quizForm->isInterview()) {
            return [];
        }

        $questions = [];
        foreach (QuizItems::questions($quizForm->questions) as $question) {
            if (isset($question['id']) && $this->scoring->isScoreEntry($question)) {
                $questions[(string) $question['id']] = $question;
            }
        }

        return $questions;
    }

    /**
     * The maximum of each choice of a question, in the order of its options.
     *
     * @param  array<string, mixed>  $question
     * @return list<int>
     */
    public function maxima(array $question): array
    {
        return array_values($this->scoring->choiceScores($question));
    }

    /**
     * The typed scores that count: only those of choices made, as numbers from 0 up to the maximum
     * of the choice (a score out of range is brought within it), with two decimals.
     *
     * @param  array<int|string, mixed>  $answers
     * @return array<string, array<string, int|float>>
     */
    public function sanitize(QuizForm $quizForm, array $answers, mixed $input): array
    {
        $scores = [];

        foreach ($this->questions($quizForm) as $id => $question) {
            foreach ($this->choicesMade($question, $answers[$id] ?? null) as $choice => $maximum) {
                $value = $this->number($input[$id][$choice] ?? null);
                if ($value !== null) {
                    $scores[$id][$choice] = $this->tidy(min($maximum, max(0, $value)));
                }
            }
        }

        return $scores;
    }

    /**
     * What keeps the answers from being handed in: a choice made without its score, or a score that
     * is not a number from 0 up to the maximum of the choice.
     *
     * @param  array<int|string, mixed>  $answers
     * @return list<string>
     */
    public function problems(QuizForm $quizForm, array $answers, mixed $input): array
    {
        $problems = [];

        foreach ($this->questions($quizForm) as $id => $question) {
            $title = trim(strip_tags((string) ($question['title'] ?? ''))) ?: 'Tanpa judul';

            foreach ($this->choicesMade($question, $answers[$id] ?? null) as $choice => $maximum) {
                $raw = $input[$id][$choice] ?? null;
                $value = $this->number($raw);
                $choiceName = trim(strip_tags($choice));

                if ($raw === null || $raw === '') {
                    $problems[] = "Skor \"{$title}\" ({$choiceName}) belum diisi.";
                } elseif ($value === null || $value < 0 || $value > $maximum) {
                    $problems[] = "Skor \"{$title}\" ({$choiceName}) harus angka 0 sampai {$maximum}.";
                }
            }
        }

        return $problems;
    }

    /**
     * The sum of the typed scores of each question, as grades given by hand.
     *
     * @param  array<string, array<string, int|float>>  $scores
     * @return array<string, int|float>
     */
    public function totals(array $scores): array
    {
        return array_map(fn (array $choices): int|float => $this->tidy(array_sum($choices)), $scores);
    }

    /**
     * The choices an answer made, each with its maximum: one option, or the ticked ones.
     *
     * @param  array<string, mixed>  $question
     * @return array<string, int>
     */
    protected function choicesMade(array $question, mixed $answer): array
    {
        $options = array_map(fn (mixed $option): string => is_scalar($option) ? (string) $option : '', array_values((array) ($question['options'] ?? [])));
        $maxima = $this->maxima($question);
        $chosen = ($question['type'] ?? '') === 'Checkboxes' ? (is_array($answer) ? $answer : []) : [$answer];

        $made = [];
        foreach ($chosen as $choice) {
            $index = is_scalar($choice) && (string) $choice !== '' ? array_search((string) $choice, $options, true) : false;
            if ($index !== false) {
                $made[$options[$index]] = $maxima[$index] ?? 0;
            }
        }

        return $made;
    }

    /**
     * A typed number, with a decimal comma or point ("4,5", "4.5"), or null.
     */
    protected function number(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        if (! is_string($value)) {
            return null;
        }

        $value = str_replace(',', '.', trim($value));

        return is_numeric($value) ? (float) $value : null;
    }

    protected function tidy(float $value): int|float
    {
        $value = round($value, self::DECIMALS);

        return floor($value) === $value ? (int) $value : $value;
    }
}
