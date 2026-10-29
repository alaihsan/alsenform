<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\QuizForm;
use App\Models\ServerError;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;
use WeakMap;

/**
 * Keeps the errors of the server where the admin sees them (Laporan → Galat Server) instead of
 * only in storage/logs/laravel.log: the same fault counted once with how often it happened, and
 * every occurrence in the activity report with its account and device. Each error gets a reference
 * code that its error page shows and the log line carries, so "ERR-7K3QXA" from a student leads to
 * the right entry.
 */
class ServerErrorRecorder
{
    /**
     * Letters and digits that cannot be mistaken for one another when read out or typed.
     */
    protected const ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    /**
     * @var WeakMap<Throwable, string>
     */
    protected WeakMap $references;

    protected ?string $lastReference = null;

    protected bool $isRecording = false;

    public function __construct(protected ActivityRecorder $activityRecorder = new ActivityRecorder)
    {
        $this->references = new WeakMap;
    }

    /**
     * The reference code of an error, the same however often it is asked for.
     */
    public function referenceFor(Throwable $exception): string
    {
        return $this->references[$exception] ??= 'ERR-'.implode('', array_map(
            fn (): string => self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)],
            range(1, 6),
        ));
    }

    /**
     * The reference of the latest error recorded during this request, for its error page.
     */
    public function lastReference(): ?string
    {
        return $this->lastReference;
    }

    /**
     * Record an error that is reported. Never throws: with the database down or not migrated yet,
     * the error is still written to laravel.log with its reference.
     */
    public function record(Throwable $exception): void
    {
        $reference = $this->referenceFor($exception);
        $this->lastReference = $reference;

        // A failure while recording an error is not recorded in turn.
        if ($this->isRecording) {
            return;
        }

        $this->isRecording = true;

        try {
            $request = app()->bound('request') ? app('request') : null;
            $isHttp = $request instanceof Request && ($request->route() !== null || ! app()->runningInConsole());
            $user = $isHttp ? $this->userOf($request) : null;
            $fingerprint = $this->fingerprint($exception);
            $now = now();

            ServerError::query()->insertOrIgnore([
                'fingerprint' => $fingerprint,
                'exception' => Str::limit($exception::class, 250, ''),
                'message' => $this->message($exception),
                'location' => $this->location($exception),
                'occurrences' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            ServerError::query()->toBase()->where('fingerprint', $fingerprint)->incrementEach(['occurrences' => 1], [
                'message' => $this->message($exception),
                'last_reference' => $reference,
                'last_method' => $isHttp ? $request->method() : 'CLI',
                'last_url' => $isHttp ? Str::limit($request->getRequestUri(), 2000, '') : $this->command(),
                'last_user_id' => $user?->id,
                'last_seen_at' => $now,
                // A fault marked as solved that comes back is open again.
                'resolved_at' => null,
                'updated_at' => $now,
            ]);

            if ($isHttp) {
                $quizForm = $request->route('quizForm');

                $this->activityRecorder->record(
                    ActivityLog::SERVER_ERROR,
                    $request,
                    $user,
                    $quizForm instanceof QuizForm ? $quizForm : null,
                    meta: [
                        'reference' => $reference,
                        'server_error_id' => ServerError::query()->where('fingerprint', $fingerprint)->value('id'),
                        'status' => $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500,
                        'method' => $request->method(),
                        'path' => Str::limit('/'.ltrim($request->path(), '/'), 255, ''),
                    ],
                );
            }
        } catch (Throwable) {
            // The database cannot take it right now: laravel.log has the error and its reference.
        } finally {
            $this->isRecording = false;
        }
    }

    /**
     * Errors of the same kind from the same place, whatever ids or values their message names,
     * are the same fault.
     */
    public function fingerprint(Throwable $exception): string
    {
        $message = (string) preg_replace(['/\d+/', "/'[^']*'/", '/"[^"]*"/'], ['#', "'?'", '"?"'], $exception->getMessage());

        return sha1(implode('|', [$exception::class, $this->location($exception), Str::limit($message, 300, '')]));
    }

    /**
     * Where in this application's own code the error comes from: the first place outside vendor/,
     * as "app/Http/Controllers/QuizResponseController.php:412".
     */
    public function location(Throwable $exception): string
    {
        $basePath = base_path().DIRECTORY_SEPARATOR;
        $frames = [['file' => $exception->getFile(), 'line' => $exception->getLine()], ...$exception->getTrace()];

        foreach ($frames as $frame) {
            $file = $frame['file'] ?? null;

            if (is_string($file) && str_starts_with($file, $basePath) && ! str_starts_with($file, $basePath.'vendor'.DIRECTORY_SEPARATOR)) {
                return Str::limit(substr($file, strlen($basePath)).':'.($frame['line'] ?? 0), 250, '');
            }
        }

        return Str::limit(basename($exception->getFile()).':'.$exception->getLine(), 250, '');
    }

    /**
     * The message, without passwords or tokens a database or service error may name.
     */
    protected function message(Throwable $exception): string
    {
        $message = (string) preg_replace('/\b(password|passwd|pwd|secret|token|key)=\S+/i', '$1=***', $exception->getMessage());

        return Str::limit($message !== '' ? $message : '(tanpa pesan)', 2000);
    }

    protected function userOf(Request $request): ?User
    {
        try {
            $user = $request->user();
        } catch (Throwable) {
            return null;
        }

        return $user instanceof User ? $user : null;
    }

    protected function command(): string
    {
        $arguments = array_slice(is_array($_SERVER['argv'] ?? null) ? $_SERVER['argv'] : [], 1, 1);

        return trim('php artisan '.implode(' ', $arguments));
    }
}
