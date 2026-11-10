import { useDeferredValue, useEffect, useMemo, useRef, useState } from 'react';
import { cariKeluarga, formatTanggal, geserTanggal, jam, norm, store } from './lib';
import { useAbsensi } from './useAbsensi';

const cx = (...c) => c.filter(Boolean).join(' ');

export default function App({ config }) {
    // Pilihan tanggal + ibadah. Tanpa pilihan -> layar "Pilih ibadah".
    // Dipulihkan saat kembali dari form tamu / halaman dimuat ulang, agar tidak loncat ke hari ini.
    const [pilihan, setPilihanState] = useState(() => {
        if (config.preset) return { type: config.preset.type, date: config.preset.date, kebaktian: config.preset.kebaktian };
        const cur = store.get('absen-current', null);
        return cur && Date.now() - cur.saved < 12 * 3600e3 && config.types.some((t) => t.id === cur.type) ? cur : null;
    });
    const setPilihan = (p) => {
        store.set('absen-current', p ? { ...p, saved: Date.now() } : null);
        if (!p) history.replaceState(null, '', config.openUrl.replace(/\/buka$/, ''));
        setPilihanState(p);
    };
    const [tab, setTab] = useState('absen');
    const [menu, setMenu] = useState(false);
    const a = useAbsensi(config.openUrl, pilihan);
    const type = config.types.find((t) => t.id === pilihan?.type);
    const header = useRef(null);

    // Alamat halaman mengikuti sesi (/absen/{id}) supaya muat ulang tetap di sesi yang sama.
    useEffect(() => {
        if (pilihan && a.sess) history.replaceState(null, '', a.sess.data_url.replace(/\/data$/, ''));
    }, [pilihan, a.sess]);

    // Tinggi header (bisa bertambah oleh bar status offline) untuk posisi kolom cari yang menempel.
    useEffect(() => {
        const el = header.current;
        const ro = new ResizeObserver(() => document.documentElement.style.setProperty('--hdr', el.offsetHeight + 'px'));
        ro.observe(el);
        return () => ro.disconnect();
    }, []);

    const pilih = (p) => {
        store.set('absen-last', p);
        setTab('absen');
        setPilihan(p);
    };

    const total = a.sess?.count_only ? (a.sess.manual_count ?? 0) : a.present.size + a.guests.length;

    return (
        <div className="flex min-h-dvh flex-col">
            <header ref={header} className="sticky top-0 z-30 bg-indigo-600 pt-[env(safe-area-inset-top)] text-white shadow">
                <div className="mx-auto flex max-w-2xl items-center gap-3 px-4 py-2.5">
                    <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-white p-1">
                        <img src={config.logo} alt="GKT" className="max-h-full max-w-full" />
                    </span>
                    {pilihan ? (
                        <button onClick={() => setPilihan(null)} className="min-w-0 flex-1 text-left" aria-label="Ganti tanggal atau ibadah">
                            <div className="truncate text-base font-bold leading-tight">
                                {type?.name}
                                {pilihan.kebaktian ? ` · ${pilihan.kebaktian}` : ''}
                            </div>
                            <div className="truncate text-sm text-indigo-100">
                                {formatTanggal(pilihan.date)} <span className="underline">ganti</span>
                            </div>
                        </button>
                    ) : (
                        <div className="min-w-0 flex-1">
                            <div className="truncate text-base font-bold leading-tight">Absensi Ibadah</div>
                            <div className="truncate text-sm text-indigo-100">{config.church}</div>
                        </div>
                    )}
                    {pilihan && (
                        <div className="text-right">
                            <div className="text-2xl font-bold leading-none tabular-nums">{a.phase === 'ready' ? total : '-'}</div>
                            <div className="text-xs text-indigo-100">hadir</div>
                        </div>
                    )}
                    <button onClick={() => setMenu(true)} className="-mr-2 rounded-lg p-2 hover:bg-indigo-500" aria-label="Menu">
                        <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                            <path strokeLinecap="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                </div>
                {pilihan && <StatusBar a={a} />}
            </header>

            <main className="mx-auto w-full max-w-2xl flex-1 px-4 pb-28 pt-3">
                {!pilihan ? (
                    <PilihIbadah config={config} onPilih={pilih} initialDate={a.sess && store.get('absen-last', null)?.date} />
                ) : a.phase === 'loading' || a.phase === 'idle' ? (
                    <Kosong>Memuat daftar...</Kosong>
                ) : a.phase === 'error' ? (
                    <Kosong tone="red">
                        {a.error}
                        <button className="btn btn-light mt-4 w-full" onClick={() => setPilihan({ ...pilihan })}>
                            Coba lagi
                        </button>
                    </Kosong>
                ) : a.sess.count_only ? (
                    <HitungJumlah key={a.sess.id} a={a} />
                ) : tab === 'absen' ? (
                    <CariAbsen a={a} />
                ) : (
                    <DaftarHadir a={a} kebaktian={pilihan.kebaktian} />
                )}
            </main>

            {pilihan && a.phase === 'ready' && !a.sess.count_only && (
                <nav className="fixed inset-x-0 bottom-0 z-30 border-t border-slate-200 bg-white pb-[env(safe-area-inset-bottom)]">
                    <div className="mx-auto grid max-w-2xl grid-cols-3">
                        <NavBtn active={tab === 'absen'} onClick={() => setTab('absen')} icon="search" label="Absen" />
                        <NavBtn active={tab === 'hadir'} onClick={() => setTab('hadir')} icon="list" label={`Hadir (${total})`} />
                        <NavBtn href={a.sess.guest_url} icon="plus" label="Catat tamu" />
                    </div>
                </nav>
            )}

            {menu && <Menu config={config} sess={pilihan ? a.sess : null} onClose={() => setMenu(false)} onGanti={() => (setMenu(false), setPilihan(null))} />}
        </div>
    );
}

