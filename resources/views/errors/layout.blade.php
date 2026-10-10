{{--
    Error page for full page loads. Self-contained (inline CSS, no Vite assets) so it still renders
    when the build is missing or the database is down. Mirrors resources/js/pages/Error.vue, which
    is shown for visits inside the app; both get their content from App\Support\ErrorPage.
--}}
@php
    $status = isset($exception) && $exception instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
        ? $exception->getStatusCode()
        : ($status ?? 500);
    $page = app(\App\Support\ErrorPage::class)->props($status, $exception ?? null, request());
@endphp
<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex">
        <title>{{ $page['title'] }} - {{ config('app.name', 'Alsenform') }}</title>
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" media="print" onload="this.media='all'" />
        <style>
            *, *::before, *::after { box-sizing: border-box; }
            body {
                margin: 0;
                min-height: 100vh;
                background: #f5f4ff;
                color: #0f172a;
                font-family: 'Instrument Sans', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
                -webkit-font-smoothing: antialiased;
            }
            svg { display: block; flex-shrink: 0; }
            .header { position: sticky; top: 0; z-index: 30; border-bottom: 1px solid #f1f5f9; background: #fff; }
            .header-inner { display: flex; align-items: center; gap: .75rem; max-width: 72rem; height: 3.5rem; margin: 0 auto; padding: 0 1rem; }
            .brand { display: flex; align-items: center; gap: .625rem; }
            .brand-mark { display: grid; grid-template-columns: 1fr 1fr; gap: .25rem; width: 2rem; height: 2rem; padding: .375rem; border-radius: .75rem; background: #6366f1; box-shadow: 0 2px 0 #4338ca; }
            .brand-mark span { border-radius: .5rem; background: rgba(255, 255, 255, .95); }
            .brand-mark span:nth-child(2), .brand-mark span:nth-child(3) { background: rgba(255, 255, 255, .7); }
            .brand-name { display: block; font-size: 1rem; font-weight: 700; line-height: 1.25; letter-spacing: -.025em; }
            .brand-tagline { display: block; font-size: 9px; font-weight: 500; line-height: 1; color: #64748b; }
            .main { display: flex; justify-content: center; padding: 2.5rem 1rem; }
            .card { width: 100%; max-width: 36rem; padding: 1.5rem; border: 1px solid #eef2ff; border-radius: 2rem; background: #fff; box-shadow: 0 10px 0 #e0e7ff; text-align: center; }
            .icon { display: flex; align-items: center; justify-content: center; width: 4rem; height: 4rem; margin: 0 auto; border-radius: 1rem; }
            .icon svg { width: 2rem; height: 2rem; }
            .tone-indigo .icon { background: #eef2ff; color: #4f46e5; box-shadow: 0 5px 0 #c7d2fe; }
            .tone-amber .icon { background: #fffbeb; color: #d97706; box-shadow: 0 5px 0 #fde68a; }
            .tone-sky .icon { background: #f0f9ff; color: #0284c7; box-shadow: 0 5px 0 #bae6fd; }
            .tone-rose .icon { background: #fff1f2; color: #e11d48; box-shadow: 0 5px 0 #fecdd3; }
            .code { margin: 1.5rem 0 0; font-size: .75rem; font-weight: 900; letter-spacing: .2em; text-transform: uppercase; }
            .tone-indigo .code { color: #6366f1; }
            .tone-amber .code { color: #d97706; }
            .tone-sky .code { color: #0284c7; }
            .tone-rose .code { color: #e11d48; }
            h1 { margin: .5rem 0 0; font-size: 1.5rem; font-weight: 900; line-height: 1.25; color: #0f172a; }
            .message { margin: .75rem 0 0; font-size: .875rem; line-height: 1.625; color: #475569; }
            .detail { margin: 1.25rem 0 0; padding: .75rem 1rem; border: 1px solid #fde68a; border-radius: 1rem; background: #fffbeb; font-size: .875rem; font-weight: 600; line-height: 1.5; color: #78350f; text-align: left; }
            .detail strong { display: block; margin-bottom: .125rem; font-size: .6875rem; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; color: #b45309; }
            .hint { margin: 1rem 0 0; font-size: .8125rem; font-weight: 600; line-height: 1.5; color: #64748b; }
            .actions { display: flex; flex-direction: column-reverse; gap: .625rem; margin-top: 1.75rem; }
            .button { display: inline-flex; align-items: center; justify-content: center; gap: .5rem; padding: .75rem 1.25rem; border-radius: .75rem; font: inherit; font-size: .875rem; font-weight: 700; line-height: 1.25; text-decoration: none; cursor: pointer; transition: background-color .15s, transform .15s; }
            .button svg { width: 1rem; height: 1rem; }
            .button-primary { border: 0; background: #4f46e5; color: #fff; box-shadow: 0 4px 0 #3730a3; }
            .button-primary:hover { background: #4338ca; transform: translateY(-1px); }
            .button-secondary { border: 1px solid #e2e8f0; background: #fff; color: #334155; }
            .button-secondary:hover { background: #f8fafc; }
            .button:focus-visible { outline: 2px solid #6366f1; outline-offset: 2px; }
            .meta { margin: 1.5rem 0 0; font-size: 11px; color: #94a3b8; }
            @media (min-width: 640px) {
                .header-inner { padding: 0 1.5rem; }
                .brand-name { font-size: 1.125rem; }
                .brand-tagline { font-size: 10px; }
                .main { padding: 4rem 1.5rem; }
                .card { padding: 2.5rem; }
                h1 { font-size: 1.875rem; }
                .message { font-size: .9375rem; }
                .actions { flex-direction: row; justify-content: center; }
            }
        </style>
    </head>
    <body>
        <header class="header">
            <div class="header-inner">
                <div class="brand">
                    <div class="brand-mark" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
                    <div>
                        <span class="brand-name">Alsenform</span>
                        <span class="brand-tagline">CBT &amp; Exam Platform</span>
                    </div>
                </div>
            </div>
        </header>

        <main class="main">
            <section class="card tone-{{ $page['tone'] }}">
                <div class="icon">@include('errors.partials.icon', ['name' => $page['icon']])</div>
                <p class="code">Kode Error {{ $page['status'] }}</p>
                <h1>{{ $page['title'] }}</h1>
                <p class="message">{{ $page['message'] }}</p>

                @if ($page['detail'])
                    <p class="detail"><strong>Keterangan</strong>{{ $page['detail'] }}</p>
                @endif

                <p class="hint">{{ $page['hint'] }}</p>

                <div class="actions">
                    @if ($page['secondaryAction']['href'])
                        <a href="{{ $page['secondaryAction']['href'] }}" class="button button-secondary">
                            @include('errors.partials.icon', ['name' => $page['secondaryAction']['icon']])
                            {{ $page['secondaryAction']['label'] }}
                        </a>
                    @else
                        <button
                            type="button"
                            class="button button-secondary"
                            onclick="window.history.length > 1 ? window.history.back() : window.location.assign(@js($page['primaryAction']['href']))"
                        >
                            @include('errors.partials.icon', ['name' => $page['secondaryAction']['icon']])
                            {{ $page['secondaryAction']['label'] }}
                        </button>
                    @endif
                    <a href="{{ $page['primaryAction']['href'] }}" class="button button-primary">
                        @include('errors.partials.icon', ['name' => $page['primaryAction']['icon']])
                        {{ $page['primaryAction']['label'] }}
                    </a>
                </div>

                @if ($page['occurredAt'])
                    <p class="meta">Waktu kejadian: {{ $page['occurredAt'] }}</p>
                @endif
            </section>
        </main>
    </body>
</html>
