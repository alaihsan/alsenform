<?php

namespace App\Support;

use App\Models\QuestionBankItem;
use App\Models\QuizForm;
use App\Models\QuizSession;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

/**
 * What one student gets in an exam: which questions (all, or a random draw), in which order, and
 * the order of the choices. Fixed once per attempt and stored with it, so a reload or another
 * device shows the same exam, and two students next to each other see different ones.
 */
class ExamLayout
{
    public const DIFFICULTIES = ['mudah', 'sedang', 'sulit'];

    /**
     * Question types whose choices may be shuffled. Scales keep their order, grids their rows.
     */
    public const SHUFFLED_CHOICE_TYPES = ['Multiple choice', 'Checkboxes', 'Drop-down'];

    /**
     * Choices that point at other choices ("Semua jawaban benar", "A dan C benar") only make sense
     * in their written order: the choices of such a question are not shuffled.
     */
    protected const POSITIONAL_CHOICE = '/(^|\s)(semua|seluruh|tidak ada|bukan|all|none|both)\b.*\b(benar|salah|jawaban|pilihan|di ?atas|of the above)\b|\b[a-e]\s*(,|dan|&|serta|atau|and|or)\s*[a-e]\b|di ?atas/iu';

    /**
     * The stored layout of the attempt, made now when there is none yet or when the teacher
     * changed what it depends on before the student started.
     *
     * @return array{questions: list<int|string>, options: array<string, list<int>>, draw: bool, signature: string}
     */
    public function ensure(QuizForm $quizForm, QuizSession $session): array
    {
        $layout = $session->layout;
        $signature = $this->signature($quizForm);

        $isCurrent = is_array($layout) && isset($layout['questions'])
            && ($session->started_at !== null || ($layout['signature'] ?? null) === $signature);

        if ($isCurrent) {
            return $layout;
        }

        $layout = $this->build($quizForm, (string) $session->session_token);

        // Concurrent first requests build the same layout (the token is the seed); one is kept.
        QuizSession::query()
            ->whereKey($session->id)
            ->where(fn ($query) => $query->whereNull('layout')->orWhereNull('started_at'))
            ->update(['layout' => json_encode($layout)]);

        $session->layout = $layout;
        $session->syncOriginalAttribute('layout');

        return $layout;
    }

    /**
     * A new layout, the same every time for the same seed and settings.
     *
     * @return array{questions: list<int|string>, options: array<string, list<int>>, draw: bool, signature: string}
     */
    public function build(QuizForm $quizForm, string $seed): array
    {
        $randomizer = new Randomizer(new Xoshiro256StarStar(hash('sha256', $seed, true)));
        $questions = $this->questionsOf($quizForm);
        $isDrawn = $this->drawSettings($quizForm) !== null;

        $chosen = $isDrawn ? $this->draw($quizForm, $questions, $randomizer) : $questions;

        if ($this->setting($quizForm, 'shuffleQuestions') && count($chosen) > 1) {
            $chosen = $randomizer->shuffleArray($chosen);
        }

        $options = [];
        if ($this->setting($quizForm, 'shuffleOptions')) {
            foreach ($chosen as $question) {
                if ($this->choicesMayBeShuffled($question)) {
                    $options[(string) $question['id']] = $randomizer->shuffleArray(array_keys(array_values($question['options'])));
                }
            }
        }

        return [
            'questions' => array_values(array_map(fn (array $question): int|string => $question['id'], $chosen)),
            'options' => $options,
            'draw' => $isDrawn,
            'signature' => $this->signature($quizForm),
        ];
    }

    /**
     * The questions of the attempt in its order, with the choices in its order. Questions the
     * teacher removed are left out; questions added after the start are shown last, unless the
     * exam draws a selection for each student.
     *
     * @param  array<string, mixed>|null  $layout
     * @return list<array<string, mixed>>
     */
    public function arrange(QuizForm $quizForm, ?array $layout): array
    {
        $questions = $this->questionsOf($quizForm);

        if (! is_array($layout) || ! isset($layout['questions'])) {
            return $questions;
        }

        $byId = [];
        foreach ($questions as $question) {
            $byId[(string) $question['id']] = $question;
        }

        $arranged = [];
        foreach ($layout['questions'] as $id) {
            if (isset($byId[(string) $id])) {
                $arranged[(string) $id] = $byId[(string) $id];
            }
        }

        if (empty($layout['draw'])) {
            foreach ($byId as $id => $question) {
                $arranged[$id] ??= $question;
            }
        }

        return array_values(array_map(function (array $question) use ($layout): array {
            $order = $layout['options'][(string) $question['id']] ?? null;
            $choices = is_array($question['options'] ?? null) ? array_values($question['options']) : [];

            if (is_array($order) && count($order) === count($choices) && array_diff(array_keys($choices), $order) === []) {
                $question['options'] = array_values(array_map(fn (int $index): mixed => $choices[$index], $order));
            }

            return $question;
        }, $arranged));
    }

    /**
     * The ids (as strings) of the questions the student is given, or null when they are given
     * every question of the exam.
     *
     * @param  array<string, mixed>|null  $layout
     * @return list<string>|null
     */
    public function assignedIds(QuizForm $quizForm, ?array $layout): ?array
    {
        if (! is_array($layout) || empty($layout['draw'])) {
            return null;
        }

        return array_values(array_map(fn (array $question): string => (string) $question['id'], $this->arrange($quizForm, $layout)));
    }