function StatusBar({ a }) {
    if (a.authExpired)
        return (
            <a href={location.href} className="block bg-red-600 px-4 py-1.5 text-center text-sm font-medium">
                Sesi login habis. Ketuk untuk login ulang.
            </a>
        );
    if (!a.online)
        return <div className="bg-amber-500 px-4 py-1.5 text-center text-sm font-medium">Offline · {a.pending} absen disimpan di HP, dikirim otomatis saat online</div>;
    if (a.pending) return <div className="bg-amber-500 px-4 py-1.5 text-center text-sm font-medium">Mengirim {a.pending} absen...</div>;
    return null;
}

/** Langkah 1 & 2: pilih tanggal, lalu ibadah (KU I / KU II / Gabungan / ibadah lain). */
function PilihIbadah({ config, onPilih, initialDate }) {
    const last = store.get('absen-last', null);
    // Pertama kali: hari ini. Saat "ganti" dari sesi yang sedang dibuka: tanggal sesi itu.
    const [date, setDate] = useState(initialDate || config.today);
    const umum = config.types.find((t) => t.umum);
    const lain = config.types.filter((t) => !t.umum);
    const isLast = (type, kebaktian) => last && last.type === type && (last.kebaktian || null) === (kebaktian || null);

    return (
        <div className="space-y-5">
            <section>
                <h2 className="mb-2 text-sm font-semibold uppercase tracking-wide text-slate-500">1. Tanggal</h2>
                <div className="flex items-stretch gap-2">
                    <button className="btn btn-light w-14 shrink-0 px-0 text-xl" onClick={() => setDate(geserTanggal(date, -1))} aria-label="Hari sebelumnya">
                        ‹
                    </button>
                    <label className="relative flex min-w-0 flex-1 flex-col justify-center rounded-xl bg-white px-4 py-2 text-center ring-1 ring-slate-300">
                        <span className="truncate text-lg font-bold">{formatTanggal(date)}</span>
                        <span className="text-xs text-slate-500">{date === config.today ? 'Hari ini' : 'Ketuk untuk pilih tanggal'}</span>
                        <input type="date" value={date} onChange={(e) => e.target.value && setDate(e.target.value)} className="absolute inset-0 cursor-pointer opacity-0" aria-label="Tanggal" />
                    </label>
                    <button className="btn btn-light w-14 shrink-0 px-0 text-xl" onClick={() => setDate(geserTanggal(date, 1))} aria-label="Hari berikutnya">
                        ›
                    </button>
                </div>
                {date !== config.today && (
                    <button className="mt-2 text-sm font-medium text-indigo-600 underline" onClick={() => setDate(config.today)}>
                        Kembali ke hari ini
                    </button>
                )}
            </section>

            <section>
                <h2 className="mb-2 text-sm font-semibold uppercase tracking-wide text-slate-500">2. Ibadah</h2>
                {umum && (
                    <div className="rounded-2xl bg-white p-3 shadow-sm ring-1 ring-slate-200">
                        <div className="mb-2 px-1 font-semibold">{umum.name}</div>
                        <div className="grid grid-cols-3 gap-2">
                            {(config.kebaktian.length ? config.kebaktian : [null]).map((k) => (
                                <button
                                    key={k ?? 'umum'}
                                    onClick={() => onPilih({ type: umum.id, date, kebaktian: k })}
                                    className={cx(
                                        'rounded-xl px-2 py-5 text-lg font-bold transition active:scale-[.97]',
                                        isLast(umum.id, k) ? 'bg-indigo-600 text-white' : 'bg-indigo-50 text-indigo-700 ring-1 ring-indigo-200',
                                    )}
                                >
                                    {k ?? 'Buka'}
                                </button>
                            ))}
                        </div>
                    </div>
                )}
                {lain.length > 0 && (
                    <div className="mt-3 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
                        <div className="px-4 pt-3 text-sm text-slate-500">Ibadah lain</div>
                        <div className="divide-y divide-slate-100">
                            {lain.map((t) => (
                                <button key={t.id} onClick={() => onPilih({ type: t.id, date, kebaktian: null })} className="flex w-full items-center justify-between px-4 py-4 text-left active:bg-slate-50">
                                    <span className={cx('text-base', isLast(t.id, null) && 'font-semibold text-indigo-700')}>{t.name}</span>
                                    <span className="text-sm text-slate-400">{t.count_only ? 'jumlah' : '›'}</span>
                                </button>
                            ))}
                        </div>
                    </div>
                )}
            </section>
            <p className="text-center text-sm text-slate-500">3. Setelah memilih ibadah, daftar absensi akan muncul.</p>
        </div>
    );
}

