<?php

use App\Models\InstalledAddon;
use App\Models\QuizForm;
use App\Models\QuizResponse;
use App\Models\User;
use App\Services\Addons;
use App\Support\Addon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->teacher = User::factory()->create(['role' => 'guru']);
    $this->exam = QuizForm::factory()->for($this->teacher)->create([
        'published_at' => now(),
        'questions' => [['id' => 1, 'title' => '2 + 2 = ?', 'type' => 'Multiple choice', 'options' => ['3', '4', '5'], 'answer' => '4', 'points' => 1]],
        'settings' => ['collectEmail' => false],
    ]);
    QuizResponse::query()->create(['quiz_form_id' => $this->exam->id, 'respondent_identifier' => 'anon_1', 'answers' => [1 => '4'], 'score' => 1]);
});

/**
 * The sheet names of the exam's Excel workbook.
 */
function workbookSheets(QuizForm $exam, User $as): string
{
    $export = test()->actingAs($as)->get(route('forms.responses.export', $exam));
    $zip = new ZipArchive;
    $zip->open($export->baseResponse->getFile()->getPathname());
    $workbook = (string) $zip->getFromName('xl/workbook.xml');
    $zip->close();

    return $workbook;
}

test('a teacher installs an add-on from the gallery and it is on for their exams', function () {
    $this->actingAs($this->teacher)->get(route('addons.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Addons/Index')
            ->where('addons.0.key', 'item-analysis')
            ->where('addons.0.installed', false)
            ->where('canInstallForEveryone', false));

    expect($this->exam->usesAddon(Addon::ItemAnalysis))->toBeFalse();

    $this->actingAs($this->teacher)->postJson(route('addons.install', 'item-analysis'))
        ->assertOk()
        ->assertJsonPath('addons.0.installed', true)
        ->assertJsonPath('addons.0.canUninstall', true);

    app()->forgetScopedInstances();
    expect($this->exam->refresh()->usesAddon(Addon::ItemAnalysis))->toBeTrue();
});

test('without the add-on there is no analysis, no Excel sheet and no difficulty labelling', function () {
    $this->actingAs($this->teacher)->get(route('forms.edit', $this->exam))
        ->assertInertia(fn (Assert $page) => $page
            ->where('quizForm.responses.analysis', null)
            ->where('quizForm.addons.0.installed', false)
            ->where('quizForm.addons.0.activeOnExam', false));

    expect(workbookSheets($this->exam, $this->teacher))->not->toContain('Analisis Butir Soal');

    $this->actingAs($this->teacher)->postJson(route('forms.analysis.apply-difficulty', $this->exam))->assertForbidden();
});

test('installed, the analysis shows in the results and in the Excel workbook; switched off for one exam, it is gone there', function () {
    app(Addons::class)->install(Addon::ItemAnalysis, $this->teacher);

    $this->actingAs($this->teacher)->get(route('forms.edit', $this->exam))
        ->assertInertia(fn (Assert $page) => $page
            ->where('quizForm.responses.analysis.respondents', 1)
            ->where('quizForm.addons.0.activeOnExam', true));
    expect(workbookSheets($this->exam, $this->teacher))->toContain('Analisis Butir Soal');

    // The teacher switches it off for this exam in the editor (saved with the exam settings).
    $this->actingAs($this->teacher)->patch(route('forms.update', $this->exam), [
        'title' => $this->exam->title,
        'slug' => $this->exam->slug,
        'questions' => $this->exam->questions,
        'settings' => [...$this->exam->settings, 'addons' => ['item-analysis' => false]],
    ])->assertSessionHasNoErrors();
    app()->forgetScopedInstances();

    $this->actingAs($this->teacher)->get(route('forms.edit', $this->exam))
        ->assertInertia(fn (Assert $page) => $page
            ->where('quizForm.responses.analysis', null)
            ->where('quizForm.addons.0.installed', true)
            ->where('quizForm.addons.0.activeOnExam', false));
    expect(workbookSheets($this->exam->refresh(), $this->teacher))->not->toContain('Analisis Butir Soal');
    $this->actingAs($this->teacher)->postJson(route('forms.analysis.apply-difficulty', $this->exam))->assertForbidden();
});

