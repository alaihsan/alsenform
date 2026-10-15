<?php

use App\Models\QuizForm;
use App\Models\QuizResponse;
use App\Models\User;
use App\Services\Addons;
use App\Support\Addon;
use App\Support\FormDesign;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->teacher = User::factory()->create(['role' => 'guru']);
    $this->exam = QuizForm::factory()->for($this->teacher)->create([
        'published_at' => now(),
        'questions' => [['id' => 1, 'title' => '2 + 2 = ?', 'type' => 'Multiple choice', 'options' => ['3', '4'], 'answer' => '4', 'points' => 1]],
        'settings' => [
            'collectEmail' => false,
            'showResultsSummary' => true,
            'design' => ['preset' => 'malam', 'accent' => '#22D3EE', 'background' => '#0b1120', 'card' => '#111827', 'text' => '#e5e7eb', 'headerImage' => '/storage/media/header.webp', 'radius' => 'xl'],
        ],
    ]);
});

function saveDesign(QuizForm $exam, User $teacher, array $design): void
{
    test()->actingAs($teacher)->patch(route('forms.update', $exam), [
        'title' => $exam->title,
        'slug' => $exam->slug,
        'questions' => $exam->questions,
        'settings' => [...$exam->settings, 'design' => $design],
    ])->assertSessionHasNoErrors();
}

test('a design keeps known keys with safe values only', function () {
    Storage::fake('public');
    Storage::disk('public')->put('media/latar.webp', 'image');

    $design = app(FormDesign::class)->normalize([
        'preset' => 'Malam <b>',
        'accent' => '#22D3EE',
        'background' => 'red; background: url(http://evil.example/x)',
        'card' => '#12345',
        'headerImage' => 'https://evil.example/header.png',
        'logo' => '/storage/media/../../.env',
        'backgroundImage' => 'http://localhost/storage/media/latar.webp',
        'titleFont' => "'Playfair Display', serif",
        'bodyFont' => 'Inter; } body { display: none',
        'radius' => 'huge',
        'shadow' => 'strong',
        'backgroundDim' => 200,
        'cardOpacity' => 10,
        'cardBorder' => 'yes',
        'onclick' => 'alert(1)',
    ]);

    expect($design)
        ->toHaveKeys(array_keys(FormDesign::DEFAULTS))
        ->not->toHaveKey('onclick')
        ->and($design['preset'])->toBeNull()
        ->and($design['accent'])->toBe('#22d3ee')
        ->and($design['background'])->toBe(FormDesign::DEFAULTS['background'])
        ->and($design['card'])->toBe(FormDesign::DEFAULTS['card'])
        ->and($design['headerImage'])->toBeNull()
        ->and($design['logo'])->toBeNull()
        ->and($design['backgroundImage'])->toBe('/storage/media/latar.webp')
        ->and($design['titleFont'])->toBe("'Playfair Display', serif")
        ->and($design['bodyFont'])->toBe(FormDesign::DEFAULTS['bodyFont'])
        ->and($design['radius'])->toBe('lg')
        ->and($design['shadow'])->toBe('strong')
        ->and($design['backgroundDim'])->toBe(80)
        ->and($design['cardOpacity'])->toBe(50)
        ->and($design['cardBorder'])->toBeTrue();
});