/** Langkah 3: cari nama / kepala keluarga, ketuk untuk absen. */
function CariAbsen({ a }) {
    const [q, setQ] = useState('');
    const dq = useDeferredValue(q);
    const [toast, setToast] = useState(null);
    const input = useRef(null);
    const { groups, total, hit } = useMemo(() => cariKeluarga(a.members, dq), [a.members, dq]);

    useEffect(() => {
        if (!toast) return;
        const t = setTimeout(() => setToast(null), 3500);
        return () => clearTimeout(t);
    }, [toast]);

    const toggle = (m) => {
        // Sudah hadir di kebaktian lain hari ini: cukup diabsen sekali.
        const lain = a.hadirDiLain.get(m.id);
        if (lain && !a.present.has(m.id)) return setToast({ m, lain });
        const on = !a.present.has(m.id);
        a.mark(m.id, on);
        setToast({ m, on });
    };
    const hadir = (id) => a.present.has(id) || a.hadirDiLain.has(id);

    return (
        <>
            <div className="sticky top-[var(--hdr,64px)] z-20 -mx-4 bg-slate-100 px-4 pb-2 pt-1">
                <div className="relative">
                    <input
                        ref={input}
                        type="search"
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                        autoComplete="off"
                        enterKeyHint="search"
                        placeholder="Cari nama jemaat / kepala keluarga"
                        className="input pr-11 shadow-sm"
                    />
                    {q && (
                        <button onClick={() => (setQ(''), input.current?.focus())} className="absolute inset-y-0 right-0 px-3 text-2xl text-slate-400" aria-label="Hapus pencarian">
                            ×
                        </button>
                    )}
                </div>
            </div>

            {!dq.trim() ? (
                <Kosong>
                    Ketik minimal sebagian nama jemaat atau nama kepala keluarga.
                    <div className="mt-2 text-xs">{a.members.length} jemaat dalam daftar</div>
                </Kosong>
            ) : !groups.length ? (
                <Kosong>
                    Tidak ditemukan. Bila tamu, gunakan <b>Catat tamu</b> di bawah.
                </Kosong>
            ) : (
                <ul className="mt-1 space-y-2">
                    {groups.map((g) => (
                        <li key={g.key} className="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
                            {(g.list.length > 1 || g.head) && (
                                <div className="flex justify-between gap-2 bg-slate-50 px-4 py-2 text-sm text-slate-600">
                                    <span className="truncate">
                                        Keluarga <b>{g.head || '-'}</b>
                                    </span>
                                    <span className="shrink-0">
                                        {g.list.filter((m) => hadir(m.id)).length}/{g.list.length} hadir
                                        {g.region ? ` · ${g.region}` : ''}
                                    </span>
                                </div>
                            )}
                            <div className="divide-y divide-slate-100">
                                {g.list.map((m) => (
                                    <BarisJemaat
                                        key={m.id}
                                        m={m}
                                        on={a.present.has(m.id)}
                                        lain={a.present.has(m.id) ? null : a.hadirDiLain.get(m.id)}
                                        bold={hit.has(m.id)}
                                        onClick={() => toggle(m)}
                                    />
                                ))}
                            </div>
                        </li>
                    ))}
                    {total > groups.length && <li className="py-3 text-center text-sm text-slate-500">{total - groups.length} keluarga lainnya, ketik lebih lengkap.</li>}
                </ul>
            )}

            {toast && (
                <div className="fixed inset-x-0 bottom-[calc(72px+env(safe-area-inset-bottom))] z-40 px-4">
                    <div className="mx-auto flex max-w-2xl items-center gap-3 rounded-xl bg-slate-900 px-4 py-3 text-white shadow-lg">
                        <span className="min-w-0 flex-1 truncate text-sm">
                            <b>{toast.m.n}</b> {toast.lain ? `sudah hadir di ${toast.lain}` : toast.on ? 'hadir' : 'dibatalkan'}
                        </span>
                        {!toast.lain && (
                            <button className="text-sm font-semibold text-indigo-300" onClick={() => (a.mark(toast.m.id, !toast.on), setToast(null))}>
                                URUNGKAN
                            </button>
                        )}
                    </div>
                </div>
            )}
        </>
    );
}

