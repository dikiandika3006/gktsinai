// Utilitas kecil layar absensi: penyimpanan lokal (offline), HTTP, pencarian.

export const store = {
    get(k, d) {
        try {
            return JSON.parse(localStorage.getItem(k)) ?? d;
        } catch {
            return d;
        }
    },
    set(k, v) {
        try {
            localStorage.setItem(k, JSON.stringify(v));
        } catch {
            /* penyimpanan penuh / diblokir: abaikan */
        }
    },
};

const CSRF = document.querySelector('meta[name=csrf-token]')?.content;

export function request(url, { method = 'GET', body } = {}) {
    return fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            ...(body ? { 'Content-Type': 'application/json' } : {}),
            ...(method !== 'GET' ? { 'X-CSRF-TOKEN': CSRF } : {}),
        },
        body: body ? JSON.stringify(body) : undefined,
    });
}

export const isAuthError = (r) => r && (r.status === 401 || r.status === 419);

/** Huruf kecil tanpa aksen agar "Andre" cocok dengan "André". */
export const norm = (s) => (s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');

const HARI = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
const BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

export function formatTanggal(iso) {
    const [y, m, d] = iso.split('-').map(Number);
    const t = new Date(y, m - 1, d);
    return `${HARI[t.getDay()]}, ${d} ${BULAN[m - 1]} ${y}`;
}

export function geserTanggal(iso, hari) {
    const [y, m, d] = iso.split('-').map(Number);
    const t = new Date(y, m - 1, d + hari);
    return [t.getFullYear(), String(t.getMonth() + 1).padStart(2, '0'), String(t.getDate()).padStart(2, '0')].join('-');
}

export const jam = (iso) => (iso ? new Date(iso).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) : '');

/** Cari jemaat (nama, alias, kode, kepala keluarga, kode KK) lalu kelompokkan per keluarga. */
export function cariKeluarga(members, query, limit = 15) {
    const q = norm(query.trim());
    if (!q) return { groups: [], total: 0, hit: new Set() };
    const tokens = q.split(/\s+/);
    const matched = members.filter((m) => tokens.every((t) => m.s.includes(t)));
    // Yang cocok dengan nama orangnya tampil lebih dulu daripada yang cocok lewat kepala keluarga.
    matched.sort((a, b) => norm(b.n).includes(q) - norm(a.n).includes(q) || a.n.localeCompare(b.n));

    const byFamily = new Map();
    members.forEach((m) => m.f && (byFamily.get(m.f) ?? byFamily.set(m.f, []).get(m.f)).push(m));

    const groups = new Map();
    matched.forEach((m) => {
        const key = m.f ? 'f' + m.f : 'm' + m.id;
        if (groups.has(key)) return;
        const list = (m.f ? byFamily.get(m.f) : [m]).slice();
        list.sort((a, b) => (b.n === b.fh) - (a.n === a.fh) || a.n.localeCompare(b.n));
        groups.set(key, { key, head: m.fh, region: m.r, list });
    });
    const all = [...groups.values()];
    return { groups: all.slice(0, limit), total: all.length, hit: new Set(matched.map((m) => m.id)) };
}
