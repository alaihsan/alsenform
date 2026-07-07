<?php

use App\Http\Controllers\QuizFormController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', [QuizFormController::class, 'dashboard'])->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('forms/create/{template?}', [QuizFormController::class, 'create'])->name('forms.create');
    Route::get('forms/{quizForm:slug}/edit', [QuizFormController::class, 'edit'])->name('forms.edit');
    Route::patch('forms/{quizForm}', [QuizFormController::class, 'update'])->name('forms.update');
    Route::post('forms/media/upload', [QuizFormController::class, 'uploadMedia'])->name('forms.media.upload');
});

Route::get('forms/{quizForm:slug}', [QuizFormController::class, 'show'])->name('forms.public');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
