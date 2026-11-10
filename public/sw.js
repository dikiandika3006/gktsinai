// Service worker PWA absensi: halaman absensi tetap terbuka saat sinyal lemah.
const CACHE = 'sij-absen-v2';

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (e) => {
    e.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))));
    self.clients.claim();
});

self.addEventListener('fetch', (e) => {
    const req = e.request;
    if (req.method !== 'GET') return;
    const url = new URL(req.url);
    if (url.origin !== location.origin) return;

    // Aset hasil build: cache-first.
    if (url.pathname.includes('/build/') || url.pathname.endsWith('.png')) {
        e.respondWith(caches.match(req).then((hit) => hit || fetch(req).then((res) => {
            const copy = res.clone();
            caches.open(CACHE).then((c) => c.put(req, copy));
            return res;
        })));
        return;
    }

    // Halaman absensi & datanya: network-first, fallback ke cache.
    if (url.pathname.includes('/absen')) {
        e.respondWith(fetch(req).then((res) => {
            if (res.ok) {
                const copy = res.clone();
                caches.open(CACHE).then((c) => c.put(req, copy));
            }
            return res;
        }).catch(() => caches.match(req)));
    }
});
