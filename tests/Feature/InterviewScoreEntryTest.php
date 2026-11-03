<?php

use App\Models\QuizForm;
use App\Models\QuizResponse;
use App\Models\User;
use App\Services\QuizResultsReport;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Mode pewawancara, "Skor diisi pewawancara": the score of each choice is its maximum, and the
 * interviewer types the score the candidate earns for every choice made, from 0 up to it, with
 * decimals. The sum per question counts like a grade given by hand.
 */

beforeEach(function () {
    $this->teacher = User::factory()->create(['role' => 'guru']);
    $this->interview = QuizForm::factory()->for($this->teacher)->create([
        'title' => 'Tes Wawancara Bahasa Inggris',
        'published_at' => now(),
        'questions' => [
            ['id' => 10, 'title' => 'A. Speaking', 'type' => 'Section'],
            ['id' => 1, 'title' => 'Grammar', 'type' => 'Multiple choice', 'required' => true, 'scoreEntry' => true,
                'options' => ['No morphology syntax error', 'Generally accurate', 'Basic structure error'], 'optionScores' => [10, 5, 5]],
            ['id' => 2, 'title' => 'Bacaan sholat', 'type' => 'Checkboxes', 'scoreEntry' => true,
                'options' => ['Iftitah', "I'tidal", 'Tasyahud akhir'], 'optionScores' => [5, 5, 5]],
            // Scored by the choice itself, as before.
            ['id' => 3, 'title' => 'Motivasi', 'type' => 'Multiple choice',
                'options' => ['Kemauan sendiri', 'Kemauan orang tua'], 'optionScores' => [3, 1]],
        ],
        'settings' => ['isQuiz' => true, 'optionScoring' => true, 'interviewMode' => true, 'showResultsSummary' => true, 'resultsDetail' => 'full'],
    ]);
    $this->candidate = ['respondent_name' => 'Ahmad Fauzi', 'respondent_number' => 'PMB-001', 'respondent_school' => 'SDN 1 Bekasi'];
});

/**
 * Open the interview, name the candidate and start, as the page does: the attempt's token and the
 * questions the interviewer sees.
 *
 * @return array{0: string, 1: list<array<string, mixed>>}
 */
function startInterview(QuizForm $form, User $interviewer, array $candidate): array
{
    $token = test()->actingAs($interviewer)->get(route('forms.public', $form->slug))->inertiaProps('session.token');
    $questions = test()->actingAs($interviewer)->postJson(route('forms.responses.start', $form->slug), ['session_token' => $token, ...$candidate])
        ->assertOk()
        ->json('questions');

    return [$token, $questions];
}

test('the interviewer sees the maximum of each choice, only where the score is typed', function () {
    [, $questions] = startInterview($this->interview, $this->teacher, $this->candidate);
    $byId = collect($questions)->keyBy('id');

    expect($byId[1]['scoreMaxima'])->toBe([10, 5, 5])
        ->and($byId[2]['scoreMaxima'])->toBe([5, 5, 5])
        ->and($byId[3])->not->toHaveKey('scoreMaxima')
        ->and($byId[1])->not->toHaveKey('optionScores');
});

