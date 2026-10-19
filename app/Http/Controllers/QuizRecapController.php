<?php

namespace App\Http\Controllers;

use App\Actions\SubmitExpiredAttempts;
use App\Services\Addons;
use App\Services\QuizRecap;
use App\Support\Addon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Rekap Kuis add-on: the teacher's grades of all their quizzes in one table, by class, and as
 * an Excel workbook. Only there while the teacher has the add-on installed.
 */
class QuizRecapController extends Controller
{
    public function index(Request $request, Addons $addons, QuizRecap $recap, SubmitExpiredAttempts $submitExpiredAttempts): Response
    {
        $this->ensureInstalled($request, $addons);
        $submitExpiredAttempts->handleQuietly();
        $kelas = $request->string('kelas')->trim()->toString() ?: null;
        $data = $recap->build($request->user(), $kelas);

        return Inertia::render('QuizRecap/Index', [
            ...$data,
            'exportUrl' => route('quiz-recap.export', array_filter(['kelas' => $data['kelas']])),
        ]);
    }

    public function export(Request $request, Addons $addons, QuizRecap $recap, SubmitExpiredAttempts $submitExpiredAttempts): BinaryFileResponse
    {
        $this->ensureInstalled($request, $addons);
        $submitExpiredAttempts->handleQuietly();
        $kelas = $request->string('kelas')->trim()->toString() ?: null;

        $path = tempnam(sys_get_temp_dir(), 'falcon-recap-');
        $filteredBy = $recap->save($request->user(), $kelas, $path);

        return response()
            ->download($path, $recap->filename($filteredBy), [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'no-store, max-age=0',
            ])
            ->deleteFileAfterSend();
    }

    /**
     * Without the add-on the page does not exist for the teacher.
     */
    protected function ensureInstalled(Request $request, Addons $addons): void
    {
        abort_unless($addons->isInstalledFor(Addon::QuizRecap, $request->user()), 404);
    }
}