test('uninstalling turns it off on all the teacher\'s exams, and reinstalling brings back each exam\'s choice', function () {
    $addons = app(Addons::class);
    $addons->install(Addon::ItemAnalysis, $this->teacher);
    $switchedOff = QuizForm::factory()->for($this->teacher)->create(['settings' => ['addons' => ['item-analysis' => false]]]);

    $this->actingAs($this->teacher)->deleteJson(route('addons.uninstall', 'item-analysis'))->assertOk()->assertJsonPath('addons.0.installed', false);
    app()->forgetScopedInstances();
    expect($this->exam->refresh()->usesAddon(Addon::ItemAnalysis))->toBeFalse();

    app(Addons::class)->install(Addon::ItemAnalysis, $this->teacher);
    expect($this->exam->refresh()->usesAddon(Addon::ItemAnalysis))->toBeTrue()
        ->and($switchedOff->refresh()->usesAddon(Addon::ItemAnalysis))->toBeFalse();
});

test('an admin installs an add-on for every teacher, which only an admin can remove', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($this->teacher)->postJson(route('addons.install', 'item-analysis'), ['everyone' => true])->assertForbidden();

    $this->actingAs($admin)->postJson(route('addons.install', 'item-analysis'), ['everyone' => true])
        ->assertOk()
        ->assertJsonPath('addons.0.installedForEveryone', true);
    expect(InstalledAddon::query()->sole())->user_id->toBeNull()->installed_by->toBe($admin->id);

    app()->forgetScopedInstances();
    expect($this->exam->refresh()->usesAddon(Addon::ItemAnalysis))->toBeTrue();

    $this->actingAs($this->teacher)->get(route('addons.index'))
        ->assertInertia(fn (Assert $page) => $page->where('addons.0.installedForEveryone', true)->where('addons.0.canUninstall', false));
    $this->actingAs($this->teacher)->deleteJson(route('addons.uninstall', 'item-analysis'), ['everyone' => true])->assertForbidden();
    $this->actingAs($admin)->deleteJson(route('addons.uninstall', 'item-analysis').'?everyone=1')->assertOk();
    expect(InstalledAddon::query()->count())->toBe(0);
});

test('collaborators see the add-ons of the exam\'s owner', function () {
    $collaborator = User::factory()->create(['role' => 'guru']);
    $this->exam->collaborators()->attach($collaborator->id);
    app(Addons::class)->install(Addon::ItemAnalysis, $this->teacher);

    $this->actingAs($collaborator)->get(route('forms.edit', $this->exam))
        ->assertInertia(fn (Assert $page) => $page
            ->where('quizForm.responses.analysis.respondents', 1)
            ->where('quizForm.addons.0.installed', false)
            ->where('quizForm.addons.0.installedForOwner', true));
    expect(workbookSheets($this->exam, $collaborator))->toContain('Analisis Butir Soal');
});

test('only known add-ons can be switched per exam, and students have no gallery', function () {
    $this->actingAs($this->teacher)->patch(route('forms.update', $this->exam), [
        'title' => $this->exam->title,
        'slug' => $this->exam->slug,
        'questions' => $this->exam->questions,
        'settings' => [...$this->exam->settings, 'addons' => ['tidak-ada' => true]],
    ])->assertSessionHasErrors('settings.addons');

    $this->actingAs($this->teacher)->postJson(route('addons.install', 'tidak-ada'))->assertNotFound();
    $this->actingAs(User::factory()->create(['role' => 'siswa']))->get(route('addons.index'))->assertForbidden();
});

test('removing an add-on an admin installed for everyone says it stays on', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    app(Addons::class)->install(Addon::ItemAnalysis, $admin, forEveryone: true);
    app(Addons::class)->install(Addon::ItemAnalysis, $this->teacher);
    app()->forgetScopedInstances();

    // The teacher's own installation goes; the one for everyone stays.
    $this->actingAs($this->teacher)->deleteJson(route('addons.uninstall', 'item-analysis'))
        ->assertOk()
        ->assertJsonPath('message', 'Analisis Butir Soal tetap aktif karena dipasang admin untuk semua guru.')
        ->assertJsonPath('addons.0.installed', true);
    expect(InstalledAddon::query()->whereNull('user_id')->count())->toBe(1)
        ->and(InstalledAddon::query()->where('user_id', $this->teacher->id)->count())->toBe(0);

    // The admin's own installation stays when theirs for everyone goes.
    app(Addons::class)->install(Addon::ItemAnalysis, $admin);
    app()->forgetScopedInstances();
    $this->actingAs($admin)->deleteJson(route('addons.uninstall', 'item-analysis').'?everyone=1')
        ->assertOk()
        ->assertJsonPath('message', 'Analisis Butir Soal dilepas dari semua guru, tetapi tetap terpasang untuk Anda sendiri.');

    app()->forgetScopedInstances();
    $this->actingAs($this->teacher)->deleteJson(route('addons.uninstall', 'item-analysis'))
        ->assertOk()
        ->assertJsonPath('message', 'Analisis Butir Soal dilepas.')
        ->assertJsonPath('addons.0.installed', false);
});
