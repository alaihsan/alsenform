<?php

namespace App\Support;

use App\Models\AnswerFile;
use App\Models\QuizForm;
use App\Models\QuizSession;
use Illuminate\Support\Str;

/**
 * Answers to "File upload" questions: a list of files the student handed in; and to "Voice answer"
 * questions (Jawaban Suara): the one recording of the student. Only files of the same attempt and
 * question count, described as stored on the server (not as the page says).
 */
class FileAnswers
{
    public const FILE_UPLOAD = 'File upload';

    /** Jawaban Suara: the student answers by speaking, like the speaking part of a TOEFL test. */
    public const VOICE_ANSWER = 'Voice answer';

    /** Question types answered with files kept on the server. */
    public const TYPES = [self::FILE_UPLOAD, self::VOICE_ANSWER];

    public const MAX_FILES_PER_QUESTION = 5;

    /**
     * Whether a question is answered with files (or a recording) kept on the server.
     *
     * @param  array<string, mixed>|mixed  $question
     */
    public static function isFileQuestion(mixed $question): bool
    {
        return is_array($question) && in_array($question['type'] ?? '', self::TYPES, true);
    }

    /**
     * @param  array<string, mixed>|mixed  $question
     */
    public static function isVoiceQuestion(mixed $question): bool
    {
        return is_array($question) && ($question['type'] ?? '') === self::VOICE_ANSWER;
    }

    /**
     * @param  array<int|string, mixed>  $answers
     * @return array<int|string, mixed>
     */
    public function sanitize(QuizForm $quizForm, ?QuizSession $session, array $answers): array
    {
        foreach (is_array($quizForm->questions) ? $quizForm->questions : [] as $question) {
            if (! self::isFileQuestion($question) || ! isset($question['id']) || ! array_key_exists($question['id'], $answers)) {
                continue;
            }

            $id = $question['id'];
            $wanted = collect(is_array($answers[$id]) ? $answers[$id] : [])
                ->map(fn (mixed $file): mixed => is_array($file) ? ($file['id'] ?? null) : $file)
                ->filter(fn (mixed $fileId): bool => is_string($fileId) && Str::isUuid($fileId))
                ->unique()
                ->take(self::isVoiceQuestion($question) ? 1 : self::MAX_FILES_PER_QUESTION)
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
