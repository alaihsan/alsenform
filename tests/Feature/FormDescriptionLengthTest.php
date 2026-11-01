<?php

use App\Models\QuizForm;
use App\Models\User;
use App\Rules\MaxVisibleCharacters;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->teacher = User::factory()->create(['role' => 'guru']);
    $this->form = QuizForm::factory()->for($this->teacher)->create(['published_at' => now()]);
});

/**
 * Save the form as the editor does, with the given description.
 */
function saveDescription(QuizForm $form, User $teacher, string $description): TestResponse
{
    return test()->actingAs($teacher)->patch(route('forms.update', $form), [
        'title' => $form->title,
        'description' => $description,
        'slug' => $form->slug,
        'questions' => $form->questions,
        'settings' => $form->settings,
        'published' => true,
    ]);
}

test('a description of 2000 characters with formatting is saved in full and shown to students', function () {
    // 2000 characters of text; the bold, underline and highlighter tags are not counted.
    $description = '<b>Petunjuk</b> '.str_repeat('a', 1000).' <mark data-color="yellow"><u>'.str_repeat('ب', 990).'</u></mark>';
    expect(mb_strlen(MaxVisibleCharacters::visibleText($description)))->toBe(QuizForm::DESCRIPTION_MAX_CHARACTERS);

    saveDescription($this->form, $this->teacher, $description)->assertSessionHasNoErrors();

    expect($this->form->refresh()->description)->toBe($description);

    $this->actingAs(User::factory()->create(['role' => 'siswa']))
        ->get(route('forms.public', $this->form->slug))
        ->assertInertia(fn (Assert $page) => $page->where('quizForm.description', $description));
});

test('a description of more than 2000 characters is refused', function () {
    $before = $this->form->description;

    saveDescription($this->form, $this->teacher, str_repeat('a', QuizForm::DESCRIPTION_MAX_CHARACTERS + 1))
        ->assertSessionHasErrors('description');

    expect($this->form->refresh()->description)->toBe($before);
});

test('only the formatting tags are left out of the count', function () {
    expect(MaxVisibleCharacters::visibleText('<b>a</b><i>b</i><u>c</u><mark data-color="green">d</mark> x < y <span>z</span>'))
        ->toBe('abcd x < y <span>z</span>');
});
