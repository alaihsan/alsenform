<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\QuizForm;
use App\Models\User;
use App\Support\HtmlToPlainText;
use App\Support\XlsxWriter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Throwable;

/**
 * The activity report of the Laporan menu: logins, logouts and exam participation with the
 * device, browser and time. Admins see everything; teachers see their students' logins and
 * the activity on the exams they own or help edit.
 */
class ActivityReport
{
    public const PER_PAGE = 50;

    /** Rows written to the Excel file at most, so a very long period cannot exhaust memory. */
    public const EXPORT_LIMIT = 20000;

    /**
     * @var array<string, string>
     */
    public const ROLE_LABELS = [
        'siswa' => 'Siswa',
        'guru' => 'Guru',
        'admin' => 'Admin',
        'tamu' => 'Tamu',
    ];

    /**
     * IDs of the exams each viewer may edit, as array keys.
     *
     * @var array<int, array<int, int>>
     */
    protected array $editableFormIds = [];

    public function __construct(protected HtmlToPlainText $plainText = new HtmlToPlainText) {}

    /**
     * The filters of the query string, made valid. The period defaults to today.
     *
     * @return array{from: string, to: string, event: string|null, form: int|null, role: string|null, search: string}
     */
    public function filters(Request $request, User $viewer): array
    {
        $today = $this->now()->toDateString();
        $from = $this->validDate($request->query('from')) ?? $today;
        $to = $this->validDate($request->query('to')) ?? $from;

        if ($to < $from) {
            [$from, $to] = [$to, $from];
        }

        $event = $request->query('event');
        $role = $request->query('role');
        $form = $request->query('form');

        return [
            'from' => $from,
            'to' => $to,
            'event' => is_string($event) && array_key_exists($event, ActivityLog::EVENT_LABELS) ? $event : null,
            'form' => is_numeric($form) && (int) $form > 0 ? (int) $form : null,
            // Teachers only see students (and their own account), so the role filter is for admins.
            'role' => $viewer->isAdmin() && is_string($role) && array_key_exists($role, self::ROLE_LABELS) ? $role : null,
            'search' => Str::limit(trim((string) $request->query('search', '')), 100, ''),
        ];
    }

    /**
     * The activities matching the filters, newest first.
     *
     * @param  array{from: string, to: string, event: string|null, form: int|null, role: string|null, search: string}  $filters
     * @return Builder<ActivityLog>
     */
    public function query(User $viewer, array $filters, bool $withEvent = true): Builder
    {
        $timezone = $this->timezone();

        return $this->visibleTo(ActivityLog::query(), $viewer)
            ->whereBetween('activity_logs.created_at', [
                Carbon::parse($filters['from'], $timezone)->startOfDay()->utc(),
                Carbon::parse($filters['to'], $timezone)->endOfDay()->utc(),
            ])
            ->when($withEvent && $filters['event'], fn (Builder $query) => $query->where('event', $filters['event']))
            ->when($filters['form'], fn (Builder $query) => $query->where('quiz_form_id', $filters['form']))
            ->when($filters['role'], fn (Builder $query) => $this->whereRole($query, (string) $filters['role']))
            ->when($filters['search'] !== '', fn (Builder $query) => $this->whereSearch($query, $filters['search']))
            ->orderByDesc('activity_logs.created_at')
            ->orderByDesc('activity_logs.id');
    }

