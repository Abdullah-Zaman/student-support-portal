@props(['title' => null, 'heading' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta name="robots" content="noindex, nofollow">
        @include('partials.head', ['title' => $title ?? null])
    </head>
    <body class="min-h-screen bg-page antialiased">
        <header class="bg-plum px-4 py-4 text-white sm:px-8">
            <div class="mx-auto flex max-w-6xl items-center gap-3">
                <x-superior-logo white class="h-9 w-auto" />
                <span class="text-sm font-semibold">{{ $heading ?? __('Timetable') }}</span>
            </div>
        </header>
        <main class="mx-auto flex max-w-6xl flex-col gap-6 px-4 py-6 sm:px-8">
            {{ $slot }}
        </main>
    </body>
</html>