function BarisJemaat({ m, on, lain, bold, onClick }) {
    return (
        <button onClick={onClick} className={cx('flex min-h-14 w-full items-center gap-3 px-4 py-3 text-left transition-colors active:bg-slate-100', on && 'bg-green-50', lain && 'bg-sky-50')}>
            <span
                className={cx(
                    'flex h-9 w-9 shrink-0 items-center justify-center rounded-full border-2 text-lg',
                    on ? 'border-green-600 bg-green-600 text-white' : lain ? 'border-sky-500 bg-sky-500 text-white' : 'border-slate-300',
                )}
            >
                {on || lain ? '✓' : ''}
            </span>
            <span className={cx('min-w-0 flex-1 text-base leading-snug', bold && 'font-semibold')}>
                {m.n}
                {m.a && <span className="font-normal text-slate-500"> ({m.a})</span>}
                {m.p && <span className="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-xs font-normal text-slate-500">pasif</span>}
            </span>
            <span className={cx('shrink-0 text-sm font-semibold', on ? 'text-green-700' : lain ? 'text-sky-700' : 'text-indigo-600')}>
                {on ? 'Hadir' : lain ? `Hadir · ${lain}` : 'Absen'}
            </span>
        </button>
    );
}

/** Jemaat + tamu satu sesi jadi baris daftar hadir, terbaru di atas. */
function barisHadir(present, guests, byId, nq) {
    const jemaat = [...present.entries()].map(([id, at]) => ({ m: byId.get(id), at })).filter((r) => r.m);
    const tamu = guests.map((g, i) => ({ g, at: g.at, key: g.id ?? 'g' + i, n: g.name || '' }));
    return [...jemaat.map((r) => ({ ...r, key: 'm' + r.m.id, n: r.m.n })), ...tamu]
        .filter((r) => !nq || (r.m ? r.m.s : norm(r.n)).includes(nq))
        .sort((x, y) => new Date(y.at || 0) - new Date(x.at || 0) || x.n.localeCompare(y.n));
}