    /**
     * @param  array{from: string, to: string, event: string|null, form: int|null, role: string|null, search: string}  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginate(User $viewer, array $filters): LengthAwarePaginator
    {
        return $this->query($viewer, $filters)
            ->with(['user:id,name,email,nis,nip,kelas,role,is_admin', 'quizForm:id,title,slug,user_id,deleted_at'])
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (ActivityLog $log): array => $this->row($log, $viewer));
    }

    /**
     * Totals of the period for every kind of activity (the event filter is ignored, so the
     * tiles always show the whole picture).
     *
     * @param  array{from: string, to: string, event: string|null, form: int|null, role: string|null, search: string}  $filters
     * @return array{logins: int, loginUsers: int, examStarted: int, examSubmitted: int, people: array<string, int>}
     */
    public function summary(User $viewer, array $filters): array
    {
        $byEvent = $this->query($viewer, $filters, withEvent: false)
            ->reorder()
            ->selectRaw('event, COUNT(*) AS total, COUNT(DISTINCT user_id) AS users')
            ->groupBy('event')
            ->get()
            ->keyBy('event');

        // People (accounts, plus guests by their device cookie) per kind of device. Plain SQL that
        // PostgreSQL, MySQL and SQLite all accept.
        $people = $this->query($viewer, $filters, withEvent: false)
            ->reorder()
            ->selectRaw('device_type, COUNT(DISTINCT user_id) + COUNT(DISTINCT CASE WHEN user_id IS NULL THEN respondent_identifier END) AS total')
            ->groupBy('device_type')
            ->pluck('total', 'device_type');

        return [
            'logins' => (int) ($byEvent[ActivityLog::LOGIN]->total ?? 0),
            'loginUsers' => (int) ($byEvent[ActivityLog::LOGIN]->users ?? 0),
            'examStarted' => (int) ($byEvent[ActivityLog::EXAM_STARTED]->total ?? 0),
            'examSubmitted' => (int) ($byEvent[ActivityLog::EXAM_SUBMITTED]->total ?? 0),
            'people' => collect(array_keys(ActivityLog::DEVICE_TYPE_LABELS))
                ->mapWithKeys(fn (string $type): array => [$type => (int) ($people[$type] ?? 0)])
                ->all(),
        ];
    }

    /**
     * The exams that appear in the activities this viewer may see, for the exam filter.
     *
     * @return list<array{id: int, title: string}>
     */
    public function forms(User $viewer): array
    {
        $formIds = $this->visibleTo(ActivityLog::query(), $viewer)
            ->whereNotNull('quiz_form_id')
            ->select('quiz_form_id');

        return QuizForm::withTrashed()
            ->whereIn('id', $formIds)
            ->orderBy('title')
            ->get(['id', 'title'])
            ->map(fn (QuizForm $form): array => ['id' => $form->id, 'title' => $this->title($form)])
            ->values()
            ->all();
    }

    /**
     * One activity as shown on the page.
     *
     * @return array<string, mixed>
     */
    public function row(ActivityLog $log, User $viewer): array
    {
        $time = $log->created_at->copy()->setTimezone($this->timezone())->locale('id');
        $user = $log->user;
        $form = $log->quizForm;

        return [
            'id' => $log->id,
            'event' => $log->event,
            'eventLabel' => ActivityLog::EVENT_LABELS[$log->event] ?? $log->event,
            'date' => $time->translatedFormat('D, d M Y'),
            'time' => $time->format('H:i:s'),
            'timezone' => $time->format('T'),
            'person' => [
                'name' => $user?->name ?? ($log->respondent_name ?: ($log->respondent_email ?: 'Peserta tamu')),
                'identifier' => $user ? ($user->nis ?: $user->nip ?: $user->email) : ($log->respondent_name ? $log->respondent_email : null),
                'role' => self::ROLE_LABELS[$this->role($user)],
                'kelas' => $user?->kelas ?? $log->respondent_class,
            ],
            'form' => $form ? [
                'title' => $this->title($form),
                'url' => ! $form->trashed() && $this->canEdit($form, $viewer) ? route('forms.edit', ['quizForm' => $form->slug]) : null,
            ] : null,
            'deviceType' => $log->device_type,
            'deviceTypeLabel' => ActivityLog::DEVICE_TYPE_LABELS[$log->device_type] ?? ActivityLog::DEVICE_TYPE_LABELS['unknown'],
            'device' => $log->device,
            'os' => $log->os,
            'browser' => $log->browser,
            'ip' => $log->ip_address,
            'userAgent' => $log->user_agent,
            'note' => ($log->meta['timeout'] ?? false) ? 'Waktu habis' : null,
        ];
    }

    public function filename(array $filters): string
    {
        $period = $filters['from'] === $filters['to'] ? $filters['from'] : "{$filters['from']}_sd_{$filters['to']}";

        return 'laporan_aktivitas_'.str_replace('-', '', $period).'.xlsx';
    }

