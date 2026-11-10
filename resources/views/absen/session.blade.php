@extends('layouts.mobile')
@section('pwa', '1')
@section('title', 'Absensi')
@section('back', route('absen.index'))
@section('heading', $session->activityType?->name)
@section('subheading', $session->date->translatedFormat('l, d/m/Y').' · '.substr((string) $session->start_time, 0, 5))
@section('header-right')
    <div class="text-right">
        <div id="count" class="text-2xl font-bold leading-none">{{ $session->manual_count ?? '-' }}</div>
        <div class="text-xs text-indigo-100">hadir</div>
    </div>
@endsection
@section('content')
    @if ($session->isCountOnly())
        {{-- KH-05: Sekolah Minggu / kegiatan tanpa daftar nama --}}
        <form method="POST" action="{{ route('absen.count', $session) }}" class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            @csrf
            @if (session('status'))<p class="mb-3 rounded-lg bg-green-50 p-3 text-green-700">{{ session('status') }}</p>@endif
            <label class="label text-base">Jumlah total hadir</label>
            <div class="flex items-center gap-3">
                <button type="button" class="btn btn-light h-16 w-16 text-3xl" onclick="const i=document.getElementById('mc');i.value=Math.max(0,(+i.value||0)-1)">&minus;</button>
                <input id="mc" type="number" inputmode="numeric" min="0" name="manual_count" value="{{ $session->manual_count ?? 0 }}" class="input h-16 text-center text-3xl font-bold">
                <button type="button" class="btn btn-light h-16 w-16 text-3xl" onclick="const i=document.getElementById('mc');i.value=(+i.value||0)+1">+</button>
            </div>
            <button class="btn btn-primary mt-4 w-full">Simpan jumlah</button>
        </form>
    @else
        <div id="status" class="mb-3 flex items-center justify-between rounded-xl bg-white px-3 py-2 text-sm shadow-sm ring-1 ring-slate-200">
            <span id="net" class="font-medium text-green-700">Online</span>
            <span id="queue" class="text-slate-500"></span>
        </div>

        <div class="sticky top-[64px] z-10 -mx-4 bg-slate-100 px-4 pb-2 pt-1">
            <input id="q" type="search" autocomplete="off" placeholder="Cari nama, alias, atau kode..." class="input shadow-sm">
            <div class="mt-2 grid grid-cols-2 gap-2 text-sm font-semibold">
                <button data-tab="belum" class="tab rounded-lg bg-indigo-600 py-2 text-white">Belum hadir</button>
                <button data-tab="hadir" class="tab rounded-lg bg-white py-2 text-slate-700 ring-1 ring-slate-300">Sudah hadir</button>
            </div>
        </div>

        <ul id="list" class="mt-1 space-y-2"><li class="p-6 text-center text-slate-500">Memuat daftar jemaat...</li></ul>
        <p id="more" class="hidden py-3 text-center text-sm text-slate-500"></p>

        <div class="mt-6 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
            <div class="flex items-center justify-between">
                <h3 class="font-semibold">Tamu hadir</h3>
                <a href="{{ route('tamu.create', ['sesi' => $session->qr_token]) }}" class="btn btn-light px-4 py-2 text-sm">+ Catat tamu</a>
            </div>
            <ul id="guests" class="mt-2 text-sm text-slate-600"></ul>
        </div>
        <a href="{{ route('sessions.qr', $session) }}" class="btn btn-light mt-3 w-full">Tampilkan QR check-in mandiri</a>
    @endif
@endsection

