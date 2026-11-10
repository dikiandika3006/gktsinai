@extends('layouts.mobile')
@section('pwa', '1')
@section('title', 'Absensi')
@section('heading', 'Absensi Ibadah')
@section('header-right')
    <div class="text-right">
        <div id="count" class="text-2xl font-bold leading-none">-</div>
        <div class="text-xs text-indigo-100">hadir</div>
    </div>
@endsection
@section('content')
    {{-- 1. Pilih tanggal & ibadah --}}
    <div class="grid grid-cols-5 gap-2">
        <input id="date" type="date" value="{{ $presetDate }}" class="input col-span-2 px-3 text-base" aria-label="Tanggal">
        <select id="type" class="input col-span-3 px-3 text-base" aria-label="Ibadah">
            @foreach ($types as $t)
                <option value="{{ $t['id'] }}" @selected($t['id'] == $presetType)>{{ $t['name'] }}</option>
            @endforeach
        </select>
    </div>
    <p id="net" class="mt-2 text-sm font-medium text-green-700"></p>

    {{-- Kegiatan yang hanya dicatat jumlahnya (Sekolah Minggu) --}}
    <div id="countbox" class="mt-3 hidden rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <label class="label text-base">Jumlah total hadir</label>
        <div class="flex items-center gap-3">
            <button type="button" data-step="-1" class="step btn btn-light h-16 w-16 text-3xl">&minus;</button>
            <input id="mc" type="number" inputmode="numeric" min="0" class="input h-16 text-center text-3xl font-bold">
            <button type="button" data-step="1" class="step btn btn-light h-16 w-16 text-3xl">+</button>
        </div>
        <button id="savecount" class="btn btn-primary mt-4 w-full">Simpan jumlah</button>
    </div>

    {{-- 2. Cari nama / kepala keluarga, 3. klik untuk absen --}}
    <div id="namebox" class="mt-3 hidden">
        <div class="sticky top-[68px] z-10 -mx-4 bg-slate-100 px-4 pb-2 pt-1">
            <input id="q" type="search" autocomplete="off" placeholder="Ketik nama jemaat atau kepala keluarga..." class="input shadow-sm">
        </div>
        <ul id="list" class="mt-1 space-y-2"></ul>
        <p id="more" class="hidden py-3 text-center text-sm text-slate-500"></p>

        <details class="mt-6 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
            <summary class="cursor-pointer font-semibold">Sudah hadir (<span id="npresent">0</span>)</summary>
            <ul id="presentlist" class="mt-2 divide-y divide-slate-100 text-sm"></ul>
        </details>

        <div class="mt-3 grid grid-cols-2 gap-2">
            <a id="guestlink" href="#" class="btn btn-light text-sm">+ Catat tamu</a>
            <a id="qrlink" href="#" class="btn btn-light text-sm">QR mandiri</a>
        </div>
    </div>

    <p class="mt-6 text-center text-sm"><a href="{{ url('/admin') }}" class="text-slate-500 underline">Buka panel admin</a></p>
@endsection

