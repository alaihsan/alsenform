<?php

namespace App\Http\Controllers;

use App\Actions\SubmitExpiredAttempts;
use App\Http\Requests\StoreUnlockRequestRequest;
use App\Http\Requests\VerifyUnlockCodeRequest;
use App\Models\QuizForm;
use App\Models\QuizSession;
use App\Models\UnlockRequest;
use App\Support\UnlockCode;
use Illuminate\Http\JsonResponse;

class UnlockRequestController extends Controller
{
    /**
     * The attempts the teacher can unlock: every attempt that is or was locked, also when the
     * student never asked to be unlocked, plus unlock requests without an attempt.
     */
    public function index(QuizForm $quizForm, SubmitExpiredAttempts $submitExpiredAttempts): JsonResponse
    {
        abort_unless($quizForm->canBeEditedBy(auth()->user()), 403);

        // Polled while the teacher watches the exam: hand in the attempts whose time ran out.
        $submitExpiredAttempts->handleQuietly($quizForm);

        $requests = UnlockRequest::query()
            ->whereBelongsTo($quizForm)
            ->latest()
            ->get(['id', 'quiz_form_id', 'respondent_identifier', 'email', 'status', 'created_at', 'updated_at'])
            ->keyBy('respondent_identifier');

        $sessions = QuizSession::query()
            ->where('quiz_form_id', $quizForm->id)
            ->where(fn ($query) => $query
                ->where('is_locked', true)
                ->orWhere('blur_count', '>', 0)
                ->orWhereIn('respondent_identifier', $requests->keys()))
            ->with('user:id,name,kelas,email')
            ->get()
            ->keyBy('respondent_identifier');

        $entries = $sessions
            ->map(fn (QuizSession $session): array => $this->entry($quizForm, $session, $requests->get($session->respondent_identifier)))
            ->concat($requests
                ->reject(fn (UnlockRequest $unlockRequest): bool => $sessions->has($unlockRequest->respondent_identifier))
                ->map(fn (UnlockRequest $unlockRequest): array => $this->entry($quizForm, null, $unlockRequest)))
            ->sortBy([
                fn (array $a, array $b): int => ($a['state'] === 'locked' ? 0 : 1) <=> ($b['state'] === 'locked' ? 0 : 1),
                fn (array $a, array $b): int => strcmp((string) $b['last_activity_at'], (string) $a['last_activity_at']),
            ])
            ->values();

        return response()->json([
            'lock_on_blur' => $quizForm->locksOnBlur(),
            'locked_count' => $entries->where('state', 'locked')->count(),
            'requests' => $entries,
        ]);
    }

    public function approve(UnlockRequest $unlockRequest): JsonResponse
    {
        $quizForm = $unlockRequest->quizForm;
        abort_unless($quizForm->canBeEditedBy(auth()->user()), 403);

        $unlockRequest->update([
            'status' => 'approved',
        ]);

        QuizSession::query()
            ->where('quiz_form_id', $quizForm->id)
            ->where('respondent_identifier', $unlockRequest->respondent_identifier)
            ->get()
            ->each(fn (QuizSession $session) => $session->unlock());

        return response()->json([
            'message' => 'Request berhasil disetujui.',
            'request' => $unlockRequest->only(['id', 'quiz_form_id', 'respondent_identifier', 'email', 'status']),
        ]);
    }

    /**
     * Unlock one attempt, whether or not the student asked for it. The exam page notices
     * within seconds and lets the student continue.
     */
    public function unlockSession(QuizForm $quizForm, QuizSession $quizSession): JsonResponse
    {
        abort_unless($quizForm->canBeEditedBy(auth()->user()), 403);
        abort_unless($quizSession->quiz_form_id === $quizForm->id, 404);

        $quizSession->unlock();

        $unlockRequest = UnlockRequest::query()
            ->whereBelongsTo($quizForm)
            ->where('respondent_identifier', $quizSession->respondent_identifier)
            ->first();

        if ($unlockRequest?->status === 'pending') {
            $unlockRequest->update(['status' => 'approved']);
        }

        return response()->json([
            'message' => 'Kunci dibuka. Siswa dapat melanjutkan ujian.',
            'request' => $this->entry($quizForm, $quizSession->load('user:id,name,kelas,email'), $unlockRequest),
        ]);
    }

    /**
     * Unlock every locked attempt of the form at once.
     */
    public function unlockAll(QuizForm $quizForm): JsonResponse
    {
        abort_unless($quizForm->canBeEditedBy(auth()->user()), 403);

        $unlocked = $quizForm->releaseLockedSessions();

        return response()->json([
            'message' => $unlocked > 0 ? "{$unlocked} siswa dibuka kuncinya." : 'Tidak ada siswa yang terkunci.',
            'unlocked' => $unlocked,
        ]);
    }

