<?php

use App\Models\QuizForm;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The form cards of the Beranda: the whole title, the status (Terbit or Draf) next to the ⋮ under it,
 * and the rest (number of questions, "Hanya tautan", the public link) behind the i in the top right
 * corner of the card. The ⋮ menus open over the page and scroll when the screen is short.
 */

test('the Beranda gives each card its whole title and what its i shows', function () {
    $teacher = User::factory()->create(['role' => 'guru']);
    $title = 'SELEKSI PMB SMP ISLAM AL AZHAR 22 - TES WAWANCARA BAHASA INGGRIS DAN PRAKTIK AGAMA GELOMBANG KEDUA';
    $exam = QuizForm::factory()->for($teacher)->create([
        'title' => $title,
        'published_at' => now(),
        'settings' => ['isQuiz' => true, 'listedForStudents' => false],
        'questions' => [
            ['id' => 1, 'title' => 'Satuan gaya?', 'type' => 'Multiple choice', 'options' => ['Newton', 'Joule'], 'points' => 1, 'required' => true],
            ['id' => 2, 'title' => 'Ibu kota Jawa Barat?', 'type' => 'Short answer', 'points' => 1, 'required' => true],
            ['id' => 3, 'title' => 'Ceritakan dirimu', 'type' => 'Paragraph', 'points' => 0, 'required' => false],
        ],
    ]);

    $this->actingAs($teacher)->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('recentForms.0.title', $title)
            ->where('recentForms.0.questionsCount', 3)
            ->where('recentForms.0.linkOnly', true)
            ->where('recentForms.0.isPublished', true)
            ->where('recentForms.0.publicUrl', route('forms.public', $exam->slug)));
});

test('a card shows its whole title, its status by the ⋮, and the other details behind its i', function () {
    $dashboard = File::get(resource_path('js/pages/Dashboard.vue'));
    $card = str($dashboard)->after('data-testid="form-card"')->before('</article>')->toString();
    $info = str($card)->after('data-testid="form-info"')->before('</PopoverContent>')->toString();

    // The title is never cut, and nothing sits beside it.
    expect(substr_count($card, 'data-testid="form-title"'))->toBe(3)
        ->and($card)->not->toContain('line-clamp', 'link-only-badge', '{{ form.questionsCount }} Soal')
        // The i in the top right corner, with the number of questions, "Hanya tautan" and the public link.
        // (named by hidden text: the aria-label tooltips of the page would pin it with position: relative).
        ->and($card)->toContain('data-testid="form-info-button"', 'absolute right-2 top-2', '<span class="sr-only">Informasi formulir</span>')
        ->and(str($card)->after('<PopoverTrigger')->before('</PopoverTrigger>')->toString())->not->toContain('aria-label="')
        ->and($info)->toContain('Jumlah soal', 'form.questionsCount', 'Hanya tautan', 'form.linkOnly', 'Tautan publik', 'form.publicUrl', 'copyPublicUrl(form)')
        // Terbit or Draf stays on the card, under the title, next to the ⋮.
        ->and(strpos($card, 'data-testid="form-status"'))->toBeGreaterThan(strpos($card, 'data-testid="form-title"'))
        ->and(strpos($card, 'data-testid="form-menu-button"'))->toBeGreaterThan(strpos($card, 'data-testid="form-status"'))
        // Wider cards: four in a row on a large screen instead of five.
        ->and($dashboard)->toContain("'grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4'");
});

test('the ⋮ menus of forms and folders open over the page, and scroll when the screen is short', function () {
    $dashboard = File::get(resource_path('js/pages/Dashboard.vue'));

    // Both menus are DropdownMenus (drawn over the page, turned upwards near the bottom of the screen),
    // which leave the page scrolling and scroll themselves when they are taller than the room they have.
    expect(substr_count($dashboard, ':modal="false"'))->toBe(2)
        ->and(substr_count($dashboard, 'max-h-[var(--radix-dropdown-menu-content-available-height)]'))->toBe(2)
        // A menu still closing (after Escape, or a click on the next ⋮) clears only itself: clearing
        // the shared one shut the menu just opened, so it took a second click.
        ->and($dashboard)->toContain(
            '@update:open="(isOpen: boolean) => setFormMenuOpen(form.id, isOpen)"',
            '@update:open="(isOpen: boolean) => setFormInfoOpen(form.id, isOpen)"',
            '@update:open="(isOpen: boolean) => setFolderMenuOpen(folder.id, isOpen)"',
            '} else if (openId.value === id) {',
        )
        // No more menus drawn inside a card, where the cards after it could cover them.
        ->not->toContain('v-if="activeMenuId === form.id"', 'v-if="activeFolderMenuId === folder.id"');
});

test('the exam start confirmation scrolls inside a wider box that keeps a space above and below it', function () {
    $quiz = File::get(resource_path('js/pages/PublicQuiz.vue'));
    $overlay = str($quiz)->after('v-if="isStartModalOpen"')->before('data-testid="exam-start-overlay"')->toString();
    $modal = str($quiz)->after('data-testid="exam-start-overlay"')->before('</section>')->toString();
    $body = str($modal)->after('data-testid="exam-start-body"')->before('data-testid="exam-start-actions"')->toString();
    $actions = str($modal)->after('data-testid="exam-start-actions"')->toString();

    expect($overlay)->toContain('fixed inset-0', 'items-center', 'py-4', 'sm:py-10')
        ->not->toContain('overflow-y-auto')
        ->and($modal)->toContain('flex max-h-full w-full max-w-3xl flex-col overflow-hidden')
        ->and($quiz)->toContain('<div class="min-h-0 flex-1 overflow-y-auto overscroll-contain" data-testid="exam-start-body">')
        ->and($body)->toContain('Aturan Mengerjakan', 'id="exam-token"')
        // The buttons and the error of starting stay in view under the content that scrolls.
        ->and($actions)->toContain('startExamError', 'Kerjakan Sekarang', 'Kembali');
});