    /**
     * Write the activities matching the filters to an Excel workbook.
     *
     * @param  array{from: string, to: string, event: string|null, form: int|null, role: string|null, search: string}  $filters
     */
    public function save(User $viewer, array $filters, string $path): void
    {
        $total = $this->query($viewer, $filters)->count();
        $logs = $this->query($viewer, $filters)
            ->with(['user:id,name,email,nis,nip,kelas,role,is_admin', 'quizForm:id,title,slug,user_id,deleted_at'])
            ->limit(self::EXPORT_LIMIT)
            ->get();

        $headers = ['No', 'Tanggal', 'Jam', 'Aktivitas', 'Nama', 'NIS / NIP / Email', 'Peran', 'Kelas', 'Ujian', 'Jenis Perangkat', 'Perangkat', 'Sistem Operasi', 'Browser', 'Alamat IP', 'Keterangan', 'User Agent'];
        $lastColumn = XlsxWriter::columnLetter(count($headers));
        $timezone = $this->now()->format('T');

        $rows = [
            [XlsxWriter::cell('Laporan Aktivitas Pengguna (Login & Ujian)', 'title')],
            [XlsxWriter::cell(implode('   •   ', array_filter([
                'Periode '.$this->dateLabel($filters['from']).($filters['from'] === $filters['to'] ? '' : ' s.d. '.$this->dateLabel($filters['to'])),
                $filters['event'] ? ActivityLog::EVENT_LABELS[$filters['event']] : null,
                $filters['search'] !== '' ? 'Pencarian "'.$filters['search'].'"' : null,
                $total > self::EXPORT_LIMIT ? number_format(self::EXPORT_LIMIT, 0, ',', '.').' dari '.number_format($total, 0, ',', '.').' aktivitas terbaru' : $total.' aktivitas',
                'Diekspor '.$this->now()->format('d/m/Y H:i').' '.$timezone,
            ])), 'subtitle')],
            null,
            array_map(fn (string $title) => XlsxWriter::cell($title, 'header'), $headers),
        ];

        foreach ($logs as $index => $log) {
            $row = $this->row($log, $viewer);
            $rows[] = [
                XlsxWriter::cell($index + 1, 'integer'),
                XlsxWriter::cell($log->created_at->copy()->setTimezone($this->timezone())->format('d/m/Y'), 'textCenter'),
                XlsxWriter::cell($row['time'].' '.$row['timezone'], 'textCenter'),
                XlsxWriter::cell($row['eventLabel'], 'text'),
                XlsxWriter::cell($row['person']['name'], 'text'),
                XlsxWriter::cell($row['person']['identifier'], 'text'),
                XlsxWriter::cell($row['person']['role'], 'textCenter'),
                XlsxWriter::cell($row['person']['kelas'], 'textCenter'),
                XlsxWriter::cell($row['form']['title'] ?? null, 'text'),
                XlsxWriter::cell($row['deviceTypeLabel'], 'textCenter'),
                XlsxWriter::cell($row['device'], 'text'),
                XlsxWriter::cell($row['os'], 'text'),
                XlsxWriter::cell($row['browser'], 'text'),
                XlsxWriter::cell($row['ip'], 'textCenter'),
                XlsxWriter::cell($row['note'], 'text'),
                XlsxWriter::cell($row['userAgent'], 'text'),
            ];
        }

        (new XlsxWriter)
            ->addSheet('Aktivitas', $rows, [
                'columns' => [6, 12, 13, 16, 26, 22, 9, 10, 30, 12, 24, 16, 26, 15, 13, 60],
                'freeze' => 'A5',
                'autoFilter' => "A4:{$lastColumn}".max(4, count($rows)),
                'merge' => ["A1:{$lastColumn}1", "A2:{$lastColumn}2"],
                'rowHeights' => [1 => 28, 2 => 20],
                'repeatRows' => [4, 4],
                'landscape' => true,
            ])
            ->save($path, 'Laporan Aktivitas Pengguna');
    }

