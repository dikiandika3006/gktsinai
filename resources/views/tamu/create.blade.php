@extends('layouts.mobile')
@section('title', 'Form Tamu')
@section('heading', 'Selamat datang!')
@section('subheading', $session ? $session->activityType?->name.' · '.$session->date->format('d/m/Y') : config('sij.church_name'))
@auth
    @section('back', route('absen.index'))
@endauth
@section('content')
    <p class="mb-4 text-slate-600">Terima kasih sudah beribadah bersama kami. Mohon isi data singkat berikut.</p>
    <form method="POST" action="{{ route('tamu.store') }}" class="space-y-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        @csrf
        <input type="hidden" name="sesi" value="{{ $session?->qr_token }}">
        <input type="text" name="website" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">
        @if ($errors->any())
            <div class="rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif
        <div><label class="label" for="name">Nama lengkap *</label><input id="name" name="name" value="{{ old('name') }}" required class="input" autocomplete="name"></div>
        <div><label class="label" for="phone">No. HP / WhatsApp</label><input id="phone" name="phone" value="{{ old('phone') }}" type="tel" inputmode="tel" class="input" placeholder="08..."></div>
        <div><label class="label" for="city">Alamat / kota</label><input id="city" name="city" value="{{ old('city') }}" class="input"></div>
        <div><label class="label" for="came_with">Datang bersama siapa?</label><input id="came_with" name="came_with" value="{{ old('came_with') }}" class="input" placeholder="mis. keluarga, teman, sendiri"></div>
        <div>
            <label class="label" for="source">Tahu gereja ini dari mana?</label>
            <select id="source" name="source" class="input">
                <option value="">- pilih -</option>
                @foreach ($sumber as $k => $v)<option value="{{ $k }}" @selected(old('source') === $k)>{{ $v }}</option>@endforeach
            </select>
        </div>
        <div><label class="label" for="origin_church">Gereja asal (bila ada)</label><input id="origin_church" name="origin_church" value="{{ old('origin_church') }}" class="input"></div>
        <label class="flex items-start gap-3 rounded-xl bg-slate-50 p-3">
            <input type="checkbox" name="consent_contact" value="1" class="mt-1 h-6 w-6 rounded" @checked(old('consent_contact'))>
            <span class="text-sm text-slate-700">Saya bersedia dihubungi oleh gereja melalui telepon/WhatsApp. Data saya hanya dipakai untuk pelayanan gereja (UU No. 27/2022 tentang Pelindungan Data Pribadi).</span>
        </label>
        <button class="btn btn-primary w-full">Kirim</button>
    </form>
@endsection