@if (! $session->isCountOnly())
@push('scripts')
<script>
(() => {
    const SID = @js($session->id);
    const DATA_URL = @js(route('absen.data', $session));
    const SYNC_URL = @js(route('absen.sync', $session));
    const CSRF = document.querySelector('meta[name=csrf-token]').content;
    const kData = `sij-absen-${SID}-data`, kQueue = `sij-absen-${SID}-queue`;
    const $ = (id) => document.getElementById(id);
    const store = {
        get(k, d) { try { return JSON.parse(localStorage.getItem(k)) ?? d; } catch (e) { return d; } },
        set(k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) {} },
    };

    let members = [], present = new Set(), guests = [], tab = 'belum';
    let queue = store.get(kQueue, []);
    const norm = (s) => (s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');

    function applyQueue() { queue.forEach(it => it.undo ? present.delete(it.member_id) : present.add(it.member_id)); }

    async function load() {
        const cached = store.get(kData, null);
        if (cached) { init(cached); }
        try {
            const res = await fetch(DATA_URL, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (!res.ok) throw new Error(res.status);
            const data = await res.json();
            store.set(kData, data);
            init(data);
        } catch (e) { if (!cached) $('list').innerHTML = '<li class="p-6 text-center text-red-600">Gagal memuat. Periksa koneksi lalu muat ulang.</li>'; }
    }

    function init(data) {
        members = data.members.map(m => ({ ...m, s: norm([m.n, m.a, m.c].join(' ')) }));
        present = new Set(data.present);
        guests = data.guests || [];
        applyQueue();
        render();
    }

    function toggle(id, force) {
        const on = force ?? !present.has(id);
        on ? present.add(id) : present.delete(id);
        queue = queue.filter(it => it.member_id !== id);
        queue.push({ member_id: id, at: new Date().toISOString(), undo: !on });
        store.set(kQueue, queue);
        if (navigator.vibrate) navigator.vibrate(30);
        render();
        sync();
    }

    function checkinFamily(fid) {
        members.filter(m => m.f === fid).forEach(m => { if (!present.has(m.id)) toggle(m.id, true); });
    }

    function esc(s) { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; }

    function render() {
        $('count').textContent = present.size + guests.length;
        const q = norm($('q').value.trim());
        let rows = members.filter(m => tab === 'hadir' ? present.has(m.id) : !present.has(m.id));
        if (q) rows = rows.filter(m => q.split(/\s+/).every(t => m.s.includes(t)));
        const total = rows.length;
        rows = rows.slice(0, 60);
        $('list').innerHTML = rows.length ? rows.map(m => {
            const on = present.has(m.id);
            const fam = m.f ? members.filter(x => x.f === m.f) : [];
            const famLeft = fam.filter(x => !present.has(x.id)).length;
            return `<li class="flex items-stretch gap-2">
                <button data-id="${m.id}" class="pick flex min-w-0 flex-1 items-center gap-3 rounded-2xl p-4 text-left shadow-sm ring-1 ${on ? 'bg-green-50 ring-green-400' : 'bg-white ring-slate-200'}">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border-2 ${on ? 'border-green-600 bg-green-600 text-white' : 'border-slate-300'}">${on ? '&#10003;' : ''}</span>
                    <span class="min-w-0">
                        <span class="block truncate text-lg font-semibold">${esc(m.n)}${m.a ? ` <span class="font-normal text-slate-500">(${esc(m.a)})</span>` : ''}</span>
                        <span class="block truncate text-sm text-slate-500">${esc(m.c)} &middot; ${esc(m.fk || 'tanpa KK')}${m.r ? ' &middot; ' + esc(m.r) : ''}${m.p ? ' &middot; <span class="text-amber-600">pasif</span>' : ''}</span>
                    </span>
                </button>
                ${fam.length > 1 && famLeft > 0 ? `<button data-fam="${m.f}" class="fam w-20 shrink-0 rounded-2xl bg-indigo-50 text-xs font-semibold text-indigo-700 ring-1 ring-indigo-200">Satu KK<br>(${famLeft})</button>` : ''}
            </li>`;
        }).join('') : `<li class="p-6 text-center text-slate-500">${q ? 'Tidak ditemukan.' : (tab === 'hadir' ? 'Belum ada yang hadir.' : 'Semua sudah hadir.')}</li>`;
        $('more').classList.toggle('hidden', total <= 60);
        $('more').textContent = `${total - 60} lainnya, persempit pencarian.`;
        $('guests').innerHTML = guests.length ? guests.map(g => `<li>&bull; ${esc(g)}</li>`).join('') : '<li>Belum ada.</li>';
        $('queue').textContent = queue.length ? `${queue.length} menunggu dikirim` : 'Tersimpan';
    }

    let syncing = false;
    async function sync() {
        if (syncing || !queue.length || !navigator.onLine) { render(); return; }
        syncing = true;
        const batch = queue.slice();
        try {
            const res = await fetch(SYNC_URL, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': CSRF },
                body: JSON.stringify({ items: batch }),
            });
            if (res.status === 419 || res.status === 401) { $('net').textContent = 'Sesi login habis, muat ulang halaman'; $('net').className = 'font-medium text-red-600'; }
            if (res.ok) {
                queue = queue.filter(it => !batch.some(b => b.member_id === it.member_id && b.at === it.at));
                store.set(kQueue, queue);
            }
        } catch (e) {}
        syncing = false;
        render();
    }

    function net() {
        const on = navigator.onLine;
        $('net').textContent = on ? 'Online' : 'Offline: centangan disimpan di HP';
        $('net').className = 'font-medium ' + (on ? 'text-green-700' : 'text-amber-600');
        if (on) sync();
    }

    document.addEventListener('click', (e) => {
        const p = e.target.closest('.pick'); if (p) return toggle(+p.dataset.id);
        const f = e.target.closest('.fam'); if (f) return checkinFamily(+f.dataset.fam);
        const t = e.target.closest('.tab');
        if (t) {
            tab = t.dataset.tab;
            document.querySelectorAll('.tab').forEach(b => b.className = 'tab rounded-lg py-2 ' + (b === t ? 'bg-indigo-600 text-white' : 'bg-white text-slate-700 ring-1 ring-slate-300'));
            render();
        }
    });
    let deb; $('q').addEventListener('input', () => { clearTimeout(deb); deb = setTimeout(render, 120); });
    window.addEventListener('online', net); window.addEventListener('offline', net);
    setInterval(sync, 15000);
    net(); load();
})();
</script>
@endpush
@endif
