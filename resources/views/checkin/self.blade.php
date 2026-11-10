@extends('layouts.mobile')
@section('title', 'Check-in')
@section('heading', 'Selamat datang')
@section('subheading', $session->activityType?->name.' · '.$session->date->translatedFormat('d F Y'))
@section('content')
    @if (! $open)
        <div class="rounded-2xl bg-white p-6 text-center shadow-sm ring-1 ring-slate-200">
            <p class="text-lg font-semibold">Check-in belum dibuka atau sudah ditutup.</p>
            @php([$buka, $tutup] = $session->checkinWindow())
            <p class="mt-1 text-slate-500">Check-in mandiri untuk sesi ini dibuka {{ $buka->translatedFormat('d F Y') }} pukul {{ $buka->format('H:i') }} - {{ $tutup->format('H:i') }}. Silakan menghubungi petugas penyambut.</p>
        </div>
    @else
        <div id="step-search">
            <label for="q" class="label text-base">Ketik nama Anda (minimal 3 huruf)</label>
            <input id="q" type="search" autocomplete="off" class="input" placeholder="Nama atau nama panggilan">
            <ul id="res" class="mt-3 space-y-2"></ul>
            <div class="mt-6 rounded-2xl bg-white p-4 text-center shadow-sm ring-1 ring-slate-200">
                <p class="text-slate-600">Baru pertama kali beribadah di sini, atau nama tidak ditemukan?</p>
                <a href="{{ route('tamu.create', ['sesi' => $session->qr_token]) }}" class="btn btn-primary mt-3 w-full">Isi form tamu</a>
            </div>
        </div>
        <div id="step-confirm" class="hidden rounded-2xl bg-white p-6 text-center shadow-sm ring-1 ring-slate-200">
            <p class="text-slate-500">Konfirmasi kehadiran sebagai</p>
            <p id="cname" class="my-2 text-2xl font-bold"></p>
            <button id="yes" class="btn btn-primary w-full">Ya, saya hadir</button>
            <button id="no" class="btn btn-light mt-2 w-full">Bukan saya</button>
        </div>
        <div id="step-done" class="hidden rounded-2xl bg-green-50 p-8 text-center ring-1 ring-green-300">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-green-600 text-3xl text-white">&#10003;</div>
            <p class="mt-3 text-xl font-bold text-green-800">Terima kasih, <span id="dname"></span>!</p>
            <p class="text-green-700">Kehadiran Anda sudah tercatat. Tuhan memberkati.</p>
            <button id="other" class="btn btn-light mt-4 w-full">Check-in anggota keluarga lain</button>
        </div>
    @endif
@endsection

@if ($open)
@push('scripts')
<script>
(() => {
    const SEARCH = @js(route('checkin.search', $session->qr_token));
    const STORE = @js(route('checkin.store', $session->qr_token));
    const CSRF = document.querySelector('meta[name=csrf-token]').content;
    const $ = (id) => document.getElementById(id);
    const show = (s) => ['search', 'confirm', 'done'].forEach(x => $('step-' + x).classList.toggle('hidden', x !== s));
    const esc = (s) => { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; };
    let picked = null, deb;

    $('q').addEventListener('input', () => {
        clearTimeout(deb);
        deb = setTimeout(async () => {
            const q = $('q').value.trim();
            if (q.length < 3) { $('res').innerHTML = ''; return; }
            const r = await fetch(SEARCH + '?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' } });
            const list = r.ok ? await r.json() : [];
            $('res').innerHTML = list.length ? list.map(m => `<li><button data-id="${m.id}" data-name="${esc(m.name)}" ${m.done ? 'disabled' : ''}
                class="pick w-full rounded-2xl bg-white p-4 text-left shadow-sm ring-1 ring-slate-200 disabled:opacity-60">
                <span class="block text-lg font-semibold">${esc(m.name)}</span>
                <span class="block text-sm text-slate-500">${esc(m.info)}${m.done ? ' · sudah check-in' : ''}</span></button></li>`).join('')
                : '<li class="p-4 text-center text-slate-500">Nama tidak ditemukan.</li>';
        }, 250);
    });
    document.addEventListener('click', (e) => {
        const b = e.target.closest('.pick');
        if (b && !b.disabled) { picked = { id: +b.dataset.id, name: b.dataset.name }; $('cname').textContent = picked.name; show('confirm'); }
    });
    $('no').onclick = () => show('search');
    $('other').onclick = () => { $('q').value = ''; $('res').innerHTML = ''; show('search'); $('q').focus(); };
    $('yes').onclick = async () => {
        $('yes').disabled = true;
        const r = await fetch(STORE, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': CSRF }, body: JSON.stringify({ member_id: picked.id }) });
        $('yes').disabled = false;
        if (r.ok) { $('dname').textContent = picked.name; show('done'); }
        else alert('Gagal mencatat. Silakan hubungi petugas.');
    };
})();
</script>
@endpush
@endif
