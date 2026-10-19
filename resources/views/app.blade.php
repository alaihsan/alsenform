<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>Falcon Forms</title>
        <link rel="icon" href="/favicon.ico" sizes="32x32">
        <link rel="icon" href="/falcon-forms-icon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        {{-- Web fonts are an optional enhancement, fetched only once the page has loaded: on an intranet
             without internet a font request can hang for minutes, which left pages loading forever
             (and blank on browsers that wait for every style sheet). System fonts are used until then. --}}
        <script>
            window.addEventListener('load', function () {
                ['https://fonts.bunny.net/css?family=instrument-sans:400,500,600', 'https://fonts.googleapis.com/css2?family=Amiri+Quran&family=Amiri:ital,wght@0,400;0,700;1,400;1,700&family=Fira+Code&family=Inter:wght@400;600;700&family=Lora:ital,wght@0,400;0,700;1,400&family=Merriweather&family=Montserrat:wght@400;600;700&family=Noto+Naskh+Arabic:wght@400;600;700&family=Outfit:wght@400;600;700&family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Plus+Jakarta+Sans:wght@400;600;700&family=Roboto:wght@400;500;700&family=Scheherazade+New:wght@400;700&display=swap'].forEach(function (href) {
                    var link = document.createElement('link');
                    link.rel = 'stylesheet';
                    link.href = href;
                    document.head.appendChild(link);
                });
            });
        </script>

        @routes
        @vite(['resources/js/app.ts'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
