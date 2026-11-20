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
        <title>{{ $page['title'] }} - Falcon Forms</title>
        <link rel="icon" href="/favicon.ico" sizes="32x32">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        {{-- Optional web font, fetched once the page has loaded so a missing internet connection never delays it. --}}
        <script>
            window.addEventListener('load', function () {
                var link = document.createElement('link');
                link.rel = 'stylesheet';
                link.href = 'https://fonts.bunny.net/css?family=instrument-sans:400,500,600';
                document.head.appendChild(link);
            });
        </script>
        {{-- The look chosen on this device (light, dark, warm), as on the app's own pages. --}}
        @include('partials.appearance-script')
        <style>
            :root {
                color-scheme: light;
                --page: #f5f4ff;
                --surface: #fff;
                --header-border: #f1f5f9;
                --card-border: #eef2ff;
                --card-lip: #e0e7ff;
                --text: #0f172a;
                --text-soft: #475569;
                --muted: #64748b;
                --faint: #94a3b8;
                --primary: #4f46e5;
                --primary-hover: #4338ca;
                --primary-lip: #3730a3;
                --focus: #6366f1;
                --secondary-border: #e2e8f0;
                --secondary-text: #334155;
                --secondary-hover: #f8fafc;
                --reference-bg: #f8fafc;
                --reference-border: #cbd5e1;
                --detail-bg: #fffbeb;
                --detail-border: #fde68a;
                --detail-text: #78350f;
                --detail-strong: #b45309;
                --indigo-bg: #eef2ff; --indigo-fg: #4f46e5; --indigo-lip: #c7d2fe; --indigo-code: #6366f1;
                --amber-bg: #fffbeb; --amber-fg: #d97706; --amber-lip: #fde68a;
                --sky-bg: #f0f9ff; --sky-fg: #0284c7; --sky-lip: #bae6fd;
                --rose-bg: #fff1f2; --rose-fg: #e11d48; --rose-lip: #fecdd3;
            }
            :root.dark {
                color-scheme: dark;
                --page: #111317;
                --surface: #191c22;
                --header-border: #252930;
                --card-border: #252930;
                --card-lip: #0b0d10;
                --text: #e9ecf1;
                --text-soft: #b3bac4;
                --muted: #9ba3af;
                --faint: #8a93a1;
                --focus: #818cf8;
                --secondary-border: #2f343c;
                --secondary-text: #c9cfd7;
                --secondary-hover: #1d2026;
                --reference-bg: #1d2026;
                --reference-border: #3c424c;
                --detail-bg: #2e2818;
                --detail-border: #5c4a1c;
                --detail-text: #fde68a;
                --detail-strong: #fbbf24;
                --indigo-bg: #282b4b; --indigo-fg: #818cf8; --indigo-lip: #2f3260; --indigo-code: #818cf8;
                --amber-bg: #33291a; --amber-fg: #fbbf24; --amber-lip: #4a3a1c;
                --sky-bg: #172b3a; --sky-fg: #38bdf8; --sky-lip: #1c3a50;
                --rose-bg: #3a1d26; --rose-fg: #fb7185; --rose-lip: #52253a;
            }
            :root[data-theme="warm"] {
                --page: #f3f1ea;
                --surface: #fdfcf8;
                --header-border: #efece3;
                --card-border: #efece3;
                --card-lip: #e4e0d5;
                --text: #24221e;
                --text-soft: #45413a;
                --muted: #716b5f;
                --faint: #9f998b;
                --primary: #b5583a;
                --primary-hover: #984a2e;
                --primary-lip: #7b3d27;
                --focus: #cc6b47;
                --secondary-border: #e4e0d5;
                --secondary-text: #45413a;
                --secondary-hover: #f6f4ee;
                --reference-bg: #f6f4ee;
                --reference-border: #cfc9bb;
                --indigo-bg: #fbf3ee; --indigo-fg: #b5583a; --indigo-lip: #efc7b3; --indigo-code: #cc6b47;
            }
            :root[data-theme="warm"].dark {
                --page: #262624;
                --surface: #2f2e2b;
                --header-border: #3c3b37;
                --card-border: #3c3b37;
                --card-lip: #1c1b19;
                --text: #f0eee7;
                --text-soft: #bfbbb1;
                --muted: #aaa69c;
                --faint: #98948b;
                --primary-lip: #643323;
                --focus: #da8463;
                --secondary-border: #46443f;
                --secondary-text: #d2cec5;
                --secondary-hover: #353431;
                --reference-bg: #353431;
                --reference-border: #55534d;
                --indigo-bg: #43362f; --indigo-fg: #da8463; --indigo-lip: #5e4033; --indigo-code: #da8463;
            }
            *, *::before, *::after { box-sizing: border-box; }
            body {
                margin: 0;
                min-height: 100vh;
                background: var(--page);
                color: var(--text);
                font-family: 'Instrument Sans', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
                -webkit-font-smoothing: antialiased;
            }
            svg { display: block; flex-shrink: 0; }
            .header { position: sticky; top: 0; z-index: 30; border-bottom: 1px solid var(--header-border); background: var(--surface); }
            .header-inner { display: flex; align-items: center; gap: .75rem; max-width: 72rem; height: 3.5rem; margin: 0 auto; padding: 0 1rem; }
            .brand { display: flex; align-items: center; gap: .625rem; }
            .brand-mark { display: block; flex-shrink: 0; width: 2rem; height: 2rem; }
            .brand-name { display: block; font-size: 1rem; font-weight: 700; line-height: 1.25; letter-spacing: -.025em; }
            .brand-tagline { display: block; font-size: 9px; font-weight: 500; line-height: 1; color: var(--muted); }
            .main { display: flex; justify-content: center; padding: 2.5rem 1rem; }
            .card { width: 100%; max-width: 36rem; padding: 1.5rem; border: 1px solid var(--card-border); border-radius: 2rem; background: var(--surface); box-shadow: 0 10px 0 var(--card-lip); text-align: center; }
            .icon { display: flex; align-items: center; justify-content: center; width: 4rem; height: 4rem; margin: 0 auto; border-radius: 1rem; }
            .icon svg { width: 2rem; height: 2rem; }
            .tone-indigo .icon { background: var(--indigo-bg); color: var(--indigo-fg); box-shadow: 0 5px 0 var(--indigo-lip); }
            .tone-amber .icon { background: var(--amber-bg); color: var(--amber-fg); box-shadow: 0 5px 0 var(--amber-lip); }
            .tone-sky .icon { background: var(--sky-bg); color: var(--sky-fg); box-shadow: 0 5px 0 var(--sky-lip); }
            .tone-rose .icon { background: var(--rose-bg); color: var(--rose-fg); box-shadow: 0 5px 0 var(--rose-lip); }
            .code { margin: 1.5rem 0 0; font-size: .75rem; font-weight: 900; letter-spacing: .2em; text-transform: uppercase; }
            .tone-indigo .code { color: var(--indigo-code); }
            .tone-amber .code { color: var(--amber-fg); }
            .tone-sky .code { color: var(--sky-fg); }
            .tone-rose .code { color: var(--rose-fg); }
            h1 { margin: .5rem 0 0; font-size: 1.5rem; font-weight: 900; line-height: 1.25; color: var(--text); }
            .message { margin: .75rem 0 0; font-size: .875rem; line-height: 1.625; color: var(--text-soft); }
            .detail { margin: 1.25rem 0 0; padding: .75rem 1rem; border: 1px solid var(--detail-border); border-radius: 1rem; background: var(--detail-bg); font-size: .875rem; font-weight: 600; line-height: 1.5; color: var(--detail-text); text-align: left; }
            .detail strong { display: block; margin-bottom: .125rem; font-size: .6875rem; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; color: var(--detail-strong); }
            .hint { margin: 1rem 0 0; font-size: .8125rem; font-weight: 600; line-height: 1.5; color: var(--muted); }
            .actions { display: flex; flex-direction: column-reverse; gap: .625rem; margin-top: 1.75rem; }
            .button { display: inline-flex; align-items: center; justify-content: center; gap: .5rem; padding: .75rem 1.25rem; border-radius: .75rem; font: inherit; font-size: .875rem; font-weight: 700; line-height: 1.25; text-decoration: none; cursor: pointer; transition: background-color .15s, transform .15s; }
            .button svg { width: 1rem; height: 1rem; }
            .button-primary { border: 0; background: var(--primary); color: #fff; box-shadow: 0 4px 0 var(--primary-lip); }
            .button-primary:hover { background: var(--primary-hover); transform: translateY(-1px); }
            .button-secondary { border: 1px solid var(--secondary-border); background: var(--surface); color: var(--secondary-text); }
            .button-secondary:hover { background: var(--secondary-hover); }
            .button:focus-visible { outline: 2px solid var(--focus); outline-offset: 2px; }
            .meta { margin: 1.5rem 0 0; font-size: 11px; color: var(--faint); }
            .reference { display: inline-block; margin: 1.5rem 0 0; padding: .375rem .875rem; border-radius: .75rem; background: var(--reference-bg); border: 1px dashed var(--reference-border); font-size: .75rem; color: var(--text-soft); }
            .reference strong { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: .875rem; letter-spacing: .05em; color: var(--text); user-select: all; }
            .reference + .meta { margin-top: .5rem; }
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
            @media print {
                :root, :root.dark, :root[data-theme="warm"], :root[data-theme="warm"].dark { color-scheme: light; --page: #fff; --surface: #fff; --text: #0f172a; --text-soft: #475569; --muted: #64748b; }
            }
        </style>
    </head>
    <body>
        <header class="header">
            <div class="header-inner">
                <div class="brand">
                    <img class="brand-mark" src="/falcon-forms-icon.svg" alt="" width="32" height="32">
                    <div>
                        <span class="brand-name">Falcon Forms</span>
                        <span class="brand-tagline">Formulir dan ujian tetap jalan, walau internet putus.</span>
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

                @if ($page['reference'])
                    <p class="reference">Kode laporan <strong>{{ $page['reference'] }}</strong></p>
                @endif
                @if ($page['occurredAt'])
                    <p class="meta">Waktu kejadian: {{ $page['occurredAt'] }}</p>
                @endif
            </section>
        </main>
    </body>
</html>
