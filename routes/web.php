<?php

use App\Http\Controllers\ActivityReportController;
use App\Http\Controllers\AddonController;
use App\Http\Controllers\AnswerFileController;
use App\Http\Controllers\ClassMapController;
use App\Http\Controllers\CohortController;
use App\Http\Controllers\ConnectionCheckController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeveloperSupportController;
use App\Http\Controllers\ExamProgressController;
use App\Http\Controllers\ExamTokenController;
use App\Http\Controllers\FollowUpExamController;
use App\Http\Controllers\GradingController;
use App\Http\Controllers\PageFailureController;
use App\Http\Controllers\QuestionBankController;
use App\Http\Controllers\QuestionImportController;
use App\Http\Controllers\QuizFolderController;
use App\Http\Controllers\QuizFormCollaboratorController;
use App\Http\Controllers\QuizFormController;
use App\Http\Controllers\QuizRecapController;
use App\Http\Controllers\QuizResponseController;
use App\Http\Controllers\QuizResponseExportController;
use App\Http\Controllers\QuizResultController;
use App\Http\Controllers\SmartFilterController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\UnlockRequestController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\EnsureDonationsEnabled;
use App\Services\DocxImportTemplate;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Cek Koneksi: measured from a slow device, also before login.
Route::get('cek-koneksi', [ConnectionCheckController::class, 'page'])->name('connection-check');
Route::get('cek-koneksi/ping', [ConnectionCheckController::class, 'ping'])->middleware('throttle:connection-check-ping')->name('connection-check.ping');
Route::get('cek-koneksi/unduh', [ConnectionCheckController::class, 'download'])->middleware('throttle:connection-check-download')->name('connection-check.download');

// A page that stayed blank in a browser (see resources/views/app.blade.php), also before login. Sent
// by the page guard with sendBeacon, which cannot carry the CSRF token; it only adds to the report.
Route::post('galat-halaman', PageFailureController::class)
    ->middleware('throttle:page-failures')
    ->withoutMiddleware(ValidateCsrfToken::class)
    ->name('page-failures.store');

// Lisensi: the licence as the LICENSE file says it, open to everyone.
Route::get('lisensi', fn () => Inertia::render('License', [
    'licenseText' => (string) file_get_contents(base_path('LICENSE')),
]))->name('license');

