<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#4f46e5">
    <meta name="sw" content="{{ asset('sw.js') }}">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <link rel="icon" href="{{ asset('logo-gkt.png') }}" type="image/png">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <title>Absensi - {{ config('sij.church_name') }}</title>
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/absen/main.jsx'])
</head>
<body class="bg-slate-100 text-slate-900 antialiased">
    <script id="absen-config" type="application/json">@json($config)</script>
    <div id="absen-root"></div>
    <noscript><p style="padding:24px;text-align:center">Aktifkan JavaScript untuk memakai absensi.</p></noscript>
</body>
</html>
