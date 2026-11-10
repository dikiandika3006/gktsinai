@extends('layouts.mobile')
@section('title', 'Terima kasih')
@section('heading', 'Terima kasih')
@section('content')
    <div class="rounded-2xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-200">
        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-indigo-600 text-3xl text-white">&#10003;</div>
        <p class="mt-4 text-xl font-bold">{{ $lagi ? 'Senang bertemu kembali' : 'Selamat datang' }}{{ $nama ? ', '.$nama : '' }}!</p>
        <p class="mt-1 text-slate-600">Data Anda sudah kami terima. Tuhan Yesus memberkati.</p>
        @auth
            {{-- Tetap di sesi yang sama (mis. KU II tanggal 15), bukan kembali ke hari ini. --}}
            <a href="{{ route('tamu.create', array_filter(['sesi' => $session?->qr_token])) }}" class="btn btn-primary mt-6 w-full">Catat tamu lain</a>
            <a href="{{ $session ? route('absen.session', $session) : route('absen.index') }}" class="btn btn-light mt-2 w-full">Kembali ke absensi</a>
        @endauth
    </div>
@endsection
