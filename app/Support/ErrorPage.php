<?php

namespace App\Support;

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * The error pages of the application, in Indonesian and in the Alsenform style.
 *
 * Full page loads get the Blade views in resources/views/errors: they need no Vite assets, so
 * they still render when the build or the database is broken. Visits inside the app get the
 * "Error" Inertia page instead of Inertia's dialog with the raw error HTML. Both are filled
 * from props(), so every page says the same thing.
 */
class ErrorPage
{
    /**
     * Content per HTTP status. "action" picks the main button: home, retry (open the address
     * again), previous (back to the page the request came from) or login.
     *
     * @var array<int, array{title: string, message: string, hint: string, icon: string, tone: string, action: string, actionLabel?: string}>
     */
    protected const PAGES = [
        400 => [
            'title' => 'Permintaan Tidak Valid',
            'message' => 'Server tidak dapat memahami permintaan yang dikirim browser Anda.',
            'hint' => 'Muat ulang halaman, lalu coba lagi.',
            'icon' => 'circle-alert',
            'tone' => 'amber',
            'action' => 'retry',
        ],
        401 => [
            'title' => 'Silakan Masuk Terlebih Dahulu',
            'message' => 'Halaman ini hanya dapat dibuka setelah Anda masuk ke akun.',
            'hint' => 'Masuk dengan email, NIP, atau NIS Anda, lalu buka kembali halaman ini.',
            'icon' => 'log-in',
            'tone' => 'indigo',
            'action' => 'login',
        ],
        403 => [
            'title' => 'Akses Ditolak',
            'message' => 'Akun Anda tidak memiliki izin untuk membuka halaman ini atau melakukan aksi tersebut.',
            'hint' => 'Pastikan Anda masuk dengan akun yang benar. Jika memerlukan akses, hubungi guru atau administrator sekolah.',
            'icon' => 'shield-alert',
            'tone' => 'amber',
            'action' => 'home',
        ],
        404 => [
            'title' => 'Halaman Tidak Ditemukan',
            'message' => 'Alamat yang Anda buka tidak ada, sudah dihapus, atau ujiannya belum dipublikasikan.',
            'hint' => 'Periksa kembali alamat atau tautan yang dibagikan guru Anda.',
            'icon' => 'search-x',
            'tone' => 'indigo',
            'action' => 'home',
        ],
        405 => [
            'title' => 'Aksi Tidak Dapat Dilakukan',
            'message' => 'Halaman ini tidak dapat dibuka dengan cara tersebut, misalnya karena dimuat ulang tepat setelah mengirim formulir.',
            'hint' => 'Kembali ke halaman sebelumnya, lalu ulangi langkah Anda dari sana.',
            'icon' => 'ban',
            'tone' => 'amber',
            'action' => 'previous',
        ],
        408 => [
            'title' => 'Waktu Permintaan Habis',
            'message' => 'Server terlalu lama menunggu data dari perangkat Anda.',
            'hint' => 'Periksa koneksi Wi-Fi atau kabel LAN, lalu coba lagi.',
            'icon' => 'hourglass',
            'tone' => 'sky',
            'action' => 'retry',
        ],
        413 => [
            'title' => 'Berkas Terlalu Besar',
            'message' => 'Berkas atau data yang dikirim melebihi batas ukuran yang diizinkan server.',
            'hint' => 'Perkecil ukuran berkas, misalnya dengan mengompres gambar atau video, lalu unggah kembali.',
            'icon' => 'file-warning',
            'tone' => 'amber',
            'action' => 'previous',
        ],
        419 => [
            'title' => 'Sesi Halaman Telah Habis',
            'message' => 'Halaman ini terlalu lama dibiarkan terbuka sehingga sesi keamanannya berakhir.',
            'hint' => 'Muat ulang halaman, lalu ulangi langkah terakhir Anda.',
            'icon' => 'timer-reset',
            'tone' => 'sky',
            'action' => 'previous',
            'actionLabel' => 'Muat Ulang Halaman',
        ],
        429 => [
            'title' => 'Terlalu Banyak Permintaan',
            'message' => 'Anda melakukan terlalu banyak percobaan dalam waktu singkat.',
            'hint' => 'Tunggu sebentar, lalu coba lagi.',
            'icon' => 'hourglass',
            'tone' => 'sky',
            'action' => 'retry',
        ],
        500 => [
            'title' => 'Terjadi Kesalahan di Server',
            'message' => 'Maaf, server mengalami kendala saat memproses permintaan Anda.',
            'hint' => 'Coba lagi beberapa saat lagi. Jika terus terjadi, beri tahu guru atau administrator beserta waktu kejadian di bawah.',
            'icon' => 'server-crash',
            'tone' => 'rose',
            'action' => 'retry',
        ],
        503 => [
            'title' => 'Sedang Dalam Pemeliharaan',
            'message' => 'Aplikasi sedang diperbarui atau dirawat sehingga untuk sementara belum dapat digunakan.',
            'hint' => 'Silakan coba lagi dalam beberapa menit.',
            'icon' => 'wrench',
            'tone' => 'sky',
            'action' => 'retry',
        ],
    ];

    /**
     * Any other 4xx status.
     *
     * @var array{title: string, message: string, hint: string, icon: string, tone: string, action: string}
     */
    protected const CLIENT_ERROR = [
        'title' => 'Permintaan Tidak Dapat Diproses',
        'message' => 'Server tidak dapat memproses permintaan ini.',
        'hint' => 'Kembali ke halaman sebelumnya, lalu coba lagi.',
        'icon' => 'circle-alert',
        'tone' => 'amber',
        'action' => 'previous',
    ];

