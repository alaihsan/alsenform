<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\ServerError;
use App\Services\ActivityReport;
use App\Support\DisplayTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Galat Server, for admins: every fault of the server once, with how often and when it happened,
 * where in the code it comes from, and the people and devices it happened to. A reference code a
 * student read from an error page ("ERR-7K3QXA") finds its fault.
 */
class ServerErrorController extends Controller
{
    public const PER_PAGE = 30;

    /**
     * @var list<string>
     */
    public const STATUSES = ['open', 'resolved', 'all'];

    public function index(Request $request): Response
    {
        $status = in_array($request->query('status'), self::STATUSES, true) ? (string) $request->query('status') : 'open';
        $search = Str::limit(trim((string) $request->query('search', '')), 100, '');

        return Inertia::render('Reports/ServerErrors', [
            'filters' => ['status' => $status, 'search' => $search],
            'errors' => function () use ($status, $search): array {
                $errors = ServerError::query()
                    ->with('lastUser:id,name')
                    ->when($status === 'open', fn (Builder $query) => $query->whereNull('resolved_at'))
                    ->when($status === 'resolved', fn (Builder $query) => $query->whereNotNull('resolved_at'))
                    ->when($search !== '', fn (Builder $query) => $this->whereSearch($query, $search))
                    ->orderByDesc('last_seen_at')
                    ->orderByDesc('id')
                    ->paginate(self::PER_PAGE)
                    ->withQueryString();

                return [
                    'rows' => collect($errors->items())->map(fn (ServerError $serverError): array => $this->row($serverError))->all(),
                    'total' => $errors->total(),
                    'previousUrl' => $errors->previousPageUrl(),
                    'nextUrl' => $errors->nextPageUrl(),
                ];
            },
            'summary' => fn (): array => [
                'open' => ServerError::query()->whereNull('resolved_at')->count(),
                'lastHour' => ActivityLog::query()->where('event', ActivityLog::SERVER_ERROR)->where('created_at', '>=', now()->subHour())->count(),
                'lastDay' => ActivityLog::query()->where('event', ActivityLog::SERVER_ERROR)->where('created_at', '>=', now()->subDay())->count(),
            ],
        ]);
    }

    /**
     * The latest occurrences of a fault: when, to whom, on which device and page.
     */
    public function show(ServerError $serverError, ActivityReport $report, Request $request): JsonResponse
    {
        $occurrences = ActivityLog::query()
            ->where('event', ActivityLog::SERVER_ERROR)
            // Compared as text: PostgreSQL reads a JSON value with ->> as text.
            ->where('meta->server_error_id', (string) $serverError->id)
            ->with(['user:id,name,email,nis,nip,kelas,role,is_admin', 'quizForm:id,title,slug,user_id,deleted_at'])
            ->latest('created_at')
            ->latest('id')
            ->limit(20)
            ->get();

        return response()->json([
            'occurrences' => $occurrences->map(fn (ActivityLog $log): array => [
                ...$report->row($log, $request->user()),
                'reference' => $log->meta['reference'] ?? null,
            ])->values()->all(),
        ]);
    }

    /**
     * Mark a fault as solved, or open it again. A solved fault that happens again opens by itself.
     */
    public function update(Request $request, ServerError $serverError): RedirectResponse
    {
        $validated = $request->validate(['resolved' => ['required', 'boolean']]);

        $serverError->update(['resolved_at' => $validated['resolved'] ? now() : null]);

        return back();
    }

    /**
     * A reference code finds the fault it belongs to; other words search the error itself.
     *
     * @param  Builder<ServerError>  $query
     * @return Builder<ServerError>
     */
    protected function whereSearch(Builder $query, string $search): Builder
    {
        if (preg_match('/^ERR-[A-Z0-9]{6}$/i', $search)) {
            $reference = strtoupper($search);

            return $query->where(fn (Builder $matches) => $matches
                ->where('last_reference', $reference)
                ->orWhereIn('id', ActivityLog::query()
                    ->where('event', ActivityLog::SERVER_ERROR)
                    ->where('meta->reference', $reference)
                    ->pluck('meta')
                    ->map(fn (mixed $meta): int => (int) (is_array($meta) ? ($meta['server_error_id'] ?? 0) : 0))
                    ->filter()
                    ->all()));
        }

        return $query->where(fn (Builder $matches) => $matches
            ->whereLike('message', "%{$search}%")
            ->orWhereLike('exception', "%{$search}%")
            ->orWhereLike('location', "%{$search}%")
            ->orWhereLike('last_url', "%{$search}%"));
    }

    /**
     * @return array<string, mixed>
     */
    protected function row(ServerError $serverError): array
    {
        return [
            'id' => $serverError->id,
            'exception' => class_basename($serverError->exception),
            'exceptionClass' => $serverError->exception,
            'message' => $serverError->message,
            'location' => $serverError->location,
            'occurrences' => $serverError->occurrences,
            'firstSeen' => DisplayTime::format($serverError->created_at, 'd M Y H:i'),
            'lastSeen' => DisplayTime::format($serverError->last_seen_at, 'd M Y H:i:s'),
            'lastSeenAgo' => DisplayTime::ago($serverError->last_seen_at),
            'lastReference' => $serverError->last_reference,
            'lastRequest' => trim($serverError->last_method.' '.$serverError->last_url) ?: null,
            'lastUser' => $serverError->lastUser?->name,
            'isResolved' => $serverError->resolved_at !== null,
            'resolvedAt' => DisplayTime::format($serverError->resolved_at, 'd M Y H:i'),
            'occurrencesUrl' => route('server-errors.show', $serverError),
            'updateUrl' => route('server-errors.update', $serverError),
        ];
    }
}
