<?php

namespace App\Support;

/**
 * Konten halaman Tentang. Diedit manual tiap ada perubahan aplikasi (pola sama dengan project lain).
 * ATURAN mendokumentasikan aturan yang sudah berlaku; RIWAYAT adalah changelog, terbaru di paling atas.
 */
class Tentang
{
    public const ATURAN = [
        [
            'icon' => 'heroicon-o-identification',
            'judul' => 'Data Jemaat & KK',
            'aturan' => [
                'Kode jemaat dibuat otomatis berformat SIBxxxx, melanjutkan nomor terakhir. Kode KK berformat KK-xxx.',
                'Pin lokasi KK dicari otomatis dari alamat. Status: Tepat (tingkat jalan), Perkiraan (tingkat kelurahan/wilayah, perlu dicek), Gagal (pasang manual), Pin manual (tidak diubah sistem).',
                'Status keanggotaan: Sidi, Baptis, Anggota (sakramen belum dilengkapi), Simpatisan, Atestasi Keluar, Meninggal. Status keaktifan: Aktif / Pasif.',
                'Status Atestasi Keluar dan Meninggal otomatis tidak punya status keaktifan.',
                'Nomor HP disimpan dalam format internasional (+62...) supaya bisa langsung dibuka di WhatsApp.',
                'Sistem memberi peringatan kemungkinan duplikat bila nama mirip dan tanggal lahir sama; jemaat ganda bisa digabung di menu Duplikat.',
            ],
        ],
        [
            'icon' => 'heroicon-o-arrow-up-tray',
            'judul' => 'Import Data Lama (Excel)',
            'aturan' => [
                'Status lama dipetakan: Jemaat Aktif → Anggota Aktif, Jemaat Non-Aktif → Anggota Pasif, Non-Jemaat Aktif → Simpatisan Aktif, Non-Jemaat Non-Aktif → Simpatisan Pasif.',
                'Teks asli kolom Kategori/Status, Wilayah, ID KK, dan Alamat tetap disimpan di bagian "Data asli Excel (lama)" pada tiap jemaat.',
                'ID KK "Belum Ada Alamat" tidak dibuat sebagai KK; wilayah "Belum Didefinisikan / Belum Ada Detail Jalan" dikosongkan.',
                'Nama yang ditulis huruf kapital semua diubah ke Huruf Awal Kapital. Tanggal 1900-01-01 dianggap kosong.',
                'Wilayah yang salah ketik dicocokkan ke master wilayah terdekat (kemiripan ≥ 80%).',
                'Import ulang file yang sama tidak menggandakan data: jemaat dicocokkan berdasarkan kode SIB.',
            ],
        ],
        [
            'icon' => 'heroicon-o-user-group',
            'judul' => 'Komisi',
            'aturan' => [
                'Usulan komisi tiap jemaat dihitung dari umur dan jenis kelamin sesuai batas di master Komisi (bisa diubah admin).',
                'Komisi Sekolah Minggu hanya mencatat jumlah hadir total, bukan per anak.',
            ],
        ],
        [
            'icon' => 'heroicon-o-clipboard-document-check',
            'judul' => 'Kehadiran',
            'aturan' => [
                'Jemaat yang tidak hadir 3 kali berturut-turut (Ibadah Umum atau komisinya) masuk Daftar Perhatian.',
                'Jemaat yang tidak hadir selama 3 bulan diusulkan menjadi Pasif.',
                'Absensi bisa lewat HP petugas atau self check-in dengan QR per sesi ibadah.',
            ],
        ],
        [
            'icon' => 'heroicon-o-hand-raised',
            'judul' => 'Tamu & Follow-Up',
            'aturan' => [
                'Tamu baru wajib di-follow-up dalam 48 jam; PIC yang terlambat mendapat pengingat.',
                'Tahap follow-up: Baru → Sudah Dihubungi → Datang Lagi → Rutin (3+ kali) → Jadi Jemaat / Tidak Lanjut.',
            ],
        ],
        [
            'icon' => 'heroicon-o-home',
            'judul' => 'Visitasi',
            'aturan' => [
                'Usulan visitasi memprioritaskan lansia (70+ tahun) dan KK yang belum dikunjungi lebih dari 12 bulan.',
                'Laporan visitasi yang ditandai RAHASIA hanya bisa dibaca penulisnya dan peran yang berhak membaca catatan rahasia.',
            ],
        ],
        [
            'icon' => 'heroicon-o-shield-check',
            'judul' => 'Hak Akses & Keamanan',
            'aturan' => [
                'Peran: Super Admin, Sekretariat, Pendeta, Majelis, Koordinator Wilayah, Koordinator Komisi, Tim Visitasi, Petugas Penyambut.',
                'Nomor HP dan alamat hanya terlihat oleh Super Admin, Sekretariat, Pendeta, Majelis, Koordinator Wilayah, dan Tim Visitasi.',
                'Koordinator Wilayah hanya melihat KK di wilayahnya.',
                'Super Admin dan Pendeta wajib memakai verifikasi 2 langkah (aplikasi authenticator).',
                'Setiap perubahan data dicatat di Log Audit.',
            ],
        ],
        [
            'icon' => 'heroicon-o-circle-stack',
            'judul' => 'Backup Database',
            'aturan' => [
                'Backup otomatis setiap hari pukul 03.00, disimpan 30 hari.',
                'Sebelum restore, sistem otomatis membuat backup "_pre-restore" sehingga restore bisa dibatalkan dengan me-restore file itu.',
            ],
        ],
    ];

