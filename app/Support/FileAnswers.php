<?php

namespace App\Support;

use App\Models\AnswerFile;
use App\Models\QuizForm;
use App\Models\QuizSession;
use Illuminate\Support\Str;

/**
 * Answers to "File upload" questions: a list of files the student handed in. Only files of the
 * same attempt and question count, described as stored on the server (not as the page says).
 */
class FileAnswers
{
    public const MAX_FILES_PER_QUESTION = 5;

    /**
     * @param  array<int|string, mixed>  $answers
     * @return array<int|string, mixed>
     */
    public function sanitize(QuizForm $quizForm, ?QuizSession $session, array $answers): array
    {
        foreach (is_array($quizForm->questions) ? $quizForm->questions : [] as $question) {
            if (! is_array($question) || ($question['type'] ?? '') !== 'File upload' || ! isset($question['id']) || ! array_key_exists($question['id'], $answers)) {
                continue;
            }

            $id = $question['id'];
            $wanted = collect(is_array($answers[$id]) ? $answers[$id] : [])
                ->map(fn (mixed $file): mixed => is_array($file) ? ($file['id'] ?? null) : $file)
                ->filter(fn (mixed $fileId): bool => is_string($fileId) && Str::isUuid($fileId))
                ->unique()
                ->take(self::MAX_FILES_PER_QUESTION)
                ->values();

            $files = $session === null || $wanted->isEmpty()
                ? collect()
                : AnswerFile::query()
                    ->where('quiz_session_id', $session->id)
                    ->where('question_id', (string) $id)
                    ->whereIn('id', $wanted->all())
                    ->get()
                    ->keyBy('id');

            $answers[$id] = $wanted->map(fn (string $fileId): ?array => $files->get($fileId)?->asAnswer())->filter()->values()->all();

            if ($answers[$id] === []) {
                unset($answers[$id]);
            }
        }

        return $answers;
    }
}
