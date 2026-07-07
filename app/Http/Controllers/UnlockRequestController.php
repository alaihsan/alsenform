<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUnlockRequestRequest;
use App\Http\Requests\VerifyUnlockCodeRequest;
use App\Models\QuizForm;
use App\Models\UnlockRequest;
use App\Support\UnlockCode;
use Illuminate\Http\JsonResponse;

class UnlockRequestController extends Controller
{
    public function index(QuizForm $quizForm): JsonResponse
    {
        abort_unless($quizForm->user_id === auth()->id(), 403);

        $requests = UnlockRequest::query()
            ->whereBelongsTo($quizForm)
            ->latest()
            ->get(['id', 'quiz_form_id', 'respondent_identifier', 'email', 'status', 'created_at', 'updated_at']);

        return response()->json([
            'requests' => $requests,
        ]);
    }

    public function approve(UnlockRequest $unlockRequest): JsonResponse
    {
        $quizForm = $unlockRequest->quizForm;
        abort_unless($quizForm->user_id === auth()->id(), 403);

        $unlockRequest->update([
            'status' => 'approved',
        ]);

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

        return response()->json([
            'message' => 'Permintaan buka kunci berhasil dikirim.',
            'code' => $code,
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

        if ($unlockRequest && $unlockCode->verify(trim($validated['code']), $unlockRequest->unlock_code)) {
            $unlockRequest->update(['status' => 'approved']);

            return response()->json([
                'success' => true,
                'message' => 'Kode benar. Kuis terbuka.',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Kode salah. Silakan coba lagi.',
        ], 422);
    }
}