/**
 * Daftar yang sudah hadir, terbaru di atas. Untuk KU I / KU II / Gabungan, kebaktian lain
 * pada tanggal yang sama ikut tampil per bagian (bisa dibuka-tutup); jemaat dihitung sekali.
 */
function DaftarHadir({ a, kebaktian }) {
    const [q, setQ] = useState('');
    const [msg, setMsg] = useState('');
    const byId = useMemo(() => new Map(a.members.map((m) => [m.id, m])), [a.members]);
    const nq = norm(q.trim());
    const bagian = useMemo(() => {
        const ini = { kebaktian: kebaktian || null, ini: true, rows: barisHadir(a.present, a.guests, byId, nq), jemaat: a.present.size, tamu: a.guests.length };
        const lain = a.others.map((o) => ({ kebaktian: o.kebaktian, rows: barisHadir(o.present, o.guests, byId, nq), jemaat: o.present.size, tamu: o.guests.length }));
        return [ini, ...lain];
    }, [a.present, a.guests, a.others, byId, nq, kebaktian]);
    const gabung = bagian.length > 1;
    const jemaatUnik = useMemo(() => {
        const s = new Set(a.present.keys());
        a.others.forEach((o) => o.present.forEach((_, id) => s.add(id)));
        return s.size;
    }, [a.present, a.others]);
    const tamuTotal = bagian.reduce((t, b) => t + b.tamu, 0);
    const semua = bagian.reduce((t, b) => t + b.jemaat + b.tamu, 0);

    const batal = (m) => confirm(`Batalkan kehadiran ${m.n}?`) && a.mark(m.id, false);
    const hapusTamu = async (g) => {
        if (!confirm(`Hapus tamu "${g.name}" dari absensi ini?`)) return;
        setMsg((await a.removeGuest(g.id)) ? `Tamu "${g.name}" dihapus.` : 'Gagal menghapus tamu. Pastikan HP online.');
    };

    return (
        <>
            <div className="grid grid-cols-2 gap-2">
                <Stat label={gabung ? 'Jemaat hari ini (dihitung 1x)' : 'Jemaat'} value={gabung ? jemaatUnik : a.present.size} />
                <Stat label={gabung ? 'Tamu hari ini' : 'Tamu'} value={gabung ? tamuTotal : a.guests.length} />
            </div>
            {semua > 8 && <input type="search" value={q} onChange={(e) => setQ(e.target.value)} placeholder="Cari di daftar hadir" className="input mt-3 text-base" />}
            {msg && <p className="mt-2 text-sm font-medium text-slate-600">{msg}</p>}

            {gabung ? (
                bagian.map((b) => (
                    <details key={b.kebaktian ?? 'ini'} open className="group mt-3 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
                        <summary className="flex cursor-pointer list-none items-center gap-2 bg-slate-50 px-4 py-3 [&::-webkit-details-marker]:hidden">
                            <span className="text-slate-400 transition-transform group-open:rotate-90">›</span>
                            <span className="flex-1 font-semibold">
                                {b.kebaktian ?? 'Kebaktian ini'}
                                {b.ini && <span className="ml-1.5 rounded bg-indigo-100 px-1.5 py-0.5 text-xs font-medium text-indigo-700">sedang dibuka</span>}
                            </span>
                            <span className="text-sm tabular-nums text-slate-500">
                                {b.jemaat} jemaat{b.tamu ? ` · ${b.tamu} tamu` : ''}
                            </span>
                        </summary>
                        {b.rows.length ? (
                            <ListHadir rows={b.rows} onBatal={b.ini ? batal : null} onHapusTamu={b.ini ? hapusTamu : null} />
                        ) : (
                            <p className="px-4 py-4 text-center text-sm text-slate-500">{nq ? 'Tidak ditemukan.' : 'Belum ada yang tercatat hadir.'}</p>
                        )}
                    </details>
                ))
            ) : !bagian[0].rows.length ? (
                <Kosong>{q.trim() ? 'Tidak ditemukan di daftar hadir.' : 'Belum ada yang tercatat hadir.'}</Kosong>
            ) : (
                <div className="mt-3 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
                    <ListHadir rows={bagian[0].rows} onBatal={batal} onHapusTamu={hapusTamu} />
                </div>
            )}
        </>
    );
}

