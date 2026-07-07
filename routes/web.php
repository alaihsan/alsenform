<?php

use App\Http\Controllers\DeveloperSupportController;
use App\Http\Controllers\QuestionImportController;
use App\Http\Controllers\QuizFormController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', [QuizFormController::class, 'dashboard'])->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::post('folders', [QuizFormController::class, 'storeFolder'])->name('folders.store');
    Route::patch('folders/{quizFolder}', [QuizFormController::class, 'updateFolder'])->name('folders.update');
    Route::delete('folders/{quizFolder}', [QuizFormController::class, 'destroyFolder'])->name('folders.destroy');
    Route::get('forms/create/{template?}', [QuizFormController::class, 'create'])->name('forms.create');
    Route::get('forms/{quizForm:slug}/edit', [QuizFormController::class, 'edit'])->name('forms.edit');
    Route::post('forms/{quizForm}/duplicate', [QuizFormController::class, 'duplicate'])->name('forms.duplicate');
    Route::patch('forms/{quizForm}/folder', [QuizFormController::class, 'moveToFolder'])->name('forms.folder');
    Route::patch('forms/{quizForm}', [QuizFormController::class, 'update'])->name('forms.update');
    Route::delete('forms/{quizForm}', [QuizFormController::class, 'destroy'])->name('forms.destroy');
    Route::patch('forms/{quizForm}/restore', [QuizFormController::class, 'restore'])->withTrashed()->name('forms.restore');
    Route::delete('forms/{quizForm}/force-delete', [QuizFormController::class, 'forceDelete'])->withTrashed()->name('forms.force-delete');
    Route::post('forms/media/upload', [QuizFormController::class, 'uploadMedia'])->name('forms.media.upload');
    Route::post('questions/import', [QuestionImportController::class, 'import'])->name('questions.import');

    // Developer Support Routes
    Route::post('support/suggestion', [DeveloperSupportController::class, 'storeSuggestion'])->name('support.suggestion');
    Route::post('support/donate', [DeveloperSupportController::class, 'storeDonation'])->name('support.donate');
    Route::post('support/donate/{donation}/confirm', [DeveloperSupportController::class, 'confirmDonation'])->name('support.confirm-donation');
    Route::get('support/stats', [DeveloperSupportController::class, 'getStats'])->name('support.stats');
    Route::post('support/withdraw', [DeveloperSupportController::class, 'storeWithdrawal'])->name('support.withdraw');

    // Unlock Request Admin Routes
    Route::get('forms/{quizForm}/unlock-requests', [QuizFormController::class, 'getUnlockRequests'])->name('forms.unlock-requests.index');
    Route::post('unlock-requests/{unlockRequest}/approve', [QuizFormController::class, 'approveUnlockRequest'])->name('forms.unlock-requests.approve');
});

Route::get('forms/{quizForm:slug}', [QuizFormController::class, 'show'])->name('forms.public');
Route::post('forms/{quizForm:slug}/responses', [QuizFormController::class, 'storeResponse'])->name('forms.responses.store');
Route::post('forms/{quizForm:slug}/unlock-requests', [QuizFormController::class, 'storeUnlockRequest'])->name('forms.public.unlock-requests.store');
Route::get('forms/{quizForm:slug}/unlock-requests/status/{identifier}', [QuizFormController::class, 'checkUnlockRequestStatus'])->name('forms.public.unlock-requests.status');
Route::post('forms/{quizForm:slug}/unlock', [QuizFormController::class, 'verifyUnlockCode'])->name('forms.public.unlock-verify');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
