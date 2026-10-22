<?php

use App\Models\QuizForm;
use App\Models\User;
use App\Services\Addons;
use App\Support\Addon;

/*
 * Rumus Matematika and Rumus IPA: formula catalogues in the question toolbar of the editor. They
 * are installed per teacher (or by an admin for everyone), not switched per exam, and the formulas
 * already written in questions stay as they are when they are removed.
 */
beforeEach(function () {
    $this->teacher = User::factory()->create(['role' => 'guru']);
    $this->exam = QuizForm::factory()->for($this->teacher)->create([
        'questions' => [['id' => 1, 'title' => 'Hitung $\frac{1}{2} + \frac{1}{3}$ dan setarakan $\ce{H2 + O2 -> H2O}$', 'type' => 'Short answer', 'answer' => '', 'points' => 1]],
    ]);
});

/**
 * The add-ons of a page's catalogue, by key.
 *
 * @param  list<array<string, mixed>>  $catalog
 * @return array<string, array<string, mixed>>
 */
function addonsByKey(array $catalog): array
{
    return collect($catalog)->keyBy('key')->all();
}

test('the gallery offers both formula add-ons, shown in the question toolbar rather than switched per exam', function () {
    $addons = addonsByKey($this->actingAs($this->teacher)->get(route('addons.index'))->inertiaProps('addons'));

    foreach (['math-formulas' => 'Rumus Matematika', 'science-formulas' => 'Rumus IPA'] as $key => $name) {
        expect($addons[$key])
            ->name->toBe($name)
            ->installed->toBeFalse()
            ->perExam->toBeFalse()
            ->shownIn->toBe('bilah alat setiap soal di editor');
    }
    expect($addons['math-formulas']['icon'])->toBe('sigma')
        ->and($addons['science-formulas']['icon'])->toBe('atom')
        // Rekap Kuis keeps its place, and add-ons switched per exam have none.
        ->and($addons['quiz-recap']['shownIn'])->toBe('menu Aplikasi di Beranda')
        ->and($addons['item-analysis']['shownIn'])->toBeNull()
        ->and($addons['item-analysis']['perExam'])->toBeTrue();
});

test('installed, a formula add-on is in the editor of the teacher, and removing it leaves the questions as they are', function () {
    $this->actingAs($this->teacher)->postJson(route('addons.install', 'math-formulas'))
        ->assertOk()
        ->assertJsonPath('message', 'Rumus Matematika dipasang.');
    app()->forgetScopedInstances();

    $addons = addonsByKey($this->get(route('forms.edit', $this->exam))->inertiaProps('quizForm.addons'));
    expect($addons['math-formulas']['installed'])->toBeTrue()
        ->and($addons['science-formulas']['installed'])->toBeFalse();

    $this->deleteJson(route('addons.uninstall', 'math-formulas'))
        ->assertOk()
        ->assertJsonPath('message', 'Rumus Matematika dilepas.');
    app()->forgetScopedInstances();

    $editor = $this->get(route('forms.edit', $this->exam));
    expect(addonsByKey($editor->inertiaProps('quizForm.addons'))['math-formulas']['installed'])->toBeFalse()
        ->and($editor->inertiaProps('quizForm.questions.0.title'))->toBe('Hitung $\frac{1}{2} + \frac{1}{3}$ dan setarakan $\ce{H2 + O2 -> H2O}$');
});

test('the formula add-ons follow the teacher who edits, not the exam\'s owner', function () {
    $collaborator = User::factory()->create(['role' => 'guru']);
    $this->exam->collaborators()->attach($collaborator->id);
    app(Addons::class)->install(Addon::ScienceFormulas, $collaborator);

    $forCollaborator = addonsByKey($this->actingAs($collaborator)->get(route('forms.edit', $this->exam))->inertiaProps('quizForm.addons'));
    expect($forCollaborator['science-formulas']['installed'])->toBeTrue();

    $forOwner = addonsByKey($this->actingAs($this->teacher)->get(route('forms.edit', $this->exam))->inertiaProps('quizForm.addons'));
    expect($forOwner['science-formulas']['installed'])->toBeFalse();
});

test('an admin installs the formula add-ons for every teacher, which only an admin can remove', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->postJson(route('addons.install', 'science-formulas'), ['everyone' => true])->assertOk();
    app()->forgetScopedInstances();

    $addons = addonsByKey($this->actingAs($this->teacher)->get(route('forms.edit', $this->exam))->inertiaProps('quizForm.addons'));
    expect($addons['science-formulas'])
        ->installed->toBeTrue()
        ->installedForEveryone->toBeTrue()
        ->canUninstall->toBeFalse();
    $this->deleteJson(route('addons.uninstall', 'science-formulas'), ['everyone' => true])->assertForbidden();
});

test('the formula add-ons read nothing and are on wherever they are installed', function () {
    foreach ([Addon::MathFormulas, Addon::ScienceFormulas] as $addon) {
        expect($addon->perExam())->toBeFalse()
            ->and($addon->onByDefault())->toBeTrue()
            ->and($addon->reads())->toHaveCount(1)
            ->and($addon->reads()[0])->toStartWith('Tidak membaca data apa pun');
    }

    app(Addons::class)->install(Addon::MathFormulas, $this->teacher);
    expect($this->exam->refresh()->usesAddon(Addon::MathFormulas))->toBeTrue()
        ->and($this->exam->usesAddon(Addon::ScienceFormulas))->toBeFalse();
});
