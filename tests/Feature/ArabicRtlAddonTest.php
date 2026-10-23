<?php

use App\Models\QuizForm;
use App\Models\User;
use App\Services\Addons;
use App\Support\Addon;

/*
 * Teks Arab (RTL): the RTL button in the question toolbar of the editor, installed per teacher (or
 * by an admin for everyone) and not switched per exam. Arabic keeps its font and its direction for
 * students without it.
 */
beforeEach(function () {
    $this->teacher = User::factory()->create(['role' => 'guru']);
    $this->exam = QuizForm::factory()->for($this->teacher)->create([
        'questions' => [['id' => 1, 'title' => 'Terjemahkan: بِسْمِ ٱللَّهِ ٱلرَّحْمَـٰنِ ٱلرَّحِيمِ', 'type' => 'Short answer', 'answer' => '', 'points' => 1]],
    ]);
});

/**
 * The Teks Arab (RTL) entry of a page's add-on catalogue.
 *
 * @param  list<array<string, mixed>>  $catalog
 * @return array<string, mixed>
 */
function rtlAddonOf(array $catalog): array
{
    return collect($catalog)->firstWhere('key', 'arabic-rtl');
}

test('the gallery offers Teks Arab (RTL), shown in the question toolbar and reading nothing', function () {
    $addon = rtlAddonOf($this->actingAs($this->teacher)->get(route('addons.index'))->inertiaProps('addons'));

    expect($addon)
        ->name->toBe('Teks Arab (RTL)')
        ->icon->toBe('languages')
        ->installed->toBeFalse()
        ->perExam->toBeFalse()
        ->onByDefault->toBeTrue()
        ->shownIn->toBe('bilah alat setiap soal di editor')
        ->and($addon['reads'][0])->toStartWith('Tidak membaca data apa pun');
});

test('installed, the RTL button is in the editor of the teacher; removed, it is gone and the questions stay as they are', function () {
    $this->actingAs($this->teacher)->postJson(route('addons.install', 'arabic-rtl'))
        ->assertOk()
        ->assertJsonPath('message', 'Teks Arab (RTL) dipasang.');
    app()->forgetScopedInstances();

    expect(rtlAddonOf($this->get(route('forms.edit', $this->exam))->inertiaProps('quizForm.addons'))['installed'])->toBeTrue();

    $this->deleteJson(route('addons.uninstall', 'arabic-rtl'))
        ->assertOk()
        ->assertJsonPath('message', 'Teks Arab (RTL) dilepas.');
    app()->forgetScopedInstances();

    $editor = $this->get(route('forms.edit', $this->exam));
    expect(rtlAddonOf($editor->inertiaProps('quizForm.addons'))['installed'])->toBeFalse()
        ->and($editor->inertiaProps('quizForm.questions.0.title'))->toBe('Terjemahkan: بِسْمِ ٱللَّهِ ٱلرَّحْمَـٰنِ ٱلرَّحِيمِ');
});

test('the RTL button follows the teacher who edits, and an admin can give it to every teacher', function () {
    $collaborator = User::factory()->create(['role' => 'guru']);
    $this->exam->collaborators()->attach($collaborator->id);
    app(Addons::class)->install(Addon::ArabicRtl, $collaborator);

    expect(rtlAddonOf($this->actingAs($collaborator)->get(route('forms.edit', $this->exam))->inertiaProps('quizForm.addons'))['installed'])->toBeTrue()
        ->and(rtlAddonOf($this->actingAs($this->teacher)->get(route('forms.edit', $this->exam))->inertiaProps('quizForm.addons'))['installed'])->toBeFalse();

    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->postJson(route('addons.install', 'arabic-rtl'), ['everyone' => true])->assertOk();
    app()->forgetScopedInstances();

    expect(rtlAddonOf($this->actingAs($this->teacher)->get(route('forms.edit', $this->exam))->inertiaProps('quizForm.addons')))
        ->installed->toBeTrue()
        ->installedForEveryone->toBeTrue()
        ->canUninstall->toBeFalse();
});
