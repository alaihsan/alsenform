<?php

use App\Models\AnswerFile;
use App\Models\QuizForm;
use App\Models\QuizResponse;
use App\Models\User;
use App\Services\AudioCompressor;
use App\Support\ExamLayout;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * A WAV of a tone: uncompressed, as a phone or a sound recorder hands it in.
 */
function toneWav(float $seconds, int $rate = 44100, int $channels = 2): string
{
    $samples = '';
    $count = (int) ($seconds * $rate);
    for ($i = 0; $i < $count; $i++) {
        $value = (int) (sin(2 * M_PI * 440 * $i / $rate) * 12000) & 0xFFFF;
        $samples .= str_repeat(pack('v', $value), $channels);
    }

    return 'RIFF'.pack('V', 36 + strlen($samples)).'WAVE'
        .'fmt '.pack('VvvVVvv', 16, 1, $channels, $rate, $rate * $channels * 2, $channels * 2, 16)
        .'data'.pack('V', strlen($samples)).$samples;
}

function ffmpegIsInstalled(): bool
{
    return app(AudioCompressor::class)->isAvailable();
}

beforeEach(function () {
    $this->teacher = User::factory()->create(['role' => 'guru']);
});

test('a recording of the teacher is compressed to a light mp3 with its length', function () {
    Storage::fake('public');
    $wav = toneWav(2);

    $uploaded = $this->actingAs($this->teacher)
        ->postJson(route('forms.media.audio'), ['file' => UploadedFile::fake()->createWithContent('percakapan.wav', $wav)])
        ->assertOk()
        ->json();

    expect($uploaded['url'])->toStartWith('/storage/media/audio/')->toEndWith('.mp3')
        ->and($uploaded['compressed'])->toBeTrue()
        ->and($uploaded['originalSize'])->toBe(strlen($wav))
        // 2 seconds of stereo WAV (350 KB) become a mono MP3 of about 16 KB.
        ->and($uploaded['size'])->toBeLessThan(intdiv(strlen($wav), 10))
        ->and($uploaded['duration'])->toEqualWithDelta(2.0, 0.15);

    Storage::disk('public')->assertExists(substr($uploaded['url'], strlen('/storage/')));
})->skip(fn () => ! ffmpegIsInstalled(), 'ffmpeg is not installed');

test('an mp3 that is already light is kept as it is', function () {
    Storage::fake('public');
    $light = UploadedFile::fake()->createWithContent('kecil.wav', toneWav(1, 22050, 1));

    // First made light by the server, then sent again: it is not compressed twice.
    $first = $this->actingAs($this->teacher)->postJson(route('forms.media.audio'), ['file' => $light])->assertOk()->json();
    $mp3 = Storage::disk('public')->get(substr($first['url'], strlen('/storage/')));

    $again = $this->actingAs($this->teacher)
        ->postJson(route('forms.media.audio'), ['file' => UploadedFile::fake()->createWithContent('lagi.mp3', $mp3)])
        ->assertOk()
        ->json();

    expect($again['compressed'])->toBeFalse()
        ->and($again['size'])->toBe(strlen($mp3))
        ->and($again['url'])->toEndWith('.mp3');
})->skip(fn () => ! ffmpegIsInstalled(), 'ffmpeg is not installed');

test('without ffmpeg a sound is kept as sent', function () {
    Storage::fake('public');
    config(['services.ffmpeg.enabled' => false]);
    $wav = toneWav(0.5);

    $uploaded = $this->actingAs($this->teacher)
        ->postJson(route('forms.media.audio'), ['file' => UploadedFile::fake()->createWithContent('rekaman.wav', $wav)])
        ->assertOk()
        ->json();

    expect($uploaded)->toMatchArray(['compressed' => false, 'size' => strlen($wav), 'duration' => null])
        ->and($uploaded['url'])->toEndWith('.wav');
});

