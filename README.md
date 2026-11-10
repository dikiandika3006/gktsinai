# Sistem Informasi Jemaat - GKT Jemaat Sinai Batu

Aplikasi web untuk mengelola data jemaat, pertumbuhan, kehadiran, tamu & follow-up, visitasi, dan peta persebaran jemaat.
Dibangun sesuai `SRS/SRS_Sistem_Informasi_Jemaat_GKT_Sinai_Batu.docx`.

**Teknologi:** PHP 8.3, Laravel 13, Filament 5 (panel admin), Blade + Tailwind (halaman HP), MySQL 8, spatie/laravel-permission,
maatwebsite/excel, barryvdh/laravel-dompdf, Leaflet + OpenStreetMap.

## Menjalankan di lokal (Laragon)

```bash
composer install
npm install && npm run build
cp .env.example .env && php artisan key:generate   # sesuaikan DB_*
php artisan migrate --seed                          # peran, komisi, jenis kegiatan, wilayah awal, super admin
php artisan db:seed --class=DemoSeeder              # (opsional) data contoh untuk mencoba
php artisan queue:work                              # untuk geocoding & notifikasi
```

- Panel admin: `/admin` (super admin awal dari `SIJ_ADMIN_EMAIL` / `SIJ_ADMIN_PASSWORD`, default `admin@gktsinai.test` / `password`).
  Super Admin & Pendeta **wajib memasang 2FA** (aplikasi authenticator) saat login pertama.
- Akun contoh (DemoSeeder, password `password`): `sekretariat@`, `pendeta@`, `majelis@`, `visitasi@`, `penyambut@gktsinai.test`.
- Absensi petugas (PWA): `/absen` - bisa "Tambahkan ke layar utama" dari Chrome Android.
- Form tamu: `/tamu`. Check-in QR mandiri: `/c/{token}` (QR ditampilkan dari menu Sesi Kegiatan).
- Tes: `php artisan test` (memakai database `gktsinai_test`).

## Peta modul ke SRS

| Modul | Menu / lokasi | Kode SRS |
|---|---|---|
| Data Jemaat | Jemaat, Kepala Keluarga (KK), Komisi, Import Data Jemaat, Kemungkinan Duplikat | DJ-01 s.d. DJ-17, DJ-20 |
| Peta | Peta Persebaran, Wilayah (gambar batas area) | DJ-14, DJ-15, PT-01 s.d. PT-06 |
| Pertumbuhan | Pertumbuhan Jemaat, Target Pertumbuhan, dashboard | PJ-01 s.d. PJ-06 |
| Kehadiran | Sesi Kegiatan, Absensi (HP petugas), QR mandiri, Laporan Kehadiran, Daftar Perhatian | KH-01 s.d. KH-09 |
| Tamu & FU | Jemaat Tamu (log kontak, tombol WA, konversi), form `/tamu` | TF-01 s.d. TF-09 |
| Visitasi | Jadwal Kunjungan (laporan + tanda rahasia), Usulan & Cakupan Visitasi | VS-01 s.d. VS-05, VS-07 |
| Keamanan | Peran per modul & wilayah, log audit, 2FA, ekspor kontak terbatas | Bagian 3 & 6 |

Belum dibuat (prioritas N di SRS): DJ-18 foto, DJ-19 form update mandiri, VS-06 urutan rute, PT-07 ekspor peta, integrasi WhatsApp gateway
(saat ini tombol "buka WA" dengan template pesan).

## Aturan penting

- Kode jemaat `SIBxxxx` dan KK `KK-xxx` dibuat otomatis melanjutkan nomor terakhir.
- Status dipisah: **keanggotaan** (sidi/baptis/anggota/simpatisan/keluar/meninggal) dan **keaktifan** (aktif/pasif).
- Umur & kelompok kategorial selalu dihitung dari tanggal lahir. HP disimpan format `+62...`.
- Batas umur/kelamin komisi diatur di menu **Komisi** (tanpa programmer). Sistem mengusulkan, admin menetapkan.
- Catatan visitasi bertanda rahasia hanya terbaca pendeta & penulisnya; majelis hanya melihat ringkasan.
- Ekspor Excel berisi HP & alamat hanya untuk Super Admin, Sekretariat, Pendeta.
- Pengaturan lain (ambang absen 3x, usia lansia, template WA, dsb.) di `config/sij.php`.

## Langkah migrasi data (bagian 7 SRS)

1. Rapikan alamat KK di Excel (14 orang "Belum Ada Alamat", KK-002 yang terpecah).
2. Menu **Import Data Jemaat** -> unggah `Data_Jemaat_Lengkap.xlsx`, pilih sheet *Semua Data Jemaat*, periksa pemetaan kolom, **Pratinjau**.
   Importer otomatis: membuang tanggal `-`/`1900-01-01`, Title Case nama kapital, mencocokkan ejaan wilayah yang salah ketik,
   memetakan status lama ke keanggotaan + keaktifan, membuat baris mutasi (nikah, atestasi, wafat) dan baptis/sidi "belum diketahui".
3. Cek menu **Kemungkinan Duplikat** (mis. Chandra Kurniawan) dan gabungkan bila perlu.
4. Jalankan queue worker agar geocoding berjalan (`php artisan sij:geocode`); pin manual KK berstatus perkiraan/gagal.
5. Gambar batas wilayah di menu **Wilayah**, lalu di daftar KK pilih semua -> **Terapkan wilayah usulan**.
6. Komisi -> anggota -> **Usulan dari umur & kelamin** untuk keanggotaan komisi awal.
7. Cocokkan total per status di menu **Pertumbuhan** dengan sheet *Rekapitulasi Statistik*, sekretariat memverifikasi sampel, go-live.

## Deploy ke hosting

Syarat: PHP 8.3, MySQL 8 / MariaDB 10.11, Nginx/Apache (document root `public/`), SSL, cron, dan queue worker.

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force --seed
php artisan storage:link
php artisan optimize
```

- `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://...` (HTTPS dipaksa di produksi), isi `NOMINATIM_EMAIL`.
- Cron: `* * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1`
  (pengingat FU tiap jam, geocoding 01.00, daftar perhatian 02.00, backup DB 03.00).
- Queue worker (supervisor/systemd): `php artisan queue:work --tries=3`.
- Backup: `sij:backup` menyimpan `storage/app/backups/*.sql.gz` 30 hari; isi `BACKUP_DISK` (mis. disk S3/FTP di
  `config/filesystems.php`) untuk salinan di lokasi terpisah. Uji restore tiap 3 bulan.
