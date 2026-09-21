<?php

namespace App\Http\Controllers;

use App\Models\QuizFolder;
use App\Models\QuizForm;
use App\Models\QuizResponse;
use App\Models\UnlockRequest;
use App\Support\QuizFormPayloads;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, QuizFormPayloads $payloads): Response
    {
        $user = $request->user();

        $recentForms = QuizForm::query()
            ->withTrashed()
            ->with(['quizFolder', 'user:id,name,email'])
            ->where(function ($query) use ($user): void {
                $query->whereBelongsTo($user)
                    ->orWhereHas('collaborators', fn ($q) => $q->where('users.id', $user->id));
            })
            ->latest('updated_at')
            ->limit(100)
            ->get()
            ->map(fn (QuizForm $quizForm): array => $payloads->recentForm($quizForm, $user));

        $folders = QuizFolder::query()
            ->whereBelongsTo($request->user())
            ->withCount(['forms' => fn ($query) => $query->whereNull('deleted_at')])
            ->orderBy('name')
            ->get()
            ->map(fn (QuizFolder $folder): array => $payloads->folder($folder));

        $formIds = QuizForm::query()->where('user_id', $user->id)->whereNull('deleted_at')->pluck('id');
        $totalResponses = QuizResponse::query()->whereIn('quiz_form_id', $formIds)->count();
        $pendingUnlocks = UnlockRequest::query()->whereIn('quiz_form_id', $formIds)->where('status', 'pending')->count();
        $publishedCount = QuizForm::query()->where('user_id', $user->id)->whereNotNull('published_at')->whereNull('deleted_at')->count();

        $stats = [
            'totalForms' => $formIds->count(),
            'publishedForms' => $publishedCount,
            'totalResponses' => $totalResponses,
            'pendingUnlocks' => $pendingUnlocks,
        ];

        return Inertia::render('Dashboard', [
            'recentForms' => $recentForms,
            'folders' => $folders,
            'createFolderUrl' => route('folders.store'),
            'stats' => $stats,
        ]);
    }
}