@push('scripts')
<script>
(() => {
    const OPEN_URL = @js(route('absen.open'));
    const CSRF = document.querySelector('meta[name=csrf-token]').content;
    const $ = (id) => document.getElementById(id);
    const store = {
        get(k, d) { try { return JSON.parse(localStorage.getItem(k)) ?? d; } catch (e) { return d; } },
        set(k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) {} },
    };
    const norm = (s) => (s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
    const esc = (s) => { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; };
    const post = (url, body) => fetch(url, {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify(body),
    });

    let sess = null, members = [], present = new Set(), guests = [], queue = [];
    const qKey = () => `absen-queue-${sess.id}`, dKey = () => `absen-data-${sess.id}`;

    // ---- 1. Pilih tanggal & ibadah ----
    async function openSession() {
        const key = `absen-open-${$('type').value}-${$('date').value}`;
        store.set('absen-last', { type: $('type').value, date: $('date').value });
        $('list').innerHTML = '<li class="p-6 text-center text-slate-500">Memuat...</li>';
        try {
            const r = await post(OPEN_URL, { activity_type_id: +$('type').value, date: $('date').value });
            if (r.status === 401 || r.status === 419) return status('Sesi login habis. Muat ulang halaman lalu login.', 'red');
            if (!r.ok) throw new Error(r.status);
            sess = await r.json();
            store.set(key, sess);
        } catch (e) {
            sess = store.get(key, null);
            if (!sess) { $('list').innerHTML = '<li class="p-6 text-center text-red-600">Tidak ada koneksi. Ibadah ini belum pernah dibuka di HP ini.</li>'; return; }
        }
        $('countbox').classList.toggle('hidden', !sess.count_only);
        $('namebox').classList.toggle('hidden', sess.count_only);
        $('guestlink').href = sess.guest_url; $('qrlink').href = sess.qr_url;
        if (sess.count_only) { $('mc').value = sess.manual_count ?? 0; $('count').textContent = sess.manual_count ?? 0; return; }
        queue = store.get(qKey(), []);
        await loadMembers();
    }

    async function loadMembers() {
        const cached = store.get(dKey(), null);
        if (cached) init(cached);
        try {
            const r = await fetch(sess.data_url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (!r.ok) throw new Error(r.status);
            const data = await r.json();
            store.set(dKey(), data);
            init(data);
        } catch (e) { if (!cached) $('list').innerHTML = '<li class="p-6 text-center text-red-600">Gagal memuat daftar jemaat.</li>'; }
    }

    function init(data) {
        // Pencarian mencakup nama, alias, kode, nama kepala keluarga, dan kode KK.
        members = data.members.map(m => ({ ...m, s: norm([m.n, m.a, m.c, m.fh, m.fk].join(' ')) }));
        present = new Set(data.present);
        guests = data.guests || [];
        queue.forEach(it => it.undo ? present.delete(it.member_id) : present.add(it.member_id));
        render();
    }

    // ---- 3. Klik untuk absen ----
    function mark(id, on) {
        on ? present.add(id) : present.delete(id);
        queue = queue.filter(it => it.member_id !== id);
        queue.push({ member_id: id, at: new Date().toISOString(), undo: !on });
        store.set(qKey(), queue);
        if (navigator.vibrate) navigator.vibrate(30);
        render();
        sync();
    }

    // ---- 2. Cari nama / kepala keluarga ----
    function render() {
        $('count').textContent = present.size + guests.length;
        const q = norm($('q').value.trim());
        if (!q) {
            $('list').innerHTML = '<li class="rounded-2xl bg-white p-6 text-center text-slate-500 ring-1 ring-slate-200">Ketik nama jemaat atau nama kepala keluarga di atas.</li>';
            $('more').classList.add('hidden');
        } else {
            let rows = members.filter(m => q.split(/\s+/).every(t => m.s.includes(t)));
            // Hasil yang cocok dengan nama orang tampil lebih dulu daripada yang cocok lewat kepala keluarga.
            rows.sort((a, b) => (norm(b.n).includes(q) - norm(a.n).includes(q)) || a.n.localeCompare(b.n));
            const total = rows.length; rows = rows.slice(0, 40);
            const fams = new Set();
            $('list').innerHTML = rows.length ? rows.map(m => {
                const on = present.has(m.id);
                let famBtn = '';
                if (m.f && !fams.has(m.f)) {
                    const left = members.filter(x => x.f === m.f && !present.has(x.id)).length;
                    const size = members.filter(x => x.f === m.f).length;
                    if (size > 1 && left > 0) { fams.add(m.f); famBtn = `<button data-fam="${m.f}" class="fam mt-2 w-full rounded-xl bg-indigo-50 py-2 text-sm font-semibold text-indigo-700 ring-1 ring-indigo-200">Absen sekeluarga ${esc(m.fh || '')} (${left} orang)</button>`; }
                }
                return `<li><button data-id="${m.id}" class="pick flex w-full items-center gap-3 rounded-2xl p-4 text-left shadow-sm ring-1 ${on ? 'bg-green-50 ring-green-400' : 'bg-white ring-slate-200'}">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border-2 text-xl ${on ? 'border-green-600 bg-green-600 text-white' : 'border-slate-300'}">${on ? '&#10003;' : ''}</span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-lg font-semibold">${esc(m.n)}${m.a ? ` <span class="font-normal text-slate-500">(${esc(m.a)})</span>` : ''}</span>
                        <span class="block truncate text-sm text-slate-500">KK: ${esc(m.fh || '-')}${m.r ? ' &middot; ' + esc(m.r) : ''}</span>
                    </span>
                    <span class="text-sm font-semibold ${on ? 'text-green-700' : 'text-indigo-600'}">${on ? 'Hadir' : 'Absen'}</span>
                </button>${famBtn}</li>`;
            }).join('') : '<li class="rounded-2xl bg-white p-6 text-center text-slate-500 ring-1 ring-slate-200">Tidak ditemukan. Bila tamu, gunakan "Catat tamu".</li>';
            $('more').classList.toggle('hidden', total <= 40);
            $('more').textContent = `${total - 40} lainnya, ketik lebih lengkap.`;
        }
        const hadir = members.filter(m => present.has(m.id)).sort((a, b) => a.n.localeCompare(b.n));
        $('npresent').textContent = hadir.length + guests.length;
        $('presentlist').innerHTML = hadir.map(m => `<li class="flex items-center justify-between py-2"><span>${esc(m.n)}</span>
            <button data-undo="${m.id}" class="undo text-xs text-red-600 underline">batal</button></li>`).join('')
            + guests.map(g => `<li class="py-2 text-slate-500">${esc(g)} (tamu)</li>`).join('');
        net();
    }

    let syncing = false;
    async function sync() {
        if (!sess || syncing || !queue.length || !navigator.onLine) return net();
        syncing = true;
        const batch = queue.slice();
        try {
            const r = await post(sess.sync_url, { items: batch });
            if (r.status === 401 || r.status === 419) status('Sesi login habis. Muat ulang halaman lalu login.', 'red');
            if (r.ok) {
                queue = queue.filter(it => !batch.some(b => b.member_id === it.member_id && b.at === it.at));
                store.set(qKey(), queue);
            }
        } catch (e) {}
        syncing = false;
        net();
    }

    function status(text, color) { $('net').textContent = text; $('net').className = `mt-2 text-sm font-medium text-${color}-700`; }
    function net() {
        if (!navigator.onLine) return status(`Offline: ${queue.length} absen disimpan di HP, dikirim otomatis saat online.`, 'amber');
        status(queue.length ? `Mengirim ${queue.length} absen...` : 'Online - semua absen tersimpan.', queue.length ? 'amber' : 'green');
    }

    document.addEventListener('click', (e) => {
        const p = e.target.closest('.pick'); if (p) { const id = +p.dataset.id; return mark(id, !present.has(id)); }
        const f = e.target.closest('.fam'); if (f) { members.filter(m => m.f === +f.dataset.fam && !present.has(m.id)).forEach(m => mark(m.id, true)); return; }
        const u = e.target.closest('.undo'); if (u) return mark(+u.dataset.undo, false);
        const s = e.target.closest('.step'); if (s) $('mc').value = Math.max(0, (+$('mc').value || 0) + +s.dataset.step);
    });
    $('savecount').onclick = async () => {
        const r = await post(sess.count_url, { manual_count: +$('mc').value || 0 }).catch(() => null);
        status(r && r.ok ? 'Jumlah hadir disimpan.' : 'Gagal menyimpan, periksa koneksi.', r && r.ok ? 'green' : 'red');
        if (r && r.ok) $('count').textContent = $('mc').value;
    };
    let deb; $('q').addEventListener('input', () => { clearTimeout(deb); deb = setTimeout(render, 120); });
    $('type').addEventListener('change', openSession);
    $('date').addEventListener('change', openSession);
    window.addEventListener('online', sync); window.addEventListener('offline', net);
    setInterval(sync, 15000);

    @if (! request()->route('session') && ! request()->has('type'))
        const last = store.get('absen-last', null);
        if (last && [...$('type').options].some(o => o.value === last.type)) $('type').value = last.type;
    @endif
    openSession();
})();
</script>
@endpush
