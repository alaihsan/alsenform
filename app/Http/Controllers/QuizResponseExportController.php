<?php

namespace App\Http\Controllers;

use App\Models\QuizForm;
use App\Services\QuizResultsReport;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class QuizResponseExportController extends Controller
{
    /**
     * Download the quiz results (grade recap and every student's answers) as an Excel workbook.
     */
    public function export(QuizForm $quizForm, QuizResultsReport $report): BinaryFileResponse
    {
        abort_unless($quizForm->canBeEditedBy(auth()->user()), 403);

        $path = tempnam(sys_get_temp_dir(), 'alsen-report-');
        $report->save($quizForm, $path);

        return response()
            ->download($path, $report->filename($quizForm), [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'no-store, max-age=0',
            ])
            ->deleteFileAfterSend();
    }
}