    /**
     * The draw the teacher set, or null when every student gets every question.
     *
     * @return array{mode: string, count: int, perDifficulty: array<string, int>}|null
     */
    public function drawSettings(QuizForm $quizForm): ?array
    {
        $draw = $quizForm->settings['questionDraw'] ?? null;

        if (! is_array($draw) || ! filter_var($draw['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return null;
        }

        $mode = ($draw['mode'] ?? 'count') === 'difficulty' ? 'difficulty' : 'count';
        $perDifficulty = [];
        foreach (self::DIFFICULTIES as $difficulty) {
            $perDifficulty[$difficulty] = max(0, (int) ($draw['perDifficulty'][$difficulty] ?? 0));
        }

        $count = max(0, (int) ($draw['count'] ?? 0));

        if (($mode === 'count' && $count === 0) || ($mode === 'difficulty' && array_sum($perDifficulty) === 0)) {
            return null;
        }

        return ['mode' => $mode, 'count' => $count, 'perDifficulty' => $perDifficulty];
    }

    /**
     * The difficulty label of each question of the exam (from the question bank), by question id.
     *
     * @return array<string, string>
     */
    public function difficulties(QuizForm $quizForm): array
    {
        return rescue(fn (): array => QuestionBankItem::query()
            ->where('quiz_form_id', $quizForm->id)
            ->whereIn('difficulty', self::DIFFICULTIES)
            ->pluck('difficulty', 'source_question_id')
            ->all(), [], report: false);
    }

    /**
     * How many questions of the exam have each difficulty label, for the draw settings.
     *
     * @return array<string, int>
     */
    public function difficultyCounts(QuizForm $quizForm): array
    {
        $labels = $this->difficulties($quizForm);
        $counts = array_fill_keys([...self::DIFFICULTIES, 'none'], 0);

        foreach ($this->questionsOf($quizForm) as $question) {
            $counts[$labels[(string) $question['id']] ?? 'none']++;
        }

        return $counts;
    }

    /**
     * Pick the questions of one student, kept in the order of the exam. By difficulty, questions
     * without a difficulty label are given to every student.
     *
     * @param  list<array<string, mixed>>  $questions
     * @return list<array<string, mixed>>
     */
    protected function draw(QuizForm $quizForm, array $questions, Randomizer $randomizer): array
    {
        $settings = $this->drawSettings($quizForm);
        $chosenKeys = [];

        if ($settings['mode'] === 'count') {
            $chosenKeys = $this->pick(array_keys($questions), $settings['count'], $randomizer);
        } else {
            $labels = $this->difficulties($quizForm);
            $groups = array_fill_keys(self::DIFFICULTIES, []);

            foreach ($questions as $key => $question) {
                $difficulty = $labels[(string) $question['id']] ?? null;
                if ($difficulty === null) {
                    $chosenKeys[] = $key;
                } else {
                    $groups[$difficulty][] = $key;
                }
            }

            foreach ($groups as $difficulty => $keys) {
                array_push($chosenKeys, ...$this->pick($keys, $settings['perDifficulty'][$difficulty], $randomizer));
            }
        }

        sort($chosenKeys);

        return array_values(array_map(fn (int $key): array => $questions[$key], $chosenKeys));
    }

    /**
     * @param  list<int>  $keys
     * @return list<int>
     */
    protected function pick(array $keys, int $count, Randomizer $randomizer): array
    {
        if ($count <= 0 || $keys === []) {
            return [];
        }

        if ($count >= count($keys)) {
            return $keys;
        }

        return array_values(array_map(fn (int $index): int => $keys[$index], $randomizer->pickArrayKeys($keys, $count)));
    }

    /**
     * @param  array<string, mixed>  $question
     */
    protected function choicesMayBeShuffled(array $question): bool
    {
        $choices = is_array($question['options'] ?? null) ? array_values($question['options']) : [];

        if (! in_array($question['type'] ?? '', self::SHUFFLED_CHOICE_TYPES, true) || count($choices) <= 2) {
            return false;
        }

        foreach ($choices as $choice) {
            $text = is_scalar($choice) ? trim(strip_tags((string) $choice)) : '';
            if (preg_match(self::POSITIONAL_CHOICE, $text) === 1) {
                return false;
            }
        }

        return true;
    }

    /**
     * Everything the layout depends on, to notice a change before the student starts.
     */
    protected function signature(QuizForm $quizForm): string
    {
        return sha1((string) json_encode([
            array_map(fn (array $question): array => [$question['id'], count(is_array($question['options'] ?? null) ? $question['options'] : [])], $this->questionsOf($quizForm)),
            $this->setting($quizForm, 'shuffleQuestions'),
            $this->setting($quizForm, 'shuffleOptions'),
            $this->drawSettings($quizForm),
        ]));
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function questionsOf(QuizForm $quizForm): array
    {
        return array_values(array_filter(
            is_array($quizForm->questions) ? $quizForm->questions : [],
            fn (mixed $question): bool => is_array($question) && isset($question['id']),
        ));
    }

    protected function setting(QuizForm $quizForm, string $key): bool
    {
        return filter_var($quizForm->settings[$key] ?? false, FILTER_VALIDATE_BOOLEAN);
    }
}
