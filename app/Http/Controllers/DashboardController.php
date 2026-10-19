<?php

namespace App\Http\Controllers;

use App\Models\QuizFolder;
use App\Models\QuizForm;
use App\Models\QuizResponse;
use App\Models\UnlockRequest;
use App\Services\Addons;
use App\Support\Addon;
use App\Support\DisplayTime;
use App\Support\QuizFormPayloads;
use App\Support\QuizItems;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, QuizFormPayloads $payloads, Addons $addons): Response
    {
        $user = $request->user();

        if ($user->isStudent()) {
            $userCohortIds = $user->cohorts()->pluck('cohorts.id')->all();

            $forms = QuizForm::query()
                ->whereNotNull('published_at')
                ->whereNull('deleted_at')
                ->where(function ($query) use ($userCohortIds, $user): void {
                    // Open to everyone, or to the student's cohorts, or chosen for them (remedial / enrichment).
                    $query->where(fn ($open) => $open->whereDoesntHave('cohorts')->whereDoesntHave('participants'));
                    if (! empty($userCohortIds)) {
                        $query->orWhereHas('cohorts', function ($q) use ($userCohortIds): void {
                            $q->whereIn('cohorts.id', $userCohortIds);
                        });
                    }
                    $query->orWhereHas('participants', fn ($q) => $q->where('users.id', $user->id));
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
                    'design' => $quizForm->cardDesign(),
                    'ownerName' => $quizForm->user?->name ?? 'Guru',
                    'questionsCount' => count(QuizItems::questions($quizForm->questions)),
                    'updatedLabel' => DisplayTime::ago($quizForm->published_at) ?? '',
                    'updatedAt' => (string) $quizForm->published_at,
                    'hasSubmitted' => $hasSubmitted,
                    // The student's results, once the teacher shows them.
                    'resultsUrl' => $hasSubmitted && ! empty($quizForm->settings['showResultsSummary'])
                        ? route('forms.results', ['quizForm' => $quizForm->slug])
                        : null,
                    'scheduleLabel' => $this->scheduleLabel($quizForm),
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
            // Rekap Kuis add-on: its tile in the apps menu is there while the add-on is installed.
            'quizRecapEnabled' => $addons->isInstalledFor(Addon::QuizRecap, $user),
        ]);
    }

    /**
     * Jadwal ujian on a student's card: when it opens, until when it is open, or that it closed.
     */
    protected function scheduleLabel(QuizForm $quizForm): ?string
    {
        $format = fn ($time): ?string => DisplayTime::format($time, 'D, j M H.i');

        return match ($quizForm->scheduleState()) {
            'not_yet' => 'Dibuka '.$format($quizForm->opensAt()),
            'closed' => 'Sudah ditutup',
            default => $quizForm->closesAt() !== null ? 'Ditutup '.$format($quizForm->closesAt()) : null,
        };
    }
}
