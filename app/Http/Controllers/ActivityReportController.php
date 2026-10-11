<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Services\ActivityReport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ActivityReportController extends Controller
{
    /**
     * The Laporan page: who logged in or took an exam, with device, browser and time.
     */
    public function index(Request $request, ActivityReport $report): Response
    {
        $viewer = $request->user();
        $filters = $report->filters($request, $viewer);
        $page = fn () => $report->paginate($viewer, $filters);

        return Inertia::render('Reports/Activity', [
            'filters' => $filters,
            'activities' => function () use ($page): array {
                $activities = $page();

                return [
                    'rows' => $activities->items(),
                    'currentPage' => $activities->currentPage(),
                    'lastPage' => $activities->lastPage(),
                    'total' => $activities->total(),
                    'from' => $activities->firstItem(),
                    'to' => $activities->lastItem(),
                    'previousUrl' => $activities->previousPageUrl(),
                    'nextUrl' => $activities->nextPageUrl(),
                ];
            },
            'summary' => fn () => $report->summary($viewer, $filters),
            'forms' => fn () => $report->forms($viewer),
            'eventOptions' => collect(ActivityLog::EVENT_LABELS)
                ->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])
                ->values()
                ->all(),
            'roleOptions' => $viewer->isAdmin()
                ? collect(ActivityReport::ROLE_LABELS)->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])->values()->all()
                : null,
            'today' => now()->setTimezone((string) config('app.display_timezone', config('app.timezone')))->toDateString(),
            'exportUrl' => route('reports.export', array_filter($filters, fn ($value) => $value !== null && $value !== '')),
        ]);
    }

    /**
     * Download the activities matching the filters as an Excel workbook.
     */
    public function export(Request $request, ActivityReport $report): BinaryFileResponse
    {
        $viewer = $request->user();
        $filters = $report->filters($request, $viewer);

        $path = tempnam(sys_get_temp_dir(), 'alsen-activity-');
        $report->save($viewer, $filters, $path);

        return response()
            ->download($path, $report->filename($filters), [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'no-store, max-age=0',
            ])
            ->deleteFileAfterSend();
    }
}
