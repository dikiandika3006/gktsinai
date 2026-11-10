@extends('layouts.mobile')
@section('title', 'QR Check-in')
@section('back', route('absen.session', $session))
@section('heading', 'Check-in Mandiri')
@section('subheading', $session->title)
@section('content')
    <div class="rounded-3xl bg-white p-6 text-center shadow ring-1 ring-slate-200">
        <p class="text-xl font-bold">Pindai untuk mengisi kehadiran</p>
        <p class="mt-1 text-slate-500">{{ $session->activityType?->name }} &middot; {{ $session->date->translatedFormat('l, d F Y') }}</p>
        <img src="{{ $svg }}" alt="QR check-in" class="mx-auto my-4 w-full max-w-sm">
        <p class="text-sm text-slate-500">Berlaku {{ $mulai->format('H:i') }} - {{ $selesai->format('H:i') }}. QR ini hanya untuk sesi ini.</p>
        <p class="mt-2 break-all text-xs text-slate-400">{{ $url }}</p>
    </div>
@endsection
