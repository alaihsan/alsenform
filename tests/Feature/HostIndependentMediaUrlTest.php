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

    $sessionToken = $this->get(route('forms.public', $quizForm->slug))
        ->assertOk()
        ->inertiaProps('session.token');

    $this->withCredentials()
        ->postJson(route('forms.responses.start', $quizForm->slug), ['session_token' => $sessionToken])
        ->assertOk()
        ->assertJsonPath('questions.0.media.0.url', '/storage/media/gambar-soal.webp')
        ->assertJsonPath('questions.0.media.1.url', 'https://upload.wikimedia.org/storage/media/other-site.webp')
        ->assertJsonPath('questions.0.media.2.url', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ');
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

test('external images keep their url even when a local file has the same path', function () {
    Storage::fake('public');
    Storage::disk('public')->put('media/diagram.png', 'local-image-bytes');
    $mediaUrl = new MediaUrl;

    expect($mediaUrl->normalize('https://example.org/storage/media/diagram.png'))->toBe('https://example.org/storage/media/diagram.png')
        ->and($mediaUrl->normalize('http://8.8.8.8/storage/media/diagram.png'))->toBe('http://8.8.8.8/storage/media/diagram.png')
        ->and($mediaUrl->normalize('http://172.16.5.10:8000/storage/media/diagram.png'))->toBe('/storage/media/diagram.png')
        ->and($mediaUrl->normalize('http://macmini-lab.local:8000/storage/media/diagram.png'))->toBe('/storage/media/diagram.png')
        ->and($mediaUrl->normalize('https://ujian-sekolah.sharedwithexpose.com/storage/media/diagram.png'))->toBe('/storage/media/diagram.png');
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

test('profile photos use a host independent url', function () {
    $uploaded = User::factory()->make(['avatar' => 'avatars/guru.jpg']);
    $external = User::factory()->make(['avatar' => 'https://example.org/photo.jpg']);
    $withoutPhoto = User::factory()->make(['avatar' => null]);

    expect($uploaded->avatar_url)->toBe('/storage/avatars/guru.jpg')
        ->and($external->avatar_url)->toBe('https://example.org/photo.jpg')
        ->and($withoutPhoto->avatar_url)->toBeNull();
});

test('the form editor receives the profile photo for the account menu', function () {
    $teacher = User::factory()->create(['avatar' => 'avatars/guru.jpg']);
    $quizForm = QuizForm::factory()->for($teacher)->create();

    $this->actingAs($teacher)
        ->get(route('forms.edit', ['quizForm' => $quizForm->slug]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('FormEditor')
            ->where('auth.user.name', $teacher->name)
            ->where('auth.user.avatar_url', '/storage/avatars/guru.jpg')
        );
});
