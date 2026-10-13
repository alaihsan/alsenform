<?php

namespace App\Support;

use App\Models\QuizForm;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The token of the Token Ujian add-on, like the token of ANBK: six letters the proctor shows in
 * the room, which a student types before starting. It is derived from the exam and the clock of
 * the school server (nothing is stored) and changes every 15 minutes, at :00, :15, :30 and :45.
 */
class ExamToken
{
    public const LENGTH = 6;

    public const ROTATE_SECONDS = 900;

    /**
     * A token that was just replaced is still accepted for a while: read aloud as it changed,
     * or typed slowly on a weak connection.
     */
    public const GRACE_SECONDS = 300;

    /**
     * Wrong tokens a student may type per minute before having to wait.
     */
    public const MAX_WRONG_PER_MINUTE = 10;

    /**
     * Capital letters without I and O, which are mistaken for 1 and 0.
     */
    protected const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ';

    /**
     * The token of the exam at a moment (now by default).
     */
    public function current(QuizForm $quizForm, ?CarbonInterface $at = null): string
    {
        return $this->ofWindow($quizForm, $this->window($at));
    }

    /**
     * The token replaced at the start of the current window, while it is still accepted.
     */
    public function previousStillAccepted(QuizForm $quizForm, ?CarbonInterface $at = null): ?string
    {
        return $this->previousAcceptedUntil($at) !== null
            ? $this->ofWindow($quizForm, $this->window($at) - 1)
            : null;
    }

    /**
     * Until when the token just replaced is still accepted, or null when it no longer is.
     */
    public function previousAcceptedUntil(?CarbonInterface $at = null): ?CarbonImmutable
    {
        $at ??= now();
        $until = $this->window($at) * self::ROTATE_SECONDS + self::GRACE_SECONDS;

        return $at->getTimestamp() < $until ? CarbonImmutable::createFromTimestamp($until, $at->getTimezone()) : null;
    }

    /**
     * When the current token is replaced.
     */
    public function changesAt(?CarbonInterface $at = null): CarbonImmutable
    {
        return CarbonImmutable::createFromTimestamp(($this->window($at) + 1) * self::ROTATE_SECONDS, ($at ?? now())->getTimezone());
    }

    /**
     * Whether a typed token is the current one or the one just replaced. Lower case letters and
     * spaces are accepted.
     */
    public function accepts(QuizForm $quizForm, ?string $typed, ?CarbonInterface $at = null): bool
    {
        $typed = $this->normalize($typed);

        if (strlen($typed) !== self::LENGTH) {
            return false;
        }

        $previous = $this->previousStillAccepted($quizForm, $at);

        return hash_equals($this->current($quizForm, $at), $typed)
            || ($previous !== null && hash_equals($previous, $typed));
    }

    public function normalize(?string $typed): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z]/', '', (string) $typed));
    }

    protected function window(?CarbonInterface $at = null): int
    {
        return intdiv(($at ?? now())->getTimestamp(), self::ROTATE_SECONDS);
    }

    /**
     * Six letters from a keyed hash of the exam and the window: unguessable without the app key.
     */
    protected function ofWindow(QuizForm $quizForm, int $window): string
    {
        $bytes = hash_hmac('sha256', "exam-token|{$quizForm->getKey()}|{$window}", (string) config('app.key'), true);
        $alphabetLength = strlen(self::ALPHABET);
        $token = '';

        // Four bytes per letter, so every letter is (almost exactly) as likely.
        foreach (array_values(unpack('N'.self::LENGTH, $bytes)) as $number) {
            $token .= self::ALPHABET[$number % $alphabetLength];
        }

        return $token;
    }
}
