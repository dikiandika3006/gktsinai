<?php

namespace App\Support;

/**
 * Daftar pilihan (label bahasa Indonesia) yang dipakai di seluruh sistem.
 */
class Opsi
{
    public const KELAMIN = ['L' => 'Laki-laki', 'P' => 'Perempuan'];

    public const HUBUNGAN = [
        'kepala' => 'Kepala Keluarga',
        'suami' => 'Suami',
        'istri' => 'Istri',
        'anak' => 'Anak',
        'orang_tua' => 'Orang Tua',
        'lainnya' => 'Lainnya',
    ];

    public const KEANGGOTAAN = [
        'sidi' => 'Anggota Sidi',
        'baptis' => 'Anggota Baptis',
        'anggota' => 'Anggota (sakramen belum dilengkapi)',
        'simpatisan' => 'Simpatisan / Belum Anggota',
        'keluar' => 'Atestasi Keluar',
        'meninggal' => 'Meninggal',
    ];

    /** Status keanggotaan yang dihitung sebagai anggota resmi. */
    public const ANGGOTA_RESMI = ['sidi', 'baptis', 'anggota'];

    /** Status yang masih tercatat sebagai bagian jemaat (belum keluar/meninggal). */
    public const MASIH_TERCATAT = ['sidi', 'baptis', 'anggota', 'simpatisan'];

    public const KEAKTIFAN = ['aktif' => 'Aktif', 'pasif' => 'Pasif'];

    public const JENIS_EVENT = [
        'baptis' => 'Baptis',
        'sidi' => 'Sidi',
        'nikah' => 'Nikah',
        'atestasi_masuk' => 'Atestasi Masuk',
        'atestasi_keluar' => 'Atestasi Keluar',
        'wafat' => 'Wafat',
    ];

    public const STATUS_DATA_EVENT = ['lengkap' => 'Lengkap', 'belum_diketahui' => 'Belum diketahui'];

    public const GEOCODE = [
        'belum' => 'Belum diproses',
        'tepat' => 'Tepat',
        'perkiraan' => 'Perkiraan',
        'gagal' => 'Gagal',
        'manual' => 'Pin manual',
    ];

    public const TAHAP_FU = [
        'baru' => 'Baru',
        'dihubungi' => 'Sudah Dihubungi',
        'datang_lagi' => 'Datang Lagi',
        'rutin' => 'Rutin (3+ kali)',
        'jemaat' => 'Jadi Jemaat',
        'tidak_lanjut' => 'Tidak Lanjut',
    ];

    public const CARA_FU = ['wa' => 'WhatsApp', 'telepon' => 'Telepon', 'kunjungan' => 'Kunjungan'];

    public const SUMBER_INFO = [
        'keluarga' => 'Keluarga / teman',
        'media_sosial' => 'Media sosial',
        'lewat' => 'Lewat depan gereja',
        'undangan' => 'Undangan acara',
        'pindah' => 'Pindah domisili',
        'lainnya' => 'Lainnya',
    ];

    public const JENIS_VISITASI = [
        'rutin' => 'Rutin',
        'sakit' => 'Sakit',
        'duka' => 'Duka',
        'ulang_tahun' => 'Ulang Tahun',
        'jemaat_baru' => 'Jemaat Baru',
        'pasif' => 'Jemaat Pasif',
    ];

    public const STATUS_VISITASI = ['rencana' => 'Direncanakan', 'selesai' => 'Selesai', 'batal' => 'Batal'];

    public const KELOMPOK_UMUR = [
        'anak' => 'Anak (0-12)',
        'remaja' => 'Remaja (13-17)',
        'pemuda' => 'Pemuda (18-30)',
        'dewasa' => 'Dewasa (31-59)',
        'lansia' => 'Lansia (60+)',
    ];

    /** Rentang umur [min, max] untuk tiap kelompok umur. */
    public const RENTANG_UMUR = [
        'anak' => [0, 12],
        'remaja' => [13, 17],
        'pemuda' => [18, 30],
        'dewasa' => [31, 59],
        'lansia' => [60, 200],
    ];

    public const PERAN = [
        'super_admin' => 'Super Admin',
        'sekretariat' => 'Sekretariat',
        'pendeta' => 'Pendeta',
        'majelis' => 'Majelis',
        'koordinator_wilayah' => 'Koordinator Wilayah',
        'koordinator_komisi' => 'Koordinator Komisi',
        'tim_visitasi' => 'Tim Visitasi',
        'penyambut' => 'Petugas Penyambut',
        'petugas_absensi' => 'Petugas Absensi',
    ];

    public static function label(array $opsi, ?string $key): ?string
    {
        return $key === null ? null : ($opsi[$key] ?? $key);
    }
}
