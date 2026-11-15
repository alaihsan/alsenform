<?php

use App\Models\QuizForm;
use App\Models\User;
use Illuminate\Support\Facades\File;

/*
 * Moving questions in the editor, like in Google Forms: hold the ⋮ of a card (or the dots on top
 * of it) and drag it where the blue line shows, with a mouse or a finger; the arrow keys on them
 * move it one place (resources/js/composables/useCardReorder.ts). The editor then saves the
 * questions in their new order.
 */
beforeEach(function () {
    $this->teacher = User::factory()->create(['role' => 'guru']);
    $this->exam = QuizForm::factory()->for($this->teacher)->create([
        'settings' => ['isQuiz' => true, 'shuffleQuestions' => false],
        'questions' => [
            ['id' => 1, 'title' => 'Jurusan', 'type' => 'Multiple choice', 'options' => ['IPA', 'IPS', 'Belum tahu'], 'points' => 0, 'required' => true,
                'branching' => true, 'optionGoTo' => [10, 20, 'submit']],
            ['id' => 2, 'title' => 'Nama panggilan', 'type' => 'Short answer', 'points' => 0, 'required' => false],
            ['id' => 10, 'title' => 'Soal IPA', 'description' => '', 'type' => 'Section', 'points' => 0, 'required' => false, 'goTo' => 'submit'],
            ['id' => 11, 'title' => 'Satuan gaya?', 'type' => 'Multiple choice', 'options' => ['Newton', 'Joule'], 'answer' => 'Newton', 'points' => 2, 'required' => true],
            ['id' => 20, 'title' => 'Soal IPS', 'description' => '', 'type' => 'Section', 'points' => 0, 'required' => false],
            ['id' => 21, 'title' => 'Ibu kota Jawa Barat?', 'type' => 'Short answer', 'answer' => 'Bandung', 'points' => 3, 'required' => true],
        ],
    ]);
});

test('questions moved in the editor are saved in their new order, the jumps between sections kept', function () {
    $byId = collect($this->exam->questions)->keyBy('id');
    // The IPS section (with its question) above the IPA one, and "Nama panggilan" into it.
    $moved = collect([1, 20, 21, 2, 10, 11])->map(fn (int $id): array => $byId[$id])->all();

    $this->actingAs($this->teacher)->patch(route('forms.update', $this->exam), [
        'title' => $this->exam->title,
        'slug' => $this->exam->slug,
        'questions' => $moved,
        'settings' => $this->exam->settings,
        'published' => true,
    ])->assertSessionHasNoErrors();

    $saved = collect($this->exam->fresh()->questions)->keyBy('id');
    expect($saved->keys()->all())->toBe([1, 20, 21, 2, 10, 11])
        ->and($saved[1]['optionGoTo'])->toBe([10, 20, 'submit'])
        ->and($saved[10]['goTo'])->toBe('submit');

    $this->get(route('forms.edit', $this->exam))
        ->assertInertia(fn ($page) => $page->where('quizForm.questions', fn ($questions) => collect($questions)->pluck('id')->all() === [1, 20, 21, 2, 10, 11]));
});

test('every card is moved by holding its ⋮ or the dots on top of it, or by the arrow keys on them', function () {
    $editor = File::get(resource_path('js/pages/FormEditor.vue'));
    $reorder = File::get(resource_path('js/composables/useCardReorder.ts'));

    // Questions and sections or titles: the dots on top and the ⋮ at the bottom of each card.
    expect(substr_count($editor, '@pointerdown="startReorder(itemIndex, $event)"'))->toBe(4)
        ->and(substr_count($editor, '@keydown="reorderByKey(itemIndex, $event)"'))->toBe(4)
        ->and(substr_count($editor, 'touch-none'))->toBeGreaterThanOrEqual(4)
        ->and($editor)->toContain('data-reorder-card', 'data-testid="reorder-line"', 'data-testid="reorder-ghost"')
        // HTML drag and drop, which touch screens do not have, is gone.
        ->not->toContain(':draggable=', '@dragstart', '@drop=');

    expect($reorder)->toContain('export function dropIndex', "addEventListener('pointermove'", "event.key === 'Escape'", 'window.scrollBy(', 'HOLD_MS');
});