// Kebijakan Privasi: open to everyone, also before signing in.
Route::get('privasi', fn () => Inertia::render('Privacy', [
    'contact' => config('app.privacy_contact') ?: null,
]))->name('privacy');

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', DashboardController::class)->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('help', function (Request $request, DocxImportTemplate $docxTemplate) {
        return Inertia::render('Help', [
            // The Word import guide is for the people who write exams.
            'docxImportGuide' => $request->user()->isStudent() ? null : $docxTemplate->guide(),
        ]);
    })->name('help');

    // Developer Support Routes
    Route::post('support/suggestion', [DeveloperSupportController::class, 'storeSuggestion'])->middleware('throttle:10,1')->name('support.suggestion');
    // Donations only simulate a payment: off unless DONATIONS_ENABLED is set.
    Route::post('support/donate', [DeveloperSupportController::class, 'storeDonation'])->middleware([EnsureDonationsEnabled::class, 'throttle:10,1'])->name('support.donate');
    Route::post('support/donate/{donation}/confirm', [DeveloperSupportController::class, 'confirmDonation'])->middleware([EnsureDonationsEnabled::class, 'throttle:20,1'])->name('support.confirm-donation');
    Route::get('support/stats', [DeveloperSupportController::class, 'getStats'])->middleware('throttle:30,1')->name('support.stats');
    Route::post('support/withdraw', [DeveloperSupportController::class, 'storeWithdrawal'])->middleware([EnsureDonationsEnabled::class, 'throttle:5,1'])->name('support.withdraw');

    // Forms, Folders & Question Management (Teacher & Admin Only)
    Route::middleware('can:teacher-or-admin')->group(function (): void {
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
        Route::post('forms/{quizForm}/collaborators', [QuizFormCollaboratorController::class, 'store'])->name('forms.collaborators.store');
        Route::delete('forms/{quizForm}/collaborators/{user}', [QuizFormCollaboratorController::class, 'destroy'])->name('forms.collaborators.destroy');
        Route::get('forms/{quizForm}/responses/export', [QuizResponseExportController::class, 'export'])->name('forms.responses.export');
        Route::get('forms/{quizForm}/filter-cerdas', SmartFilterController::class)->name('forms.smart-filter');
        Route::get('forms/{quizForm}/progress', ExamProgressController::class)->name('forms.progress');
        Route::get('forms/{quizForm}/exam-token', ExamTokenController::class)->name('forms.exam-token');
        Route::get('forms/{quizForm}/class-map', ClassMapController::class)->name('forms.class-map');
        Route::get('forms/{quizForm}/grading', [GradingController::class, 'index'])->name('forms.grading.index');
        Route::patch('forms/{quizForm}/responses/{quizResponse}/grade', [GradingController::class, 'update'])->name('forms.grading.update');
        Route::get('forms/{quizForm}/answer-files/{answerFile}', [AnswerFileController::class, 'show'])->name('forms.answer-files.show');
        Route::post('forms/{quizForm}/follow-ups', [FollowUpExamController::class, 'store'])->name('forms.follow-ups.store');
        Route::get('questions/import/template', [QuestionImportController::class, 'template'])->name('questions.import.template');
        Route::post('questions/import', [QuestionImportController::class, 'import'])->name('questions.import');
        Route::post('questions/import-examview', [QuestionImportController::class, 'importExamView'])->name('questions.import.examview');
        Route::get('bank-soal', [QuestionBankController::class, 'page'])->name('question-bank.page');
        Route::get('add-ons', [AddonController::class, 'index'])->name('addons.index');
        Route::post('add-ons/{addon}', [AddonController::class, 'install'])->name('addons.install');
        Route::delete('add-ons/{addon}', [AddonController::class, 'uninstall'])->name('addons.uninstall');
        Route::get('question-bank', [QuestionBankController::class, 'index'])->name('question-bank.index');
        Route::post('question-bank/questions', [QuestionBankController::class, 'questions'])->name('question-bank.questions');
        Route::patch('question-bank/labels', [QuestionBankController::class, 'labels'])->name('question-bank.labels');
        Route::patch('question-bank/archive', [QuestionBankController::class, 'archive'])->name('question-bank.archive');
        Route::post('question-bank/exam', [QuestionBankController::class, 'createExam'])->name('question-bank.exam');
        Route::patch('forms/{quizForm}/questions/{questionId}/labels', [QuestionBankController::class, 'questionLabels'])->name('forms.questions.labels');
        Route::post('forms/{quizForm}/analysis/apply-difficulty', [QuestionBankController::class, 'applyAnalysedDifficulty'])->name('forms.analysis.apply-difficulty');

        // Laporan: logins and exam participation with device, browser and time
        Route::get('reports', [ActivityReportController::class, 'index'])->name('reports.index');
        Route::get('reports/export', [ActivityReportController::class, 'export'])->name('reports.export');

        // Rekap Kuis add-on: every quiz of the teacher in one table, by class
        Route::get('rekap-kuis', [QuizRecapController::class, 'index'])->name('quiz-recap.index');
        Route::get('rekap-kuis/unduh', [QuizRecapController::class, 'export'])->name('quiz-recap.export');

        // Unlock Request Admin / Teacher Routes
        Route::get('forms/{quizForm}/unlock-requests', [UnlockRequestController::class, 'index'])->name('forms.unlock-requests.index');
        Route::post('unlock-requests/{unlockRequest}/approve', [UnlockRequestController::class, 'approve'])->name('forms.unlock-requests.approve');
        Route::post('forms/{quizForm}/sessions/unlock-all', [UnlockRequestController::class, 'unlockAll'])->name('forms.sessions.unlock-all');
        Route::post('forms/{quizForm}/sessions/{quizSession}/unlock', [UnlockRequestController::class, 'unlockSession'])->name('forms.sessions.unlock');

        // Cohort Management Routes
        Route::get('cohorts', [CohortController::class, 'index'])->name('cohorts.index');
        Route::post('cohorts', [CohortController::class, 'store'])->name('cohorts.store');
        Route::post('cohorts/sync-from-classes', [CohortController::class, 'syncFromClasses'])->name('cohorts.sync-from-classes');
        Route::get('cohorts/{cohort}', [CohortController::class, 'show'])->name('cohorts.show');
        Route::put('cohorts/{cohort}', [CohortController::class, 'update'])->name('cohorts.update');
        Route::delete('cohorts/{cohort}', [CohortController::class, 'destroy'])->name('cohorts.destroy');
        Route::post('cohorts/{cohort}/members', [CohortController::class, 'addMembers'])->name('cohorts.members.add');
        Route::delete('cohorts/{cohort}/members/{user}', [CohortController::class, 'removeMember'])->name('cohorts.members.remove');

        // Student Management & Import Routes
        Route::get('students', [StudentController::class, 'index'])->name('students.index');
        Route::post('students', [StudentController::class, 'store'])->name('students.store');
        Route::put('students/{student}', [StudentController::class, 'update'])->name('students.update');
        Route::delete('students/{student}', [StudentController::class, 'destroy'])->name('students.destroy');
        Route::post('students/import', [StudentController::class, 'import'])->name('students.import');
        Route::get('students/template', [StudentController::class, 'downloadTemplate'])->name('students.template');
        Route::post('students/{student}/reset-password', [StudentController::class, 'resetPassword'])->name('students.reset-password');
        Route::post('students/{student}/change-password', [StudentController::class, 'changePassword'])->name('students.change-password');
    });

    // User Management (Admin Only)
    Route::middleware('can:admin-only')->group(function (): void {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        Route::patch('users/{user}/role', [UserController::class, 'updateRole'])->name('users.update-role');
        Route::post('users/{user}/change-password', [UserController::class, 'changePassword'])->name('users.change-password');
        Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
        Route::post('users/import-students', [UserController::class, 'importStudents'])->name('users.import-students');
        Route::get('users/student-template', [UserController::class, 'downloadStudentTemplate'])->name('users.student-template');
    });
});

