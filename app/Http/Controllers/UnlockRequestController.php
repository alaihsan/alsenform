<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUnlockRequestRequest;
use App\Http\Requests\VerifyUnlockCodeRequest;
use App\Models\QuizForm;
use App\Models\QuizSession;
use App\Models\UnlockRequest;
use App\Support\UnlockCode;
use Illuminate\Http\JsonResponse;

class UnlockRequestController extends Controller
{
    public function index(QuizForm $quizForm): JsonResponse
    {
        abort_unless($quizForm->canBeEditedBy(auth()->user()), 403);

        $requests = UnlockRequest::query()
            ->whereBelongsTo($quizForm)
            ->latest()
            ->get(['id', 'quiz_form_id', 'respondent_identifier', 'email', 'status', 'created_at', 'updated_at']);

        $sessions = QuizSession::query()
            ->where('quiz_form_id', $quizForm->id)
            ->whereIn('respondent_identifier', $requests->pluck('respondent_identifier'))
            ->get()
            ->keyBy('respondent_identifier');

        $requestsData = $requests->map(function ($req) use ($sessions) {
            $session = $sessions->get($req->respondent_identifier);
            $blurLogs = $session?->blur_logs ?? [];
            $lastBlur = ! empty($blurLogs) ? (end($blurLogs)['timestamp'] ?? null) : null;

            return [
                'id' => $req->id,
                'quiz_form_id' => $req->quiz_form_id,
                'respondent_identifier' => $req->respondent_identifier,
                'email' => $req->email,
                'status' => $req->status,
                'created_at' => $req->created_at,
                'updated_at' => $req->updated_at,
                'blur_count' => $session?->blur_count ?? 0,
                'last_blur_at' => $lastBlur,
            ];
        });

        return response()->json([
            'requests' => $requestsData,
        ]);
    }

    public function approve(UnlockRequest $unlockRequest): JsonResponse
    {
        $quizForm = $unlockRequest->quizForm;
        abort_unless($quizForm->canBeEditedBy(auth()->user()), 403);

        $unlockRequest->update([
            'status' => 'approved',
        ]);

        // Unlock quiz session on server
        QuizSession::where('quiz_form_id', $quizForm->id)
            ->where('respondent_identifier', $unlockRequest->respondent_identifier)
            ->update(['is_locked' => false]);

        return response()->json([
            'message' => 'Request berhasil disetujui.',
            'request' => $unlockRequest->only(['id', 'quiz_form_id', 'respondent_identifier', 'email', 'status']),
        ]);
    }

    public function store(StoreUnlockRequestRequest $request, QuizForm $quizForm, UnlockCode $unlockCode): JsonResponse
    {
        abort_unless($quizForm->published_at, 404);

        $validated = $request->validated();
        $code = $unlockCode->generate();

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

        // Lock session on server
        QuizSession::where('quiz_form_id', $quizForm->id)
            ->where('respondent_identifier', $validated['respondent_identifier'])
            ->update(['is_locked' => true]);

        return response()->json([
            'message' => 'Permintaan buka kunci berhasil dikirim.',
            'request' => $unlockRequest->only(['id', 'quiz_form_id', 'respondent_identifier', 'email', 'status']),
        ]);
    }

    public function status(QuizForm $quizForm, string $identifier): JsonResponse
    {
        abort_unless($quizForm->published_at, 404);

        $unlockRequest = UnlockRequest::query()
            ->whereBelongsTo($quizForm)
            ->where('respondent_identifier', $identifier)
            ->first();

        if ($unlockRequest && $unlockRequest->status === 'approved') {
            QuizSession::where('quiz_form_id', $quizForm->id)
                ->where('respondent_identifier', $identifier)
                ->update(['is_locked' => false]);
        }

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

            QuizSession::where('quiz_form_id', $quizForm->id)
                ->where('respondent_identifier', $validated['respondent_identifier'])
                ->update(['is_locked' => false]);

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
}
