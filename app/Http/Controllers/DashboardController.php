<?php

namespace App\Http\Controllers;

use App\Models\QuizFolder;
use App\Models\QuizForm;
use App\Support\QuizFormPayloads;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, QuizFormPayloads $payloads): Response
    {
        $recentForms = QuizForm::query()
            ->withTrashed()
            ->with('quizFolder')
            ->whereBelongsTo($request->user())
            ->latest('updated_at')
            ->limit(100)
            ->get()
            ->map(fn (QuizForm $quizForm): array => $payloads->recentForm($quizForm));

        $folders = QuizFolder::query()
            ->whereBelongsTo($request->user())
            ->withCount(['forms' => fn ($query) => $query->whereNull('deleted_at')])
            ->orderBy('name')
            ->get()
            ->map(fn (QuizFolder $folder): array => $payloads->folder($folder));

        return Inertia::render('Dashboard', [
            'recentForms' => $recentForms,
            'folders' => $folders,
            'createFolderUrl' => route('folders.store'),
        ]);
    }
}
