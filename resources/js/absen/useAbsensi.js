import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { isAuthError, norm, request, store } from './lib';

/**
 * Status satu sesi absensi. Centang disimpan dulu di HP (antrean localStorage)
 * lalu dikirim ke server, jadi tetap jalan saat sinyal di gedung lemah.
 */
export function useAbsensi(openUrl, pilihan) {
    const [sess, setSess] = useState(null);
    const [phase, setPhase] = useState('idle'); // idle | loading | ready | error
    const [error, setError] = useState('');
    const [members, setMembers] = useState([]);
    const [present, setPresent] = useState(() => new Map());
    const [guests, setGuests] = useState([]);
    // Kehadiran kebaktian lain hari itu (KU I/KU II/Gabungan): [{kebaktian, present: Map, guests}].
    const [others, setOthers] = useState([]);
    const [pending, setPending] = useState(0);
    const [online, setOnline] = useState(navigator.onLine);
    const [authExpired, setAuthExpired] = useState(false);

    const queueRef = useRef([]);
    const sessRef = useRef(null);
    const syncing = useRef(false);

    const qKey = (s) => `absen-queue-${s.id}`;
    const dKey = (s) => `absen-data-${s.id}`;

    const saveQueue = (q) => {
        queueRef.current = q;
        if (sessRef.current) store.set(qKey(sessRef.current), q);
        setPending(q.length);
    };

    const applyData = useCallback((data) => {
        setMembers(data.members.map((m) => ({ ...m, s: norm([m.n, m.a, m.c, m.fh, m.fk].join(' ')) })));
        // Format lama menyimpan daftar id; format baru {id: waktu}.
        const map = new Map(Array.isArray(data.present) ? data.present.map((id) => [id, null]) : Object.entries(data.present || {}).map(([k, v]) => [+k, v]));
        queueRef.current.forEach((it) => (it.undo ? map.delete(it.member_id) : map.set(it.member_id, it.at)));
        setPresent(map);
        setGuests((data.guests || []).map((g) => (typeof g === 'string' ? { id: null, name: g, at: null } : g)));
        setOthers(
            (data.others || []).map((o) => ({
                kebaktian: o.kebaktian,
                present: new Map(Object.entries(o.present || {}).map(([k, v]) => [+k, v])),
                guests: o.guests || [],
            })),
        );
    }, []);

    const sync = useCallback(async () => {
        const s = sessRef.current;
        const batch = queueRef.current.slice();
        if (!s || syncing.current || !batch.length || !navigator.onLine) return;
        syncing.current = true;
        try {
            const r = await request(s.sync_url, { method: 'POST', body: { items: batch } });
            if (isAuthError(r)) setAuthExpired(true);
            if (r.ok && sessRef.current === s) {
                saveQueue(queueRef.current.filter((it) => !batch.some((b) => b.member_id === it.member_id && b.at === it.at)));
            }
        } catch {
            /* coba lagi nanti */
        }
        syncing.current = false;
    }, []);

    // Buka sesi tiap kali tanggal/ibadah dipilih.
    useEffect(() => {
        if (!pilihan) return;
        let batal = false;
        const key = `absen-open-${pilihan.type}-${pilihan.date}-${pilihan.kebaktian || ''}`;
        setPhase('loading');
        setError('');
        setMembers([]);
        setPresent(new Map());
        setGuests([]);
        setOthers([]);

        (async () => {
            let s;
            try {
                const r = await request(openUrl, {
                    method: 'POST',
                    body: { activity_type_id: pilihan.type, date: pilihan.date, kebaktian: pilihan.kebaktian || null },
                });
                if (isAuthError(r)) {
                    setAuthExpired(true);
                    throw new Error('auth');
                }
                if (!r.ok) throw new Error(r.status);
                s = await r.json();
                store.set(key, s);
            } catch {
                s = store.get(key, null);
            }
            if (batal) return;
            if (!s) {
                setPhase('error');
                setError('Tidak ada koneksi, dan ibadah ini belum pernah dibuka di HP ini.');
                return;
            }
            sessRef.current = s;
            setSess(s);
            queueRef.current = store.get(qKey(s), []);
            setPending(queueRef.current.length);
            if (s.count_only) return setPhase('ready');

            const cached = store.get(dKey(s), null);
            if (cached) {
                applyData(cached);
                setPhase('ready');
            }
            try {
                const r = await request(s.data_url);
                if (!r.ok) throw new Error(r.status);
                const data = await r.json();
                if (batal) return;
                store.set(dKey(s), data);
                applyData(data);
                setPhase('ready');
                sync();
            } catch {
                if (!batal && !cached) {
                    setPhase('error');
                    setError('Gagal memuat daftar jemaat. Periksa koneksi lalu coba lagi.');
                }
            }
        })();

        return () => {
            batal = true;
        };
    }, [openUrl, pilihan, applyData, sync]);

    // Ambil ulang daftar dari server: tamu dari form/QR dan absen petugas lain ikut muncul.
    const refresh = useCallback(async () => {
        const s = sessRef.current;
        if (!s || s.count_only || !navigator.onLine || document.hidden) return;
        try {
            const r = await request(s.data_url);
            if (!r.ok || sessRef.current !== s) return;
            const data = await r.json();
            store.set(dKey(s), data);
            applyData(data);
        } catch {
            /* tetap pakai data yang ada */
        }
    }, [applyData]);

    useEffect(() => {
        const on = () => {
            setOnline(true);
            sync();
        };
        const off = () => setOnline(false);
        const visible = () => !document.hidden && sync().then(refresh);
        window.addEventListener('online', on);
        window.addEventListener('offline', off);
        document.addEventListener('visibilitychange', visible);
        const t = setInterval(sync, 15000);
        const t2 = setInterval(refresh, 30000);
        return () => {
            window.removeEventListener('online', on);
            window.removeEventListener('offline', off);
            document.removeEventListener('visibilitychange', visible);
            clearInterval(t);
            clearInterval(t2);
        };
    }, [sync, refresh]);

    const mark = useCallback(
        (id, on) => {
            const at = new Date().toISOString();
            setPresent((prev) => {
                const next = new Map(prev);
                on ? next.set(id, at) : next.delete(id);
                return next;
            });
            saveQueue([...queueRef.current.filter((it) => it.member_id !== id), { member_id: id, at, undo: !on }]);
            navigator.vibrate?.(30);
            sync();
        },
        [sync],
    );

    const removeGuest = useCallback(async (id) => {
        const s = sessRef.current;
        const r = await request(s.guest_remove_url.replace(/\/0$/, '/' + id), { method: 'DELETE' }).catch(() => null);
        if (!r || !r.ok) return false;
        setGuests((prev) => {
            const next = prev.filter((g) => g.id !== id);
            const cached = store.get(dKey(s), null);
            if (cached) store.set(dKey(s), { ...cached, guests: next });
            return next;
        });
        return true;
    }, []);

    const saveCount = useCallback(async (n) => {
        const s = sessRef.current;
        const r = await request(s.count_url, { method: 'POST', body: { manual_count: n } }).catch(() => null);
        if (r?.ok) {
            const next = { ...s, manual_count: n };
            sessRef.current = next;
            setSess(next);
        }
        if (isAuthError(r)) setAuthExpired(true);
        return !!r?.ok;
    }, []);

    // id jemaat -> nama kebaktian lain tempat ia sudah hadir.
    const hadirDiLain = useMemo(() => {
        const map = new Map();
        others.forEach((o) => o.present.forEach((_, id) => !map.has(id) && map.set(id, o.kebaktian)));
        return map;
    }, [others]);

    return { sess, phase, error, members, present, guests, others, hadirDiLain, pending, online, authExpired, mark, removeGuest, saveCount };
}
