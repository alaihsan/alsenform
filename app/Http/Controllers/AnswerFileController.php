<?php

namespace App\Http\Controllers;

use App\Models\AnswerFile;
use App\Models\QuizForm;
use App\Models\QuizSession;
use App\Services\AudioCompressor;
use App\Support\ExamLayout;
use App\Support\FileAnswers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnswerFileController extends Controller
{
    /**
     * Photos of work on paper, documents and slides: what students hand in at school.
     */
    public const EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'heic', 'heif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp', 'txt'];

    /**
     * Megabytes per file when the teacher has not set a limit.
     */
    public const DEFAULT_MAX_MEGABYTES = 10;

    /**
     * Files one attempt may upload for one question (removed ones included): a guard against abuse.
     */
    protected const MAX_UPLOADS_PER_QUESTION = 20;

    /**
     * Megabytes of a recording sent for a "Voice answer" question, at the least: it is compressed
     * on the server, so a WAV of a phone fits too.
     */
    public const MIN_VOICE_MEGABYTES = 20;

    /**
     * Seconds a recording may run past the limit of its question: the recorder stops a moment late.
     */
    protected const VOICE_DURATION_GRACE_SECONDS = 3;

    /**
     * A student hands in a file for a "File upload" question of their running attempt, or their
     * recording for a "Voice answer" question (compressed to a light MP3). The answer itself (the
     * list of files) is saved with the draft and the submission.
     */
    public function store(Request $request, QuizForm $quizForm, ExamLayout $examLayout, AudioCompressor $compressor): JsonResponse
    {
        abort_unless($quizForm->published_at || $request->user()?->is($quizForm->user), 404);

        // The question decides which files it takes.
        $request->validate(['question_id' => ['required', 'string', 'max:64']]);

        $question = collect(is_array($quizForm->questions) ? $quizForm->questions : [])
            ->first(fn (mixed $question): bool => is_array($question) && (string) ($question['id'] ?? '') === (string) $request->input('question_id'));
        $isVoice = FileAnswers::isVoiceQuestion($question);

        $maxMegabytes = $isVoice ? max(self::MIN_VOICE_MEGABYTES, self::maxMegabytes($quizForm)) : self::maxMegabytes($quizForm);
        $validated = $request->validate([
            'session_token' => ['required', 'string', 'max:100'],
            'question_id' => ['required', 'string', 'max:64'],
            'file' => $isVoice
                ? ['required', 'file', 'max:'.($maxMegabytes * 1024), 'extensions:'.implode(',', AudioCompressor::EXTENSIONS), 'mimetypes:'.implode(',', AudioCompressor::MIME_TYPES)]
                : ['required', 'file', 'max:'.($maxMegabytes * 1024), 'extensions:'.implode(',', self::EXTENSIONS)],
        ], [
            'file.required' => $isVoice ? 'Rekam atau pilih suara yang akan dikirim.' : 'Pilih berkas yang akan dikirim.',
            'file.uploaded' => "Berkas gagal terkirim. Ukurannya paling besar {$maxMegabytes} MB; coba lagi.",
            'file.max' => "Berkas terlalu besar: paling besar {$maxMegabytes} MB.",
            'file.extensions' => $isVoice
                ? 'Jenis berkas tidak didukung. Kirim rekaman suara (MP3, M4A, WAV, OGG, WebM atau rekaman HP).'
                : 'Jenis berkas tidak didukung. Kirim foto, PDF, Word, Excel atau PowerPoint.',
            'file.mimetypes' => 'Berkas ini bukan rekaman suara.',
        ]);

        $session = QuizSession::query()
            ->where('quiz_form_id', $quizForm->id)
            ->where('session_token', $validated['session_token'])
            ->first();

        abort_if($session === null, 404);
        abort_if($session->user_id !== null && $request->user() !== null && $session->user_id !== $request->user()->id, 403);
        abort_if($session->started_at === null, 403, 'Ujian belum dimulai.');
        abort_if($session->submitted_at !== null, 409, 'Jawaban ujian ini sudah dikirim.');
        abort_if($session->isEffectivelyLocked($quizForm), 403, 'Ujian sedang terkunci. Hubungi pengawas.');
        abort_if($session->expires_at !== null && now()->gt($session->expires_at->copy()->addSeconds(60)), 409, 'Waktu ujian sudah habis.');

        $assigned = $examLayout->assignedIds($quizForm, $session->layout);
        abort_if(
            ! FileAnswers::isFileQuestion($question) || ($assigned !== null && ! in_array($validated['question_id'], array_map('strval', $assigned), true)),
            422,
            'Soal ini tidak menerima berkas.',
        );
        abort_if(
            AnswerFile::query()->where('quiz_session_id', $session->id)->where('question_id', $validated['question_id'])->count() >= self::MAX_UPLOADS_PER_QUESTION,
            429,
            'Terlalu banyak berkas untuk soal ini.',
        );

        $file = $request->file('file');
        $directory = "answer-files/{$quizForm->id}/{$session->id}";
        $name = Str::limit(basename(str_replace('\\', '/', $file->getClientOriginalName())), 200, '') ?: 'berkas';

        if ($isVoice) {
            $probe = $compressor->probe((string) $file->getRealPath());
            abort_if($probe !== null && ! $probe['hasAudio'], 422, 'Berkas ini tidak berisi suara.');

            $limit = self::voiceSecondsLimit($question);
            abort_if(
                $limit !== null && ($probe['duration'] ?? 0) > $limit + self::VOICE_DURATION_GRACE_SECONDS,
                422,
                'Rekaman terlalu panjang: paling lama '.self::durationLabel($limit).'.',
            );

            $stored = $compressor->store($file, $directory, 'local', AudioCompressor::SPEECH_KBPS);
            $attributes = [
                'path' => $stored['path'],
                'original_name' => $stored['compressed'] ? (pathinfo($name, PATHINFO_FILENAME) ?: 'rekaman').'.mp3' : $name,
                'mime_type' => $stored['mime'],
                'size' => $stored['size'],
                'duration' => $stored['duration'],
            ];
        } else {
            $attributes = [
                'path' => $file->store($directory, 'local'),
                'original_name' => $name,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ];
        }

        $answerFile = AnswerFile::query()->create([
            'quiz_form_id' => $quizForm->id,
            'quiz_session_id' => $session->id,
            'question_id' => $validated['question_id'],
            ...$attributes,
        ]);

        $session->markSeen();

        return response()->json(['file' => $answerFile->asAnswer(), 'maxFiles' => FileAnswers::MAX_FILES_PER_QUESTION], 201);
    }

    /**
     * A handed-in file, for the exam's teachers: images open in the browser, others download.
     */
    public function show(QuizForm $quizForm, AnswerFile $answerFile): StreamedResponse|BinaryFileResponse
    {
        abort_unless($quizForm->canBeEditedBy(auth()->user()), 403);
        abort_unless($answerFile->quiz_form_id === $quizForm->id && Storage::disk('local')->exists($answerFile->path), 404);

        $headers = ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=3600'];

        // A recording plays in the page, with byte ranges so Safari plays it and the teacher can seek.
        if ($answerFile->isAudio()) {
            return response()->file(Storage::disk('local')->path($answerFile->path), ['Content-Type' => $answerFile->mime_type, ...$headers]);
        }

        return $answerFile->isImage() && ! str_contains((string) $answerFile->mime_type, 'svg')
            ? Storage::disk('local')->response($answerFile->path, $answerFile->original_name, ['Content-Type' => $answerFile->mime_type, ...$headers])
            : Storage::disk('local')->download($answerFile->path, $answerFile->original_name, $headers);
    }

    /**
     * The longest a spoken answer may be, in seconds (null: no limit).
     *
     * @param  array<string, mixed>|null  $question
     */
    public static function voiceSecondsLimit(?array $question): ?int
    {
        $seconds = $question['maxDuration'] ?? null;

        return is_numeric($seconds) && $seconds > 0 ? (int) $seconds : null;
    }

    protected static function durationLabel(int $seconds): string
    {
        return $seconds < 60 || $seconds % 60 !== 0 ? "{$seconds} detik" : ($seconds / 60).' menit';
    }

    public static function maxMegabytes(QuizForm $quizForm): int
    {
        $limit = $quizForm->settings['maxUploadSize'] ?? null;

        return is_numeric($limit) && $limit >= 1 ? min(40, (int) $limit) : self::DEFAULT_MAX_MEGABYTES;
    }
}
