<?php

namespace App\Http\Controllers;

use App\Models\AnswerFile;
use App\Models\QuizForm;
use App\Models\QuizSession;
use App\Support\ExamLayout;
use App\Support\FileAnswers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
     * A student hands in a file for a "File upload" question of their running attempt. The answer
     * itself (the list of files) is saved with the draft and the submission.
     */
    public function store(Request $request, QuizForm $quizForm, ExamLayout $examLayout): JsonResponse
    {
        abort_unless($quizForm->published_at || $request->user()?->is($quizForm->user), 404);

        $maxMegabytes = self::maxMegabytes($quizForm);
        $validated = $request->validate([
            'session_token' => ['required', 'string', 'max:100'],
            'question_id' => ['required', 'string', 'max:64'],
            'file' => ['required', 'file', 'max:'.($maxMegabytes * 1024), 'extensions:'.implode(',', self::EXTENSIONS)],
        ], [
            'file.required' => 'Pilih berkas yang akan dikirim.',
            'file.uploaded' => "Berkas gagal terkirim. Ukurannya paling besar {$maxMegabytes} MB; coba lagi.",
            'file.max' => "Berkas terlalu besar: paling besar {$maxMegabytes} MB.",
            'file.extensions' => 'Jenis berkas tidak didukung. Kirim foto, PDF, Word, Excel atau PowerPoint.',
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

        $question = collect(is_array($quizForm->questions) ? $quizForm->questions : [])
            ->first(fn (mixed $question): bool => is_array($question) && (string) ($question['id'] ?? '') === $validated['question_id']);
        $assigned = $examLayout->assignedIds($quizForm, $session->layout);
        abort_if(
            $question === null || ($question['type'] ?? '') !== 'File upload' || ($assigned !== null && ! in_array($validated['question_id'], array_map('strval', $assigned), true)),
            422,
            'Soal ini tidak menerima berkas.',
        );
        abort_if(
            AnswerFile::query()->where('quiz_session_id', $session->id)->where('question_id', $validated['question_id'])->count() >= self::MAX_UPLOADS_PER_QUESTION,
            429,
            'Terlalu banyak berkas untuk soal ini.',
        );

        $file = $request->file('file');
        $answerFile = AnswerFile::query()->create([
            'quiz_form_id' => $quizForm->id,
            'quiz_session_id' => $session->id,
            'question_id' => $validated['question_id'],
            'path' => $file->store("answer-files/{$quizForm->id}/{$session->id}", 'local'),
            'original_name' => Str::limit(basename(str_replace('\\', '/', $file->getClientOriginalName())), 200, '') ?: 'berkas',
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);

        $session->markSeen();

        return response()->json(['file' => $answerFile->asAnswer(), 'maxFiles' => FileAnswers::MAX_FILES_PER_QUESTION], 201);
    }

    /**
     * A handed-in file, for the exam's teachers: images open in the browser, others download.
     */
    public function show(QuizForm $quizForm, AnswerFile $answerFile): StreamedResponse
    {
        abort_unless($quizForm->canBeEditedBy(auth()->user()), 403);
        abort_unless($answerFile->quiz_form_id === $quizForm->id && Storage::disk('local')->exists($answerFile->path), 404);

        $headers = ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=3600'];

        return $answerFile->isImage() && ! str_contains((string) $answerFile->mime_type, 'svg')
            ? Storage::disk('local')->response($answerFile->path, $answerFile->original_name, ['Content-Type' => $answerFile->mime_type, ...$headers])
            : Storage::disk('local')->download($answerFile->path, $answerFile->original_name, $headers);
    }

    public static function maxMegabytes(QuizForm $quizForm): int
    {
        $limit = $quizForm->settings['maxUploadSize'] ?? null;

        return is_numeric($limit) && $limit >= 1 ? min(40, (int) $limit) : self::DEFAULT_MAX_MEGABYTES;
    }
}