    public function store(StoreUnlockRequestRequest $request, QuizForm $quizForm, UnlockCode $unlockCode): JsonResponse
    {
        abort_unless($quizForm->published_at, 404);

        $validated = $request->validated();
        $code = $unlockCode->generate();

        // Asking only notifies the teacher; the attempt itself is locked by the lock request.
        $unlockRequest = UnlockRequest::query()->updateOrCreate(
            [
                'quiz_form_id' => $quizForm->id,
                'respondent_identifier' => $validated['respondent_identifier'],
            ],
            [
                'email' => $validated['email'] ?? null,
                'unlock_code' => $unlockCode->hash($code),
                'status' => 'pending',
            ]
        );

        return response()->json([
            'message' => 'Permintaan buka kunci berhasil dikirim.',
            'request' => $unlockRequest->only(['id', 'quiz_form_id', 'respondent_identifier', 'email', 'status']),
        ]);
    }

    /**
     * The status of the unlock request of a respondent. Exam pages now poll the lock state of
     * their attempt (see QuizResponseController::examStatus); this stays for pages loaded
     * before that.
     */
    public function status(QuizForm $quizForm, string $identifier): JsonResponse
    {
        abort_unless($quizForm->published_at, 404);

        $unlockRequest = UnlockRequest::query()
            ->whereBelongsTo($quizForm)
            ->where('respondent_identifier', $identifier)
            ->first();

        return response()->json([
            'status' => $unlockRequest ? $unlockRequest->status : 'none',
        ]);
    }

    public function verify(VerifyUnlockCodeRequest $request, QuizForm $quizForm, UnlockCode $unlockCode): JsonResponse
    {
        abort_unless($quizForm->published_at, 404);

        $validated = $request->validated();

        $unlockRequest = UnlockRequest::query()
            ->whereBelongsTo($quizForm)
            ->where('respondent_identifier', $validated['respondent_identifier'])
            ->first();

        if ($unlockRequest && $unlockRequest->status !== 'used' && $unlockCode->verify(trim($validated['code']), $unlockRequest->unlock_code)) {
            $unlockRequest->update(['status' => 'used']);

            QuizSession::query()
                ->where('quiz_form_id', $quizForm->id)
                ->where('respondent_identifier', $validated['respondent_identifier'])
                ->get()
                ->each(fn (QuizSession $session) => $session->unlock());

            return response()->json([
                'success' => true,
                'message' => 'Kode benar. Kuis terbuka.',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Kode salah atau sudah tidak berlaku. Silakan coba lagi.',
        ], 422);
    }

    /**
     * One row of the unlock panel: an attempt with its unlock request, or a request alone.
     *
     * @return array<string, mixed>
     */
    protected function entry(QuizForm $quizForm, ?QuizSession $session, ?UnlockRequest $unlockRequest): array
    {
        $blurLogs = $session?->blur_logs ?? [];
        $lastBlur = $blurLogs !== [] ? (end($blurLogs)['timestamp'] ?? null) : null;

        $state = match (true) {
            $session?->submitted_at !== null => 'submitted',
            $session !== null => $session->isEffectivelyLocked($quizForm) ? 'locked' : 'unlocked',
            default => $unlockRequest?->status === 'pending' && $quizForm->locksOnBlur() ? 'locked' : 'unlocked',
        };

        $lastActivity = collect([$session?->locked_at, $session?->unlocked_at, $unlockRequest?->updated_at])
            ->filter()
            ->max();

        return [
            'id' => $session !== null ? 'session-'.$session->id : 'request-'.$unlockRequest?->id,
            'quiz_form_id' => $quizForm->id,
            'session_id' => $session?->id,
            'request_id' => $unlockRequest?->id,
            'respondent_identifier' => $session?->respondent_identifier ?? $unlockRequest?->respondent_identifier,
            'email' => $unlockRequest?->email ?? $session?->respondent_email ?? $session?->user?->email,
            // A guest's name and class from the confirmation screen; an account's own name.
            'name' => $session?->user?->name ?? $session?->respondent_name,
            'kelas' => $session?->user?->kelas ?? $session?->respondent_class,
            'state' => $state,
            'status' => $unlockRequest?->status ?? 'none',
            'unlock_requested' => $state === 'locked' && $unlockRequest?->status === 'pending',
            'created_at' => $unlockRequest?->created_at ?? $session?->locked_at,
            'updated_at' => $unlockRequest?->updated_at,
            'locked_at' => $session?->locked_at,
            'unlocked_at' => $session?->unlocked_at,
            'submitted_at' => $session?->submitted_at,
            'last_activity_at' => $lastActivity?->toISOString(),
            'blur_count' => $session?->blur_count ?? 0,
            'last_blur_at' => $lastBlur,
        ];
    }
}