test('only sound files from teachers are taken', function () {
    Storage::fake('public');

    $this->actingAs($this->teacher)
        ->postJson(route('forms.media.audio'), ['file' => UploadedFile::fake()->create('soal.pdf', 10, 'application/pdf')])
        ->assertUnprocessable()
        ->assertJsonPath('errors.file.0', 'Jenis berkas tidak didukung. Kirim MP3, M4A, WAV, OGG, WebM atau rekaman HP.');

    $this->actingAs(User::factory()->create(['role' => 'siswa']))
        ->postJson(route('forms.media.audio'), ['file' => UploadedFile::fake()->createWithContent('a.wav', toneWav(0.2))])
        ->assertForbidden();
});

test('the editor keeps the sound of each choice, the play limit and the longest spoken answer', function () {
    $exam = QuizForm::factory()->for($this->teacher)->create();
    $questions = [
        [
            'id' => 1, 'title' => 'Listen and choose', 'type' => 'Multiple choice', 'options' => ['A', 'B', 'C'], 'answer' => 1, 'points' => 1,
            'media' => [['type' => 'audio', 'url' => '/storage/media/audio/soal.mp3', 'duration' => 12.5, 'maxPlays' => 2, 'caption' => 'Percakapan 1']],
            'optionAudio' => [['url' => '/storage/media/audio/a.mp3', 'duration' => 1.2], null, ['url' => '/storage/media/audio/c.mp3', 'duration' => 1.4]],
        ],
        ['id' => 2, 'title' => 'Introduce yourself', 'type' => 'Voice answer', 'options' => [], 'points' => 4, 'media' => [], 'maxDuration' => 45],
        // A drop-down list cannot play a sound: its choices keep none.
        ['id' => 3, 'title' => 'Pilih', 'type' => 'Drop-down', 'options' => ['X', 'Y'], 'points' => 1, 'media' => [], 'optionAudio' => [['url' => '/x.mp3'], null]],
    ];

    $this->actingAs($this->teacher)->patch(route('forms.update', $exam), [
        'title' => $exam->title, 'slug' => $exam->slug, 'questions' => $questions, 'settings' => ['isQuiz' => true],
    ])->assertSessionHasNoErrors();

    $saved = $exam->refresh()->questions;
    expect($saved[0]['media'][0])->toMatchArray(['type' => 'audio', 'maxPlays' => 2, 'duration' => 12.5])
        ->and($saved[0]['optionAudio'])->toBe($questions[0]['optionAudio'])
        ->and($saved[1]['maxDuration'])->toBe(45)
        ->and($saved[2])->not->toHaveKey('optionAudio');

    $questions[1]['maxDuration'] = 3600;
    $this->actingAs($this->teacher)->patch(route('forms.update', $exam), [
        'title' => $exam->title, 'slug' => $exam->slug, 'questions' => $questions, 'settings' => ['isQuiz' => true],
    ])->assertSessionHasErrors('questions.1.maxDuration');
});

test('shuffled choices keep their own sound', function () {
    $exam = QuizForm::factory()->for($this->teacher)->create([
        'questions' => [[
            'id' => 1, 'title' => 'Listen', 'type' => 'Multiple choice', 'options' => ['A', 'B', 'C'],
            'optionAudio' => [['url' => '/a.mp3'], null, ['url' => '/c.mp3']],
        ]],
    ]);

    $arranged = app(ExamLayout::class)->arrange($exam, ['questions' => [1], 'options' => ['1' => [2, 0, 1]], 'draw' => false]);

    expect($arranged[0]['options'])->toBe(['C', 'A', 'B'])
        ->and($arranged[0]['optionAudio'])->toBe([['url' => '/c.mp3'], ['url' => '/a.mp3'], null]);
});