Route::get('forms/{quizForm:slug}', [QuizResponseController::class, 'show'])->name('forms.public');
Route::post('forms/{quizForm:slug}/responses', [QuizResponseController::class, 'store'])->middleware('throttle:quiz-submissions')->name('forms.responses.store');
Route::post('forms/{quizForm:slug}/start', [QuizResponseController::class, 'start'])->middleware('throttle:quiz-activity')->name('forms.responses.start');
Route::post('forms/{quizForm:slug}/draft', [QuizResponseController::class, 'saveDraft'])->middleware('throttle:quiz-activity')->name('forms.responses.draft');
Route::post('forms/{quizForm:slug}/answer-files', [AnswerFileController::class, 'store'])->middleware('throttle:quiz-activity')->name('forms.answer-files.store');
Route::get('forms/{quizForm:slug}/hasil', [QuizResultController::class, 'show'])->name('forms.results');
Route::post('forms/{quizForm:slug}/lock', [QuizResponseController::class, 'lockSession'])->middleware('throttle:quiz-activity')->name('forms.responses.lock');
Route::get('forms/{quizForm:slug}/status', [QuizResponseController::class, 'examStatus'])->middleware('throttle:quiz-activity')->name('forms.responses.status');
Route::post('forms/{quizForm:slug}/unlock-requests', [UnlockRequestController::class, 'store'])->middleware('throttle:quiz-unlock')->name('forms.public.unlock-requests.store');
Route::post('forms/{quizForm:slug}/unlock', [UnlockRequestController::class, 'verify'])->middleware('throttle:quiz-unlock')->name('forms.public.unlock-verify');
Route::get('forms/{quizForm:slug}/unlock-requests/status/{identifier}', [UnlockRequestController::class, 'status'])->middleware('throttle:quiz-activity')->name('forms.public.unlock-requests.status');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
