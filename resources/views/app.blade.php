<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        {{-- Web fonts are optional enhancements: they load without blocking rendering, so pages open
             instantly on an intranet without internet access (system fonts are used as fallback). --}}
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" media="print" onload="this.media='all'" />
        <link href="https://fonts.googleapis.com/css2?family=Amiri+Quran&family=Amiri:ital,wght@0,400;0,700;1,400;1,700&family=Fira+Code&family=Inter:wght@400;600;700&family=Lora:ital,wght@0,400;0,700;1,400&family=Merriweather&family=Montserrat:wght@400;600;700&family=Noto+Naskh+Arabic:wght@400;600;700&family=Outfit:wght@400;600;700&family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Plus+Jakarta+Sans:wght@400;600;700&family=Roboto:wght@400;500;700&family=Scheherazade+New:wght@400;700&display=swap" rel="stylesheet" media="print" onload="this.media='all'" />

        @routes
        @vite(['resources/js/app.ts'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
