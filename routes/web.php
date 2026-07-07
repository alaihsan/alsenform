<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeveloperSupportController;
use App\Http\Controllers\QuestionImportController;
use App\Http\Controllers\QuizFolderController;
use App\Http\Controllers\QuizFormController;
use App\Http\Controllers\QuizResponseController;
use App\Http\Controllers\UnlockRequestController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', DashboardController::class)->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::post('folders', [QuizFolderController::class, 'store'])->name('folders.store');
    Route::patch('folders/{quizFolder}', [QuizFolderController::class, 'update'])->name('folders.update');
    Route::delete('folders/{quizFolder}', [QuizFolderController::class, 'destroy'])->name('folders.destroy');
    Route::get('forms/create/{template?}', [QuizFormController::class, 'create'])->name('forms.create');
    Route::get('forms/{quizForm:slug}/edit', [QuizFormController::class, 'edit'])->name('forms.edit');
    Route::post('forms/{quizForm}/duplicate', [QuizFormController::class, 'duplicate'])->name('forms.duplicate');
    Route::patch('forms/{quizForm}/folder', [QuizFormController::class, 'moveToFolder'])->name('forms.folder');
    Route::patch('forms/{quizForm}', [QuizFormController::class, 'update'])->name('forms.update');
    Route::delete('forms/{quizForm}', [QuizFormController::class, 'destroy'])->name('forms.destroy');
    Route::patch('forms/{quizForm}/restore', [QuizFormController::class, 'restore'])->withTrashed()->name('forms.restore');
    Route::delete('forms/{quizForm}/force-delete', [QuizFormController::class, 'forceDelete'])->withTrashed()->name('forms.force-delete');
    Route::post('forms/media/upload', [QuizFormController::class, 'uploadMedia'])->middleware('throttle:30,1')->name('forms.media.upload');
    Route::post('questions/import', [QuestionImportController::class, 'import'])->name('questions.import');

    // Developer Support Routes
    Route::post('support/suggestion', [DeveloperSupportController::class, 'storeSuggestion'])->middleware('throttle:10,1')->name('support.suggestion');
    Route::post('support/donate', [DeveloperSupportController::class, 'storeDonation'])->middleware('throttle:10,1')->name('support.donate');
    Route::post('support/donate/{donation}/confirm', [DeveloperSupportController::class, 'confirmDonation'])->middleware('throttle:20,1')->name('support.confirm-donation');
    Route::get('support/stats', [DeveloperSupportController::class, 'getStats'])->middleware('throttle:30,1')->name('support.stats');
    Route::post('support/withdraw', [DeveloperSupportController::class, 'storeWithdrawal'])->middleware('throttle:5,1')->name('support.withdraw');

    // Unlock Request Admin Routes
    Route::get('forms/{quizForm}/unlock-requests', [UnlockRequestController::class, 'index'])->name('forms.unlock-requests.index');
    Route::post('unlock-requests/{unlockRequest}/approve', [UnlockRequestController::class, 'approve'])->name('forms.unlock-requests.approve');
});

Route::get('forms/{quizForm:slug}', [QuizResponseController::class, 'show'])->name('forms.public');
Route::post('forms/{quizForm:slug}/responses', [QuizResponseController::class, 'store'])->middleware('throttle:20,1')->name('forms.responses.store');
Route::post('forms/{quizForm:slug}/unlock-requests', [UnlockRequestController::class, 'store'])->middleware('throttle:5,1')->name('forms.public.unlock-requests.store');
Route::get('forms/{quizForm:slug}/unlock-requests/status/{identifier}', [UnlockRequestController::class, 'status'])->middleware('throttle:60,1')->name('forms.public.unlock-requests.status');
Route::post('forms/{quizForm:slug}/unlock', [UnlockRequestController::class, 'verify'])->middleware('throttle:5,1')->name('forms.public.unlock-verify');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
