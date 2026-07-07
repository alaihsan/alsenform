<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreQuizFolderRequest;
use App\Http\Requests\UpdateQuizFolderRequest;
use App\Models\QuizFolder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class QuizFolderController extends Controller
{
    public function store(StoreQuizFolderRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        QuizFolder::query()->firstOrCreate([
            'user_id' => $request->user()->id,
            'name' => trim($validated['name']),
        ]);

        return to_route('dashboard');
    }

    public function update(UpdateQuizFolderRequest $request, QuizFolder $quizFolder): RedirectResponse
    {
        $validated = $request->validated();
        $name = trim($validated['name']);

        $quizFolder->update([
            'name' => $name,
        ]);

        $quizFolder->forms()->update([
            'folder' => $name,
        ]);

        return to_route('dashboard');
    }

    public function destroy(QuizFolder $quizFolder): RedirectResponse
    {
        Gate::authorize('delete', $quizFolder);

        $quizFolder->forms()->update([
            'folder' => null,
            'quiz_folder_id' => null,
        ]);

        $quizFolder->delete();

        return to_route('dashboard');
    }
}
