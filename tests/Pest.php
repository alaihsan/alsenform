<?php

use App\Models\QuizForm;
use App\Models\QuizSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * An exam attempt started on the exam page (timer running), as the page opens and starts it.
 * Exams are handed in from such an attempt, with its session token.
 *
 * @param  array<string, mixed>  $attributes
 */
function startedAttempt(QuizForm $quizForm, ?User $user = null, array $attributes = []): QuizSession
{
    return QuizSession::query()->create(array_merge([
        'quiz_form_id' => $quizForm->id,
        'user_id' => $user?->id,
        'respondent_identifier' => $user ? 'user_'.$user->id : 'anon_'.Str::random(16),
        'session_token' => Str::random(40),
        'started_at' => now(),
        'is_locked' => false,
    ], $attributes));
}
