<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePageFailureRequest;
use App\Models\ActivityLog;
use App\Models\QuizForm;
use App\Support\ActivityRecorder;
use App\Support\PageFailure;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * A page that stayed blank in a browser, reported by the page guard so the activity report
 * (Laporan) shows who could not open which page, on which device and browser, and why.
 */
class PageFailureController extends Controller
{
    public function __invoke(StorePageFailureRequest $request, ActivityRecorder $recorder): Response
    {
        $failure = PageFailure::from($request->string('code')->toString());
        $path = Str::limit($request->string('path')->toString(), 300, '');

        $recorder->record(
            ActivityLog::PAGE_FAILED,
            $request,
            user: $request->user(),
            quizForm: $this->examOf($path),
            respondentIdentifier: ! $request->user() && is_string($request->cookie('alsen_resp_id')) ? $request->cookie('alsen_resp_id') : null,
            meta: array_filter([
                'code' => $failure->value,
                'message' => Str::limit($request->string('message')->squish()->toString(), 300, ''),
                'path' => $path,
                'asset' => Str::limit($request->string('asset')->toString(), 300, ''),
            ]),
        );

        return response()->noContent();
    }

    /**
     * The exam of a student's exam page (/forms/{slug}), so its teacher sees the failure too.
     */
    protected function examOf(string $path): ?QuizForm
    {
        if (! preg_match('#^/forms/([A-Za-z0-9_-]+)/?(?:[?\#].*)?$#', $path, $matches)) {
            return null;
        }

        return QuizForm::query()->where('slug', $matches[1])->first();
    }
}
