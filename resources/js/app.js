// Daftarkan service worker (PWA) untuk halaman absensi.
if ('serviceWorker' in navigator && document.querySelector('meta[name="sw"]')) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    });
}
