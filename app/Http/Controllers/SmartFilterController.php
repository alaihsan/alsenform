<?php

namespace App\Http\Controllers;

use App\Actions\SubmitExpiredAttempts;
use App\Models\QuizForm;
use App\Services\QuizResultsReport;
use App\Support\Addon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Filter Cerdas add-on: the results of an exam as one table (the Excel workbook's columns and
 * values), fetched only when the teacher opens the Excel mode on the Responses tab.
 */
class SmartFilterController extends Controller
{
    public function __invoke(Request $request, QuizForm $quizForm, QuizResultsReport $report, SubmitExpiredAttempts $submitExpiredAttempts): JsonResponse
    {
        abort_unless($quizForm->canBeEditedBy($request->user()), 403);
        abort_unless($quizForm->usesAddon(Addon::SmartFilter), 403, 'Add-on Filter Cerdas tidak aktif untuk ujian ini.');

        // Like the Excel download: attempts whose time ran out are handed in first.
        $submitExpiredAttempts->handleQuietly($quizForm);

        return response()
            ->json($report->grid($quizForm))
            ->header('Cache-Control', 'no-store, max-age=0');
    }
}
