<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>@yield('title')</title>
    <style>
        @page { margin: 28px 32px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #111; }
        h1 { font-size: 16px; margin: 0 0 2px; }
        h2 { font-size: 12.5px; margin: 16px 0 6px; border-bottom: 1px solid #999; padding-bottom: 2px; }
        .kop { border-bottom: 2px solid #111; padding-bottom: 6px; margin-bottom: 12px; }
        .muted { color: #555; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #bbb; padding: 3px 5px; text-align: left; vertical-align: top; }
        th { background: #eee; }
        td.num, th.num { text-align: right; }
        .kv td { border: none; padding: 2px 4px; }
        .kv td:first-child { width: 32%; color: #444; }
        .page-break { page-break-after: always; }
        .footer { position: fixed; bottom: -12px; left: 0; right: 0; font-size: 8.5px; color: #777; }
    </style>
</head>
<body>
    <div class="kop">
        <h1>{{ config('sij.church_name') }}</h1>
        <div class="muted">{{ config('sij.church_city') }} &middot; @yield('subtitle')</div>
    </div>
    <div class="footer">Dicetak {{ now()->format('d/m/Y H:i') }} oleh {{ auth()->user()?->name }} &middot; Dokumen berisi data pribadi, jangan disebarluaskan.</div>
    @yield('content')
</body>
</html>
