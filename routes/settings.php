<?php

use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('settings/profile', [ProfileController::class, 'update'])->name('profile.update.post');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::post('settings/profile/sessions/logout-other', [ProfileController::class, 'logoutOtherSessions'])->name('profile.sessions.logout-other');
    Route::patch('settings/profile/preferences', [ProfileController::class, 'updatePreferences'])->name('profile.preferences.update');
    Route::patch('settings/profile/appearance', [ProfileController::class, 'updateAppearance'])->name('profile.appearance.update');
    Route::post('settings/profile/proctor-pin', [ProfileController::class, 'updateProctorPin'])->name('profile.proctor-pin.update');
    Route::get('settings/profile/export-quizzes', [ProfileController::class, 'exportQuizzes'])->name('profile.quizzes.export');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('settings/password', [PasswordController::class, 'update'])->name('password.update');

    // Tampilan is part of the profile now (Profil → Tampilan).
    Route::redirect('settings/appearance', '/settings/profile#tampilan')->name('appearance');
});
