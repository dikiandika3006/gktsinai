@extends('layouts.mobile')
@section('pwa', '1')
@section('title', 'Absensi')
@section('heading', 'Absensi Ibadah')
@section('subheading', auth()->user()->name)
@section('header-right')
    <a href="/admin" class="rounded-lg bg-indigo-500 px-3 py-1.5 text-sm font-medium">Panel</a>
@endsection
@section('content')
    <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-slate-500">Sesi 7 hari terakhir & mendatang</h2>
    <div class="space-y-2">
        @forelse ($sessions as $s)
            <a href="{{ route('absen.session', $s) }}" class="flex items-center gap-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200 active:bg-slate-50 {{ $s->date->isToday() ? 'ring-2 ring-indigo-500' : '' }}">
                <div class="flex h-14 w-14 shrink-0 flex-col items-center justify-center rounded-xl {{ $s->date->isToday() ? 'bg-indigo-600 text-white' : 'bg-slate-100' }}">
                    <span class="text-xl font-bold leading-none">{{ $s->date->format('d') }}</span>
                    <span class="text-xs">{{ $s->date->translatedFormat('M') }}</span>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="truncate text-lg font-semibold">{{ $s->activityType?->name }}</div>
                    <div class="text-sm text-slate-500">{{ $s->date->translatedFormat('l') }} &middot; {{ substr((string) $s->start_time, 0, 5) }} &middot; {{ $s->location }}</div>
                </div>
                <div class="text-right">
                    <div class="text-2xl font-bold text-indigo-600">{{ $s->manual_count ?? $s->attendances_count }}</div>
                    <div class="text-xs text-slate-500">hadir</div>
                </div>
            </a>
        @empty
            <p class="rounded-2xl bg-white p-6 text-center text-slate-500">Belum ada sesi. Buat sesi baru di bawah.</p>
        @endforelse
    </div>

    <details class="mt-6 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200" {{ $sessions->where('date', today())->isEmpty() ? 'open' : '' }}>
        <summary class="cursor-pointer text-lg font-semibold">+ Buat sesi baru</summary>
        <form method="POST" action="{{ route('absen.create-session') }}" class="mt-4 space-y-4">
            @csrf
            <div>
                <label class="label">Kegiatan</label>
                <select name="activity_type_id" class="input" required>
                    @foreach ($types as $t)
                        <option value="{{ $t->id }}">{{ $t->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid grid-cols-3 gap-3">
                <div class="col-span-3 sm:col-span-1"><label class="label">Tanggal</label><input type="date" name="date" value="{{ today()->toDateString() }}" class="input" required></div>
                <div><label class="label">Mulai</label><input type="time" name="start_time" value="07:00" class="input"></div>
                <div><label class="label">Selesai</label><input type="time" name="end_time" value="09:00" class="input"></div>
            </div>
            <div><label class="label">Tempat</label><input name="location" value="Gedung Gereja" class="input"></div>
            <button class="btn btn-primary w-full">Buat & mulai absensi</button>
        </form>
    </details>

    <a href="{{ route('tamu.create') }}" class="btn btn-light mt-4 w-full">Catat tamu baru</a>
@endsection
