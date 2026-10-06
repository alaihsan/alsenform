<?php

use App\Models\QuizForm;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->teacher = User::factory()->create(['role' => 'guru', 'nip' => '198001012005011001']);
    $this->quizForm = QuizForm::factory()->create(['user_id' => $this->teacher->id, 'published_at' => now()]);
});

/**
 * @param  list<string>  $titles
 * @return array<string, mixed>
 */
function examSave(QuizForm $quizForm, array $titles, ?string $baseVersion, bool $published = true): array
{
    return [
        'title' => $quizForm->title,
        'description' => $quizForm->description,
        'slug' => $quizForm->slug,
        'questions' => array_map(fn (string $title, int $index): array => [
            'id' => $index + 1,
            'title' => $title,
            'description' => '',
            'type' => 'Short answer',
            'options' => [],
            'answer' => '',
            'required' => false,
            'media' => [],
            'points' => 10,
        ], $titles, array_keys($titles)),
        'settings' => $quizForm->settings,
        'published' => $published,
        'base_version' => $baseVersion,
    ];
}

function editorVersion(QuizForm $quizForm): string
{
    $version = null;
    test()->get(route('forms.edit', ['quizForm' => $quizForm->slug]))
        ->assertOk()
        ->assertInertia(function (Assert $page) use (&$version) {
            $page->component('FormEditor')->has('quizForm.version');
            $version = $page->toArray()['props']['quizForm']['version'];
        });

    return $version;
}

test('opening the editor does not change the version an editor saves against', function () {
    $this->actingAs($this->teacher);

    expect(editorVersion($this->quizForm))->toBe(editorVersion($this->quizForm->refresh()));
});

test('an editor showing an outdated copy cannot overwrite newer questions', function () {
    $this->actingAs($this->teacher);
    $openedOnPhone = editorVersion($this->quizForm);

    // The questions are written on the laptop afterwards.
    $this->patch(route('forms.update', $this->quizForm), examSave($this->quizForm, ['Soal 1', 'Soal 2', 'Soal 3'], $openedOnPhone))
        ->assertRedirect(route('forms.edit', ['quizForm' => $this->quizForm->slug]));

    // The phone still shows the exam as it was (one question, draft) and saves it.
    $this->from(route('forms.edit', ['quizForm' => $this->quizForm->slug]))
        ->patch(route('forms.update', $this->quizForm), examSave($this->quizForm, ['Soal lama'], $openedOnPhone, published: false))
        ->assertRedirect(route('forms.edit', ['quizForm' => $this->quizForm->slug]))
        ->assertSessionHasErrors('conflict');

    $this->quizForm->refresh();
    expect(array_column($this->quizForm->questions, 'title'))->toBe(['Soal 1', 'Soal 2', 'Soal 3'])
        ->and($this->quizForm->published_at)->not->toBeNull();
});

test('consecutive saves from the same editor each build on the version returned by the last one', function () {
    $this->actingAs($this->teacher);

    $version = editorVersion($this->quizForm);
    $this->patch(route('forms.update', $this->quizForm), examSave($this->quizForm, ['Soal 1'], $version))->assertSessionHasNoErrors();

    $version = editorVersion($this->quizForm->refresh());
    $this->patch(route('forms.update', $this->quizForm), examSave($this->quizForm, ['Soal 1', 'Soal 2'], $version))->assertSessionHasNoErrors();

    expect($this->quizForm->refresh()->questions)->toHaveCount(2);
});

test('a save without a version is still accepted', function () {
    $this->actingAs($this->teacher)
        ->patch(route('forms.update', $this->quizForm), examSave($this->quizForm, ['Soal 1', 'Soal 2'], null))
        ->assertSessionHasNoErrors();

    expect($this->quizForm->refresh()->questions)->toHaveCount(2);
});

test('students submitting answers does not change the exam version', function () {
    $this->actingAs($this->teacher);
    $before = editorVersion($this->quizForm);

    $this->quizForm->responses()->create(['answers' => ['1' => 'Option 1'], 'score' => 0]);

    expect(editorVersion($this->quizForm->refresh()))->toBe($before);
});