    /**
     * Any other 5xx status.
     *
     * @var array{title: string, message: string, hint: string, icon: string, tone: string, action: string}
     */
    protected const SERVER_ERROR = [
        'title' => 'Server Sedang Bermasalah',
        'message' => 'Server tidak dapat menyelesaikan permintaan Anda saat ini.',
        'hint' => 'Coba lagi beberapa saat lagi. Jika terus terjadi, beri tahu guru atau administrator beserta waktu kejadian di bawah.',
        'icon' => 'server-crash',
        'tone' => 'rose',
        'action' => 'retry',
    ];

    /**
     * The content of the error page for the given status.
     *
     * @return array{
     *     status: int,
     *     title: string,
     *     message: string,
     *     detail: string|null,
     *     hint: string,
     *     icon: string,
     *     tone: string,
     *     primaryAction: array{label: string, href: string, icon: string},
     *     secondaryAction: array{label: string, href: string|null, icon: string},
     *     occurredAt: string|null,
     * }
     */
    public function props(int $status, ?Throwable $exception, Request $request): array
    {
        $page = self::PAGES[$status] ?? ($status >= 500 ? self::SERVER_ERROR : self::CLIENT_ERROR);
        $retryAfter = $this->retryAfterSeconds($exception);
        [$primaryAction, $secondaryAction] = $this->actions($page, $request);

        return [
            'status' => $status,
            'title' => $page['title'],
            'message' => $page['message'],
            'detail' => $this->detail($exception),
            'hint' => $retryAfter !== null ? 'Silakan coba lagi dalam sekitar '.$this->duration($retryAfter).'.' : $page['hint'],
            'icon' => $page['icon'],
            'tone' => $page['tone'],
            'primaryAction' => $primaryAction,
            'secondaryAction' => $secondaryAction,
            // Lets the operator find the matching entry in storage/logs/laravel.log (same time zone).
            'occurredAt' => $status >= 500 && $status !== 503 ? now()->format('d/m/Y H:i:s T') : null,
        ];
    }

    /**
     * Show the Error page to Inertia visits instead of Inertia's dialog with the raw error HTML.
     * JSON answers (axios calls such as the exam autosave), redirects and the debug page of a
     * server error during development are left as they are.
     */
    public function respond(Response $response, Throwable $exception, Request $request): Response
    {
        $status = $response->getStatusCode();

        if (! $request->header('X-Inertia')
            || $status < 400
            || $response instanceof JsonResponse
            || $response->isRedirection()
            || ($status >= 500 && config('app.debug'))) {
            return $response;
        }

        // Errors raised before the web middleware ran (an unknown address, a missing record,
        // maintenance mode, an expired session) have no Inertia asset version yet. Without it,
        // the next visit would be answered with a full page reload.
        if (Inertia::getVersion() === '') {
            Inertia::version(fn (): ?string => app(HandleInertiaRequests::class)->version($request));
        }

        $errorResponse = Inertia::render('Error', $this->props($status, $exception, $request))
            ->toResponse($request)
            ->setStatusCode($status);

        if ($response->headers->has('Retry-After')) {
            $errorResponse->headers->set('Retry-After', $response->headers->get('Retry-After'));
        }

        return $errorResponse;
    }

    /**
     * The message given to abort() by the application, such as "Kuis sedang terkunci...".
     * Framework messages (English, sometimes naming a model or a route) and the message of an
     * exception that wraps another one are never shown.
     */
    protected function detail(?Throwable $exception): ?string
    {
        if (! $exception instanceof HttpException || $exception::class !== HttpException::class || $exception->getPrevious() !== null) {
            return null;
        }

        $message = trim($exception->getMessage());

        return $message === '' || in_array($message, Response::$statusTexts, true) ? null : $message;
    }

    protected function retryAfterSeconds(?Throwable $exception): ?int
    {
        $retryAfter = $exception instanceof HttpExceptionInterface ? ($exception->getHeaders()['Retry-After'] ?? null) : null;

        return is_numeric($retryAfter) && (int) $retryAfter > 0 ? (int) $retryAfter : null;
    }

    protected function duration(int $seconds): string
    {
        return $seconds < 60 ? "{$seconds} detik" : (int) ceil($seconds / 60).' menit';
    }

    /**
     * @param  array{action: string, actionLabel?: string}  $page
     * @return array{0: array{label: string, href: string, icon: string}, 1: array{label: string, href: string|null, icon: string}}
     */
    protected function actions(array $page, Request $request): array
    {
        $home = ['label' => 'Ke Beranda', 'href' => route('home'), 'icon' => 'house'];

        return match ($page['action']) {
            'login' => [['label' => 'Masuk', 'href' => route('login'), 'icon' => 'log-in'], $home],
            'retry' => [[
                'label' => 'Coba Lagi',
                'href' => $request->isMethod('GET') ? $request->fullUrl() : $this->previousUrl($request),
                'icon' => 'refresh-cw',
            ], $home],
            'previous' => [[
                'label' => $page['actionLabel'] ?? 'Kembali ke Halaman Sebelumnya',
                'href' => $this->previousUrl($request),
                'icon' => 'refresh-cw',
            ], $home],
            // No address: the page goes back in the browser history.
            default => [$home, ['label' => 'Kembali', 'href' => null, 'icon' => 'arrow-left']],
        };
    }

    /**
     * The page the request came from (a full reload also gives the browser a fresh CSRF token).
     */
    protected function previousUrl(Request $request): string
    {
        $referer = (string) $request->headers->get('referer', '');

        if ($referer !== '' && parse_url($referer, PHP_URL_HOST) === $request->getHost()) {
            return $referer;
        }

        return $request->isMethod('GET') ? $request->fullUrl() : route('home');
    }
}
