<?php

use App\Models\QuizForm;
use App\Models\User;
use App\Support\MediaUrl;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @param  list<array<string, mixed>>  $media
 * @return array<string, mixed>
 */
function questionWithMedia(array $media): array
{
    return [
        'id' => 1,
        'title' => 'Perhatikan gambar berikut',
        'description' => '',
        'type' => 'Multiple choice',
        'options' => ['A', 'B'],
        'answer' => '',
        'required' => false,
        'media' => $media,
    ];
}

test('uploaded media is referenced with a host independent url', function () {
    Storage::fake('public');
    $teacher = User::factory()->create();

    $response = $this->actingAs($teacher)
        ->postJson(route('forms.media.upload'), [
            'file' => UploadedFile::fake()->create('soal.mp4', 100, 'video/mp4'),
        ])
        ->assertOk();

    $url = $response->json('url');

    expect($url)->toStartWith('/storage/media/')
        ->and(Storage::disk('public')->exists(substr($url, strlen('/storage/'))))->toBeTrue();
});

test('legacy absolute urls to our own storage keep working after the ip changes', function () {
    Storage::fake('public');
    Storage::disk('public')->put('media/gambar-soal.webp', 'image-bytes');

    $quizForm = QuizForm::factory()->create([
        'published_at' => now(),
        'questions' => [questionWithMedia([
            ['type' => 'image', 'url' => 'http://192.168.1.23:8000/storage/media/gambar-soal.webp'],
            ['type' => 'image', 'url' => 'https://upload.wikimedia.org/storage/media/other-site.webp'],
            ['type' => 'video', 'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
        ])],
    ]);

    $this->get(route('forms.public', $quizForm->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('PublicQuiz')
            ->where('quizForm.questions.0.media.0.url', '/storage/media/gambar-soal.webp')
            ->where('quizForm.questions.0.media.1.url', 'https://upload.wikimedia.org/storage/media/other-site.webp')
            ->where('quizForm.questions.0.media.2.url', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'));
});

test('saving a form stores host independent media urls', function () {
    Storage::fake('public');
    Storage::disk('public')->put('media/examview/diagram.png', 'image-bytes');

    $teacher = User::factory()->create();
    $quizForm = QuizForm::factory()->create(['user_id' => $teacher->id]);

    $this->actingAs($teacher)
        ->patch(route('forms.update', $quizForm), [
            'title' => 'Ujian IPA',
            'description' => '',
            'slug' => $quizForm->slug,
            'questions' => [questionWithMedia([
                ['type' => 'image', 'url' => 'http://alsenform.local:8000/storage/media/examview/diagram.png'],
            ])],
            'settings' => ['collectEmail' => false, 'showProgress' => true, 'shuffleQuestions' => false],
        ])
        ->assertRedirect();

    expect($quizForm->refresh()->questions[0]['media'][0]['url'])->toBe('/storage/media/examview/diagram.png');
});

test('media url normalization leaves unknown and unsafe urls untouched', function () {
    Storage::fake('public');
    $mediaUrl = new MediaUrl;

    expect($mediaUrl->normalize('/storage/media/a.png'))->toBe('/storage/media/a.png')
        ->and($mediaUrl->normalize('http://10.0.0.5/storage/media/missing.png'))->toBe('http://10.0.0.5/storage/media/missing.png')
        ->and($mediaUrl->normalize('http://10.0.0.5/storage/../.env'))->toBe('http://10.0.0.5/storage/../.env')
        ->and($mediaUrl->normalize(null))->toBeNull()
        ->and($mediaUrl->forPublicPath('media/a.png'))->toBe('/storage/media/a.png');
});
