// Daftarkan service worker (PWA) untuk halaman absensi.
const sw = document.querySelector('meta[name="sw"]');
if ('serviceWorker' in navigator && sw) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register(sw.content).catch(() => {});
    });
}
