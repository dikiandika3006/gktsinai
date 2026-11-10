<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#4f46e5">
    @hasSection('pwa')
        <meta name="sw" content="1">
        <link rel="manifest" href="/manifest.webmanifest">
        <meta name="apple-mobile-web-app-capable" content="yes">
    @endif
    <link rel="icon" href="/icon.svg" type="image/svg+xml">
    <title>@yield('title', 'SIJ') - {{ config('sij.church_name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
    <header class="sticky top-0 z-20 bg-indigo-600 text-white shadow">
        <div class="mx-auto flex max-w-2xl items-center gap-3 px-4 py-3">
            @hasSection('back')
                <a href="@yield('back')" class="-ml-1 rounded-lg p-1.5 hover:bg-indigo-500" aria-label="Kembali">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                </a>
            @endif
            <div class="min-w-0 flex-1">
                <div class="truncate text-lg font-bold leading-tight">@yield('heading', config('sij.church_name'))</div>
                @hasSection('subheading')<div class="truncate text-sm text-indigo-100">@yield('subheading')</div>@endif
            </div>
            @yield('header-right')
        </div>
    </header>
    <main class="mx-auto max-w-2xl px-4 py-4">
        @yield('content')
    </main>
    @stack('scripts')
</body>
</html>