/** Baris daftar hadir. Tanpa onBatal/onHapusTamu (kebaktian lain) hanya ditampilkan. */
function ListHadir({ rows, onBatal, onHapusTamu }) {
    return (
        <ul className="divide-y divide-slate-100">
            {rows.map(({ key, m, g, at }) => (
                <li key={key} className="flex items-center gap-3 px-4 py-3">
                    <span className="w-11 shrink-0 text-xs tabular-nums text-slate-400">{jam(at)}</span>
                    <span className="min-w-0 flex-1">
                        <span className="block truncate">
                            {m ? m.n : g.name}
                            {g && <span className="ml-1.5 rounded bg-amber-100 px-1.5 py-0.5 text-xs font-medium text-amber-800">Tamu</span>}
                        </span>
                        {m?.fh && m.fh !== m.n && <span className="block truncate text-xs text-slate-500">Kel. {m.fh}</span>}
                    </span>
                    {m
                        ? onBatal && (
                              <button onClick={() => onBatal(m)} className="shrink-0 rounded-lg px-2 py-1 text-sm text-red-600 active:bg-red-50">
                                  Batal
                              </button>
                          )
                        : onHapusTamu &&
                          g.id && (
                              <button onClick={() => onHapusTamu(g)} className="shrink-0 rounded-lg px-2 py-1 text-sm text-red-600 active:bg-red-50">
                                  Hapus
                              </button>
                          )}
                </li>
            ))}
        </ul>
    );
}

