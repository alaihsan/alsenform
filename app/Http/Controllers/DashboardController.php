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

        if ($user->isStudent()) {
            $userCohortIds = $user->cohorts()->pluck('cohorts.id')->all();

            $forms = QuizForm::query()
                ->whereNotNull('published_at')
                ->whereNull('deleted_at')
                ->where(function ($query) use ($userCohortIds): void {
                    $query->whereDoesntHave('cohorts');
                    if (! empty($userCohortIds)) {
                        $query->orWhereHas('cohorts', function ($q) use ($userCohortIds): void {
                            $q->whereIn('cohorts.id', $userCohortIds);
                        });
                    }
                })
                ->with(['user:id,name', 'cohorts:id,name'])
                ->latest('published_at')
                ->limit(100)
                ->get();

            $submittedFormIds = QuizResponse::query()
                ->where('user_id', $user->id)
                ->whereIn('quiz_form_id', $forms->pluck('id'))
                ->pluck('quiz_form_id')
                ->all();

            $recentForms = $forms->map(function (QuizForm $quizForm) use ($submittedFormIds): array {
                $hasSubmitted = in_array($quizForm->id, $submittedFormIds, true);

                return [
                    'id' => $quizForm->id,
                    'title' => $quizForm->title,
                    'description' => $quizForm->description ?? '',
                    'folderId' => null,
                    'folder' => null,
                    'slug' => $quizForm->slug,
                    'editUrl' => '',
                    'publicUrl' => route('forms.public', ['quizForm' => $quizForm->slug]),
                    'duplicateUrl' => '',
                    'moveFolderUrl' => '',
                    'deleteUrl' => '',
                    'restoreUrl' => '',
                    'forceDeleteUrl' => '',
                    'isPublished' => true,
                    'isTrashed' => false,
                    'isCollaborator' => false,
                    'tone' => 'bg-indigo-50',
                    'stripe' => 'bg-indigo-600',
                    'accent' => 'bg-indigo-500',
                    'ownerName' => $quizForm->user?->name ?? 'Guru',
                    'questionsCount' => count($quizForm->questions ?? []),
                    'updatedLabel' => $quizForm->published_at?->diffForHumans() ?? '',
                    'updatedAt' => (string) $quizForm->published_at,
                    'hasSubmitted' => $hasSubmitted,
                ];
            });

            $completedCount = count($submittedFormIds);
            $totalCount = $forms->count();
            $pendingCount = max(0, $totalCount - $completedCount);

            return Inertia::render('Dashboard', [
                'recentForms' => $recentForms,
                'folders' => [],
                'createFolderUrl' => '',
                'stats' => [
                    'totalForms' => $totalCount,
                    'publishedForms' => $completedCount,
                    'totalResponses' => $pendingCount,
                    'pendingUnlocks' => 0,
                ],
                'studentStats' => [
                    'totalAssigned' => $totalCount,
                    'completed' => $completedCount,
                    'pending' => $pendingCount,
                    'className' => $user->kelas ?? 'Siswa',
                ],
            ]);
        }

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