    public const RIWAYAT = [
        [
            'tanggal' => '10 November 2026',
            'perubahan' => [
                'Menu ditata ulang: Jemaat, Ibadah & Kehadiran, Pelayanan, Pastoral, Laporan, Pengaturan; alamat halaman berbahasa Indonesia (mis. /admin/jemaat, /admin/kk, /admin/kunjungan).',
                'Profil jemaat: tab Visitasi, Kehadiran (persentase hadir 3 bulan, terakhir hadir), dan Pelayanan (riwayat & tugas berikutnya).',
                'Visitasi: pendamping bisa dipilih dari data jemaat; laporan mencentang anggota KK yang hadir.',
                'Dashboard per peran: Pelayanan Minggu ini, Kunjungan saya, Wilayah saya, Komisi saya, Cakupan visitasi per wilayah, Hadir pertama kali & kembali hadir.',
                'Grafik kehadiran per kebaktian (KU I / KU II / Gabungan) dengan total harian (jemaat dihitung sekali).',
                'Jadwal Bulanan memberi peringatan bila seseorang bertugas dobel atau lebih dari 3 Minggu sebulan.',
                'Pemeliharaan dibagi per tab: Database, Data Jemaat, Peta, Paket Data.',
                'Pin alamat KK (OpenStreetMap): dicari bertahap (alamat dibersihkan → nama jalan → wilayah); tombol "Google Maps" di KK, daftar KK, dan popup Peta Jemaat; tombol "Proses pin alamat" di Pemeliharaan (tanpa SSH).',
                'Absensi (PWA): Ibadah Umum dipilih per kebaktian (KU I / KU II / Gabungan). Daftar Hadir menampilkan semua kebaktian hari itu per bagian yang bisa dibuka-tutup; jemaat yang sudah hadir di satu kebaktian cukup diabsen sekali dan dihitung sekali.',
                'Perbaikan error "Attempt to read property alias on null" saat edit anggota di halaman KK.',
                'Data jemaat diperbarui dari sheet "Data Jemaat Komprehensif" (385 orang, 129 KK); SIB0337 digabung ke SIB0121 sesuai catatan Excel; kolom Catatan ikut diimpor.',
                'Import Excel hanya membaca sheet yang dipilih dan memakai nilai tersimpan dari kolom rumus, sehingga file dengan banyak sheet tidak lagi kehabisan memori.',
                'Data jemaat asli diimpor dari "Data Jemaat Lengkap.xlsx", menggantikan data contoh.',
                'Tabel jemaat ditambah kolom Baptis di GKT, Sidi di GKT, dan teks asli Excel (status, wilayah, ID KK, alamat) supaya tidak ada data Excel yang hilang.',
                'Menu baru Tentang: info aplikasi, aturan sistem, riwayat perubahan, serta Backup & Restore database (Alt+F7, khusus Super Admin, pakai PIN).',
                'Menu baru Pemeliharaan Sistem: update database, impor Excel, bersihkan cache, dan storage link tanpa perlu SSH.',
                'Kolom "Cari menu..." di sidebar untuk mencari menu dengan cepat.',
                'Backup harian sekarang memakai PHP murni sehingga jalan di shared hosting.',
            ],
        ],
    ];
}