test('the typed scores count, with decimals, in the total, the sections, the results page and the gradebook', function () {
    [$token] = startInterview($this->interview, $this->teacher, $this->candidate);

    $this->actingAs($this->teacher)->postJson(route('forms.responses.store', $this->interview->slug), [
        'session_token' => $token,
        'answers' => [1 => 'Generally accurate', 2 => ['Iftitah', 'Tasyahud akhir'], 3 => 'Kemauan sendiri'],
        // A decimal comma or point; a score for a choice not made is left out.
        'scores' => [1 => ['Generally accurate' => '4,5'], 2 => ['Iftitah' => 5, 'Tasyahud akhir' => '3.25', "I'tidal" => 5]],
    ])->assertOk();

    $response = QuizResponse::query()->sole();
    expect($response->entered_scores)->toBe(['1' => ['Generally accurate' => 4.5], '2' => ['Iftitah' => 5, 'Tasyahud akhir' => 3.25]])
        ->and($response->manual_scores)->toBe(['1' => 4.5, '2' => 8.25]);

    // 4,5 + 8,25 typed, and 3 for the choice scored by itself: 15,75 of 10 + 15 + 3.
    $this->actingAs($this->teacher)->get(route('forms.edit', $this->interview))
        ->assertInertia(fn (Assert $page) => $page
            ->where('quizForm.responses.gradebook.0.score', 15.75)
            ->where('quizForm.responses.gradebook.0.maxScore', 28));

    $this->actingAs($this->teacher)->get(route('forms.results', $this->interview->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('attempts.0.sections.0.score', 15.75)
            ->where('attempts.0.questions.0.earned', 4.5)
            ->where('attempts.0.questions.0.isCorrect', null)
            ->where('attempts.0.questions.1.earned', 8.25));
});

test('a choice made without its score, or with one out of range, is not handed in', function () {
    [$token] = startInterview($this->interview, $this->teacher, $this->candidate);
    $hand = fn (array $scores) => $this->actingAs($this->teacher)->postJson(route('forms.responses.store', $this->interview->slug), [
        'session_token' => $token,
        'answers' => [1 => 'Generally accurate', 2 => ['Iftitah']],
        'scores' => $scores,
    ]);

    $hand([1 => ['Generally accurate' => '4,5']])
        ->assertUnprocessable()
        ->assertJsonPath('errors.scores.0', 'Skor "Bacaan sholat" (Iftitah) belum diisi.');
    $hand([1 => ['Generally accurate' => '7'], 2 => ['Iftitah' => '-1']])
        ->assertUnprocessable()
        ->assertJsonPath('errors.scores', ['Skor "Grammar" (Generally accurate) harus angka 0 sampai 5.', 'Skor "Bacaan sholat" (Iftitah) harus angka 0 sampai 5.']);

    expect(QuizResponse::query()->count())->toBe(0);
});

test('a page opened before the scores were typed hands in with the score of each choice', function () {
    [$token] = startInterview($this->interview, $this->teacher, $this->candidate);

    // The page sends no scores at all: the choices count with their own score.
    $this->actingAs($this->teacher)->postJson(route('forms.responses.store', $this->interview->slug), [
        'session_token' => $token,
        'answers' => [1 => 'Generally accurate', 2 => ['Iftitah']],
    ])->assertOk();

    expect(QuizResponse::query()->sole())->manual_scores->toBeNull()->entered_scores->toBeNull()->score->toBe(10);
});

test('the scores typed so far are saved with the draft and come back with it', function () {
    [$token] = startInterview($this->interview, $this->teacher, $this->candidate);

    $this->actingAs($this->teacher)->postJson(route('forms.responses.draft', $this->interview->slug), [
        'session_token' => $token,
        'answers' => [1 => 'No morphology syntax error'],
        'scores' => [1 => ['No morphology syntax error' => '9,5', 'Generally accurate' => '5']],
    ])->assertOk()->assertJsonPath('saved', true);

    $this->actingAs($this->teacher)->get(route('forms.public', $this->interview->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('session.draft_scores', ['1' => ['No morphology syntax error' => 9.5]]));
});

test('outside Mode pewawancara the choices earn their own score, and nothing is typed', function () {
    $this->interview->update(['settings' => [...$this->interview->settings, 'interviewMode' => false]]);
    $student = User::factory()->create(['role' => 'siswa']);
    $token = $this->actingAs($student)->get(route('forms.public', $this->interview->slug))->inertiaProps('session.token');
    $questions = $this->actingAs($student)->postJson(route('forms.responses.start', $this->interview->slug), ['session_token' => $token])->json('questions');

    $this->actingAs($student)->postJson(route('forms.responses.store', $this->interview->slug), [
        'session_token' => $token,
        'answers' => [1 => 'Generally accurate'],
        'scores' => [1 => ['Generally accurate' => '1']],
    ])->assertOk();

    expect(collect($questions)->pluck('scoreMaxima')->filter())->toBeEmpty()
        ->and(QuizResponse::query()->sole())->manual_scores->toBeNull()->score->toBe(5);
});

test('the Excel answers show every typed score out of its maximum', function () {
    [$token] = startInterview($this->interview, $this->teacher, $this->candidate);
    $this->actingAs($this->teacher)->postJson(route('forms.responses.store', $this->interview->slug), [
        'session_token' => $token,
        'answers' => [1 => 'Generally accurate', 2 => ['Iftitah', "I'tidal"]],
        'scores' => [1 => ['Generally accurate' => '4,5'], 2 => ['Iftitah' => 5, "I'tidal" => '2,5']],
    ])->assertOk();
    $path = tempnam(sys_get_temp_dir(), 'interview-scores-');

    app(QuizResultsReport::class)->save($this->interview->refresh(), $path);

    $zip = new ZipArchive;
    $zip->open($path);
    $answers = html_entity_decode((string) $zip->getFromName('xl/worksheets/sheet2.xml'), ENT_QUOTES | ENT_XML1);
    $sections = (string) $zip->getFromName('xl/worksheets/sheet3.xml');
    $zip->close();
    @unlink($path);

    expect($answers)->toContain("1. Grammar (10 poin)\nSkor maks.: No morphology syntax error = 10")
        ->toContain("Generally accurate: 4,5 (maks. 5)\nSkor: 4,5")
        ->toContain("Iftitah: 5 (maks. 5)\nI'tidal: 2,5 (maks. 5)\nSkor: 7,5")
        ->and($sections)->toContain('<v>12</v>');
});

test('the editor keeps whether the interviewer types the score', function () {
    $questions = $this->interview->questions;
    $questions[3]['scoreEntry'] = true;

    $this->actingAs($this->teacher)->patch(route('forms.update', $this->interview), [
        'title' => $this->interview->title,
        'slug' => $this->interview->slug,
        'questions' => $questions,
        'settings' => $this->interview->settings,
        'published' => true,
    ])->assertSessionHasNoErrors();

    expect(collect($this->interview->refresh()->questions)->pluck('scoreEntry', 'id')->filter()->keys()->all())->toBe([1, 2, 3]);
});