    /**
     * @param  Builder<ActivityLog>  $query
     * @return Builder<ActivityLog>
     */
    protected function visibleTo(Builder $query, User $viewer): Builder
    {
        if ($viewer->isAdmin()) {
            return $query;
        }

        $ownForms = QuizForm::withTrashed()
            ->select('id')
            ->where(fn (Builder $forms) => $forms
                ->where('user_id', $viewer->id)
                ->orWhereHas('collaborators', fn (Builder $collaborators) => $collaborators->where('users.id', $viewer->id)));

        return $query->where(fn (Builder $visible) => $visible
            ->where('activity_logs.user_id', $viewer->id)
            ->orWhereIn('activity_logs.quiz_form_id', $ownForms)
            ->orWhere(fn (Builder $logins) => $logins
                ->whereIn('event', [ActivityLog::LOGIN, ActivityLog::LOGOUT])
                ->whereHas('user', fn (Builder $users) => $this->whereStudent($users))));
    }

    /**
     * Student accounts, as User::isStudent() decides.
     *
     * @param  Builder<User>  $users
     * @return Builder<User>
     */
    protected function whereStudent(Builder $users): Builder
    {
        return $users->where('is_admin', false)->where(fn (Builder $students) => $students
            ->whereIn('role', ['siswa', 'murid'])
            ->orWhere(fn (Builder $withNis) => $withNis
                ->whereNotNull('nis')
                ->where('nis', '!=', '')
                ->where(fn (Builder $role) => $role->whereNull('role')->orWhereNotIn('role', ['guru', 'admin']))));
    }

    /**
     * Whether the viewer may open the exam in the editor (looked up once per viewer).
     */
    protected function canEdit(QuizForm $form, User $viewer): bool
    {
        if ($viewer->isAdmin()) {
            return true;
        }

        $this->editableFormIds[$viewer->id] ??= QuizForm::withTrashed()
            ->where('user_id', $viewer->id)
            ->orWhereHas('collaborators', fn (Builder $collaborators) => $collaborators->where('users.id', $viewer->id))
            ->pluck('id')
            ->flip()
            ->all();

        return isset($this->editableFormIds[$viewer->id][$form->id]);
    }

    /**
     * @param  Builder<ActivityLog>  $query
     * @return Builder<ActivityLog>
     */
    protected function whereRole(Builder $query, string $role): Builder
    {
        return match ($role) {
            'tamu' => $query->whereNull('activity_logs.user_id'),
            'admin' => $query->whereHas('user', fn (Builder $users) => $users->admins()),
            'guru' => $query->whereHas('user', fn (Builder $users) => $users->teachers()),
            default => $query->whereHas('user', fn (Builder $users) => $this->whereStudent($users)),
        };
    }

    /**
     * Name, NIS, NIP, email or IP address.
     *
     * @param  Builder<ActivityLog>  $query
     * @return Builder<ActivityLog>
     */
    protected function whereSearch(Builder $query, string $search): Builder
    {
        $term = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($search)).'%';

        return $query->where(fn (Builder $matches) => $matches
            ->whereRaw('LOWER(activity_logs.respondent_email) LIKE ?', [$term])
            ->orWhereRaw('LOWER(activity_logs.respondent_name) LIKE ?', [$term])
            ->orWhereRaw('LOWER(activity_logs.ip_address) LIKE ?', [$term])
            ->orWhereHas('user', fn (Builder $users) => $users->where(fn (Builder $fields) => $fields
                ->whereRaw('LOWER(name) LIKE ?', [$term])
                ->orWhereRaw('LOWER(email) LIKE ?', [$term])
                ->orWhereRaw('LOWER(nis) LIKE ?', [$term])
                ->orWhereRaw('LOWER(nip) LIKE ?', [$term]))));
    }

    protected function role(?User $user): string
    {
        return match (true) {
            $user === null => 'tamu',
            $user->isAdmin() => 'admin',
            $user->role === 'guru' => 'guru',
            default => 'siswa',
        };
    }

    protected function title(QuizForm $form): string
    {
        return Str::limit($this->plainText->convert((string) $form->title), 120) ?: 'Tanpa judul';
    }

    protected function validDate(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('!Y-m-d', $value, $this->timezone())->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    protected function dateLabel(string $date): string
    {
        return Carbon::parse($date, $this->timezone())->locale('id')->translatedFormat('d M Y');
    }

    protected function timezone(): string
    {
        return (string) config('app.display_timezone', config('app.timezone'));
    }

    protected function now(): Carbon
    {
        return now()->setTimezone($this->timezone());
    }
}