/** Kegiatan yang hanya dicatat jumlahnya (mis. Sekolah Minggu). */
function HitungJumlah({ a }) {
    const [n, setN] = useState(a.sess.manual_count ?? 0);
    const [msg, setMsg] = useState(null);
    const step = (d) => setN((v) => Math.max(0, (+v || 0) + d));

    return (
        <div className="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <label className="label text-base">Jumlah total hadir</label>
            <div className="flex items-center gap-3">
                <button onClick={() => step(-1)} className="btn btn-light h-16 w-16 shrink-0 px-0 text-3xl">
                    −
                </button>
                <input type="number" inputMode="numeric" min="0" value={n} onChange={(e) => setN(e.target.value)} className="input h-16 min-w-0 text-center text-3xl font-bold" />
                <button onClick={() => step(1)} className="btn btn-light h-16 w-16 shrink-0 px-0 text-3xl">
                    +
                </button>
            </div>
            <div className="mt-3 grid grid-cols-3 gap-2">
                {[5, 10, 20].map((d) => (
                    <button key={d} onClick={() => step(d)} className="btn btn-light py-2.5 text-sm">
                        +{d}
                    </button>
                ))}
            </div>
            <button onClick={async () => setMsg((await a.saveCount(+n || 0)) ? 'ok' : 'err')} className="btn btn-primary mt-4 w-full">
                Simpan jumlah
            </button>
            {msg && <p className={cx('mt-3 text-center text-sm font-medium', msg === 'ok' ? 'text-green-700' : 'text-red-600')}>{msg === 'ok' ? 'Jumlah hadir disimpan.' : 'Gagal menyimpan, periksa koneksi.'}</p>}
        </div>
    );
}

function Menu({ config, sess, onClose, onGanti }) {
    const csrf = document.querySelector('meta[name=csrf-token]')?.content;
    const item = 'flex w-full items-center px-5 py-4 text-left text-base active:bg-slate-50';
    return (
        <div className="fixed inset-0 z-50 flex items-end bg-black/40 sm:items-center sm:justify-center" onClick={onClose}>
            <div className="w-full overflow-hidden rounded-t-2xl bg-white pb-[env(safe-area-inset-bottom)] sm:max-w-sm sm:rounded-2xl" onClick={(e) => e.stopPropagation()}>
                <div className="border-b border-slate-100 px-5 py-3 text-sm text-slate-500">
                    Masuk sebagai <b className="text-slate-800">{config.user}</b>
                </div>
                <div className="divide-y divide-slate-100">
                    {sess && (
                        <button className={item} onClick={onGanti}>
                            Ganti tanggal / ibadah
                        </button>
                    )}
                    {sess && !sess.count_only && (
                        <a className={item} href={sess.qr_url}>
                            Tampilkan QR check-in mandiri
                        </a>
                    )}
                    {config.adminUrl && (
                        <a className={item} href={config.adminUrl}>
                            Buka panel admin
                        </a>
                    )}
                    <form method="POST" action={config.logoutUrl}>
                        <input type="hidden" name="_token" value={csrf} />
                        <button className={cx(item, 'text-red-600')}>Keluar</button>
                    </form>
                </div>
                <button className="w-full border-t border-slate-100 py-4 font-semibold text-slate-500" onClick={onClose}>
                    Tutup
                </button>
            </div>
        </div>
    );
}

function NavBtn({ active, onClick, href, icon, label }) {
    const paths = {
        search: 'M21 21l-4.3-4.3M10.5 18a7.5 7.5 0 100-15 7.5 7.5 0 000 15z',
        list: 'M9 6h11M9 12h11M9 18h11M4.5 6h.01M4.5 12h.01M4.5 18h.01',
        plus: 'M12 5v14M5 12h14',
    };
    const Tag = href ? 'a' : 'button';
    return (
        <Tag href={href} onClick={onClick} className={cx('flex flex-col items-center gap-0.5 py-2.5 text-xs font-medium', active ? 'text-indigo-600' : 'text-slate-500')}>
            <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2" strokeLinecap="round">
                <path d={paths[icon]} />
            </svg>
            {label}
        </Tag>
    );
}

const Stat = ({ label, value }) => (
    <div className="rounded-2xl bg-white px-4 py-3 shadow-sm ring-1 ring-slate-200">
        <div className="text-2xl font-bold tabular-nums">{value}</div>
        <div className="text-sm text-slate-500">{label}</div>
    </div>
);

const Kosong = ({ children, tone }) => (
    <div className={cx('mt-2 rounded-2xl bg-white p-6 text-center ring-1 ring-slate-200', tone === 'red' ? 'text-red-600' : 'text-slate-500')}>{children}</div>
);