test('custom CSS cannot load anything from elsewhere or run code', function () {
    $css = app(FormDesign::class)->sanitizeCss(<<<'CSS'
        @import url("https://evil.example/a.css");
        @import 'https://evil.example/b.css';
        .af-card { background: url(https://evil.example/track.png) }
        .af-header { background-image: url('/storage/media/kop.webp') }
        .af-logo { background: url(data:image/png;base64,iVBORw0KGgo=) }
        .af-title { background: image-set("https://evil.example/x.png" 1x) }
        .af-option { width: expression(alert(1)); -moz-binding: url(x.xml#xss) }
        .af-button { background: u\72l(https://evil.example/escaped.png) }
        .af-page { color: red } </style><script>alert(1)</script>
        .af-nav { background: url(https://evil.example/unclosed.png
        CSS);

    expect($css)
        ->not->toContain('@import')
        ->not->toContain('evil.example/a.css')
        ->not->toContain('url(https://evil.example/track.png)')
        ->toContain('url("/storage/media/kop.webp")')
        ->toContain('url("data:image/png;base64,iVBORw0KGgo=")')
        ->not->toContain('image-set(')
        ->not->toContain('expression(')
        ->not->toContain('-moz-binding')
        ->not->toContain('\\')
        ->not->toContain('<')
        ->not->toMatch('/url\(\s*[\'"]?https?:/i')
        ->toContain('.af-page { color: red }');
});

test('saving the exam stores a normalised design', function () {
    saveDesign($this->exam, $this->teacher, [
        'accent' => '#ABCDEF',
        'width' => 'wide',
        'customCss' => '@import "https://evil.example/x.css"; .af-card { border-width: 3px }',
        'secret' => 'dropped',
    ]);

    $design = $this->exam->refresh()->settings['design'];

    expect($design)
        ->toHaveKeys(array_keys(FormDesign::DEFAULTS))
        ->not->toHaveKey('secret')
        ->and($design['accent'])->toBe('#abcdef')
        ->and($design['width'])->toBe('wide')
        ->and($design['customCss'])->toBe('.af-card { border-width: 3px }');
});

test('custom CSS longer than the limit is refused', function () {
    $this->actingAs($this->teacher)->patch(route('forms.update', $this->exam), [
        'title' => $this->exam->title,
        'slug' => $this->exam->slug,
        'questions' => $this->exam->questions,
        'settings' => [...$this->exam->settings, 'design' => ['customCss' => str_repeat('a', FormDesign::MAX_CSS_LENGTH + 1)]],
    ])->assertSessionHasErrors('settings.design.customCss');
});

test('students see the design only while the add-on is on for the exam', function () {
    $student = User::factory()->create(['role' => 'siswa']);

    // Not installed: the exam looks as usual, everywhere.
    $this->actingAs($student)->get(route('forms.public', $this->exam))
        ->assertInertia(fn (Assert $page) => $page->where('quizForm.design', null));
    $this->actingAs($student)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('recentForms.0.design', null));

    app(Addons::class)->install(Addon::FormDesigner, $this->teacher);
    app()->forgetScopedInstances();

    $this->actingAs($student)->get(route('forms.public', $this->exam))
        ->assertInertia(fn (Assert $page) => $page
            ->where('quizForm.design.accent', '#22d3ee')
            ->where('quizForm.design.card', '#111827')
            ->where('quizForm.design.headerImage', '/storage/media/header.webp')
            ->where('quizForm.design.radius', 'xl')
            ->where('quizForm.design.width', 'normal'));
    $this->actingAs($student)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('recentForms.0.design.accent', '#22d3ee')
            ->where('recentForms.0.design.headerImage', '/storage/media/header.webp'));

    QuizResponse::query()->create(['quiz_form_id' => $this->exam->id, 'user_id' => $student->id, 'respondent_identifier' => 'user_'.$student->id, 'answers' => [1 => '4'], 'score' => 1]);
    $this->actingAs($student)->get(route('forms.results', $this->exam))
        ->assertInertia(fn (Assert $page) => $page->where('quiz.design.background', '#0b1120'));

    // Switched off for this exam: the usual look again, with the design kept for later.
    $this->exam->update(['settings' => [...$this->exam->settings, 'addons' => ['form-designer' => false]]]);
    app()->forgetScopedInstances();

    $this->actingAs($student)->get(route('forms.public', $this->exam))
        ->assertInertia(fn (Assert $page) => $page->where('quizForm.design', null));
    expect($this->exam->refresh()->settings['design']['accent'])->toBe('#22D3EE');
});

test('the teacher\'s dashboard card shows the design', function () {
    app(Addons::class)->install(Addon::FormDesigner, $this->teacher);

    $this->actingAs($this->teacher)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('recentForms.0.design.accent', '#22d3ee')
            ->where('recentForms.0.design.card', '#111827'));

    $this->actingAs($this->teacher)->get(route('forms.edit', $this->exam))
        ->assertInertia(fn (Assert $page) => $page
            ->where('quizForm.settings.design.preset', 'malam')
            ->where('quizForm.addons.4.key', 'form-designer')
            ->where('quizForm.addons.4.activeOnExam', true));
});

test('an exam without a design looks as usual even with the add-on on', function () {
    app(Addons::class)->install(Addon::FormDesigner, $this->teacher);
    $plain = QuizForm::factory()->for($this->teacher)->create(['published_at' => now(), 'settings' => ['collectEmail' => false]]);

    expect($plain->design())->toBeNull()->and($plain->cardDesign())->toBeNull();
});