describe('spoken answers', function () {
    beforeEach(function () {
        Storage::fake('local');
        $this->exam = QuizForm::factory()->for($this->teacher)->create([
            'published_at' => now(),
            'settings' => ['isQuiz' => true],
            'questions' => [
                ['id' => 1, 'title' => 'Describe your school', 'type' => 'Voice answer', 'points' => 4, 'maxDuration' => 30],
                ['id' => 2, 'title' => 'Foto', 'type' => 'File upload', 'points' => 1],
            ],
        ]);
        $this->student = User::factory()->create(['role' => 'siswa', 'name' => 'Rani']);
        $this->actingAs($this->student);
        $this->token = $this->get(route('forms.public', $this->exam->slug))->inertiaProps('session.token');
        $this->postJson(route('forms.responses.start', $this->exam->slug), ['session_token' => $this->token])->assertOk();
        $this->speak = fn (UploadedFile $file, int $question = 1) => $this->postJson(route('forms.answer-files.store', $this->exam->slug), [
            'session_token' => $this->token, 'question_id' => (string) $question, 'file' => $file,
        ]);
    });

    test('a student records an answer; it is compressed, handed in, and the teacher listens to it', function () {
        $recording = ($this->speak)(UploadedFile::fake()->createWithContent('rekaman.wav', toneWav(2)))->assertCreated()->json('file');

        expect($recording)->toMatchArray(['name' => 'rekaman.mp3', 'type' => 'audio/mpeg'])
            ->and($recording['duration'])->toEqualWithDelta(2.0, 0.15);

        $this->postJson(route('forms.responses.store', $this->exam->slug), [
            'session_token' => $this->token,
            // A made-up second file is dropped: a spoken answer is one recording.
            'answers' => [1 => [$recording, ['id' => '9b2b9a3e-0000-4000-8000-000000000000']]],
        ])->assertSuccessful();

        expect(QuizResponse::query()->latest('id')->first()->answers[1])->toBe([$recording]);

        $stored = AnswerFile::query()->findOrFail($recording['id']);
        $this->actingAs($this->teacher)
            ->getJson(route('forms.grading.index', [$this->exam, 'question' => 1]))
            ->assertJsonPath('questions.0.waiting', 1)
            ->assertJsonPath('answers.0.files.0.isAudio', true)
            ->assertJsonPath('answers.0.files.0.duration', $recording['duration']);

        // Played in the page, with byte ranges (Safari needs them, and seeking uses them).
        $this->actingAs($this->teacher)->get(route('forms.answer-files.show', [$this->exam, $stored]))
            ->assertOk()
            ->assertHeader('Content-Type', 'audio/mpeg')
            ->assertHeader('Accept-Ranges', 'bytes');
        $this->actingAs($this->teacher)->get(route('forms.answer-files.show', [$this->exam, $stored]), ['Range' => 'bytes=0-99'])
            ->assertStatus(206);
        $this->actingAs($this->student)->get(route('forms.answer-files.show', [$this->exam, $stored]))->assertForbidden();
    })->skip(fn () => ! ffmpegIsInstalled(), 'ffmpeg is not installed');

    test('a recording longer than the question allows is refused', function () {
        ($this->speak)(UploadedFile::fake()->createWithContent('panjang.wav', toneWav(34, 8000, 1)))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Rekaman terlalu panjang: paling lama 30 detik.');
    })->skip(fn () => ! ffmpegIsInstalled(), 'ffmpeg is not installed');

    test('only sound is taken for a spoken answer, and no sound for a file question', function () {
        ($this->speak)(UploadedFile::fake()->image('foto.jpg'))
            ->assertUnprocessable()
            ->assertJsonPath('errors.file.0', 'Jenis berkas tidak didukung. Kirim rekaman suara (MP3, M4A, WAV, OGG, WebM atau rekaman HP).');

        ($this->speak)(UploadedFile::fake()->createWithContent('suara.wav', toneWav(0.2)), 2)
            ->assertUnprocessable()
            ->assertJsonPath('errors.file.0', 'Jenis berkas tidak didukung. Kirim foto, PDF, Word, Excel atau PowerPoint.');
    });

    test('without ffmpeg the recording is kept as sent, as sound', function () {
        config(['services.ffmpeg.enabled' => false]);

        $recording = ($this->speak)(UploadedFile::fake()->createWithContent('rekaman.webm', 'webm-bytes'))->assertCreated()->json('file');

        expect($recording)->toMatchArray(['name' => 'rekaman.webm', 'type' => 'audio/webm', 'size' => 10])
            ->and($recording)->not->toHaveKey('duration');
    });
});
