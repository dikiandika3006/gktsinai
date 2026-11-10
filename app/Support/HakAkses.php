<?php

namespace App\Support;

use App\Filament\Pages;
use App\Models;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

/**
 * Hak akses menu per peran (menu Pengaturan → Hak Akses).
 *
 * Tiap sel peran × menu bernilai Tidak / Lihat / Kelola. Sel yang belum pernah diatur
 * memakai aturan bawaan aplikasi (perilaku lama), jadi peran lama tidak berubah sampai
 * admin mengubah matriksnya. Menghapus data tetap mengikuti aturan bawaan (admin/sekretariat).
 */
final class HakAkses
{
    public const TIDAK = 0;

    public const LIHAT = 1;

    public const KELOLA = 2;

    public const LEVEL = [self::TIDAK => 'Tidak', self::LIHAT => 'Lihat', self::KELOLA => 'Kelola'];

    /** Peran yang tidak ikut diatur: super admin selalu penuh, petugas absensi hanya layar absensi. */
    public const PERAN_TETAP = ['super_admin', 'petugas_absensi'];

    /** Peran yang datanya dibatasi (wilayah/komisi/KK tugas) walaupun boleh membuka menu jemaat. */
    public const PERAN_TERBATAS = ['koordinator_wilayah', 'koordinator_komisi', 'tim_visitasi'];

    /** Bawaan peran baru yang dibuat lewat kode (bisa diubah di matriks). */
    public const BAWAAN_PERAN = [
        'tim_ibadah' => [
            'jemaat' => self::LIHAT, 'kk' => self::LIHAT,
            'sesi-kegiatan' => self::KELOLA, 'absensi' => self::LIHAT, 'laporan-kehadiran' => self::LIHAT,
            'jadwal-ibadah' => self::KELOLA, 'jadwal-bulanan' => self::LIHAT, 'nama-pelayan' => self::KELOLA,
            'beban-pelayanan' => self::LIHAT, 'kaleidoskop' => self::LIHAT,
            'struktur-organisasi' => self::LIHAT, 'unit-organisasi' => self::KELOLA,
        ],
    ];

    /** Sedang menghitung aturan bawaan: cegah helper User memanggil matriks lagi (rekursi). */
    private static bool $hitungBawaan = false;

    /** @var array<string, int> */
    private static array $memo = [];

    /**
     * Daftar menu yang bisa diatur, dikelompokkan seperti sidebar.
     * model: diperiksa lewat policy (Gate); halaman: aturan bawaan berupa closure.
     *
     * @return array<string, array{label: string, grup: string, model?: class-string, halaman?: class-string, kelola: bool, bawaan: Closure(User): int}>
     */
    public static function menu(): array
    {
        $halaman = fn (Closure $boleh) => fn (User $u) => $boleh($u) ? self::LIHAT : self::TIDAK;

        return [
            'jemaat' => self::model('Data Jemaat', 'Jemaat', Models\Member::class),
            'kk' => self::model('Kepala Keluarga (KK)', 'Jemaat', Models\Family::class),
            'komisi' => self::model('Komisi', 'Jemaat', Models\Commission::class),
            'peta-jemaat' => ['label' => 'Peta Jemaat (alamat)', 'grup' => 'Jemaat', 'halaman' => Pages\PetaJemaat::class, 'kelola' => false,
                'bawaan' => $halaman(fn (User $u) => $u->lihatKontak())],
            'duplikat-jemaat' => ['label' => 'Duplikat Jemaat', 'grup' => 'Jemaat', 'halaman' => Pages\DuplikatJemaat::class, 'kelola' => false,
                'bawaan' => $halaman(fn (User $u) => $u->peran('sekretariat'))],

            'sesi-kegiatan' => self::model('Sesi Ibadah & Kegiatan', 'Ibadah & Kehadiran', Models\ActivitySession::class),
            'absensi' => ['label' => 'Absensi (HP petugas)', 'grup' => 'Ibadah & Kehadiran', 'kelola' => false,
                'bawaan' => $halaman(fn (User $u) => $u->kelolaKehadiran())],
            'laporan-kehadiran' => ['label' => 'Laporan Kehadiran', 'grup' => 'Ibadah & Kehadiran', 'halaman' => Pages\LaporanKehadiran::class, 'kelola' => false,
                'bawaan' => $halaman(fn (User $u) => $u->lihatSemuaJemaat() || $u->peran('koordinator_wilayah', 'koordinator_komisi'))],

            'jadwal-ibadah' => self::model('Jadwal Pelayanan', 'Pelayanan', Models\WorshipSchedule::class),
            'jadwal-bulanan' => ['label' => 'Jadwal Bulanan', 'grup' => 'Pelayanan', 'halaman' => Pages\JadwalBulanan::class, 'kelola' => false,
                'bawaan' => $halaman(fn (User $u) => $u->lihatSemuaJemaat())],
            'nama-pelayan' => self::model('Nama Pelayan', 'Pelayanan', Models\ServantAlias::class),
            'beban-pelayanan' => ['label' => 'Beban Pelayanan', 'grup' => 'Pelayanan', 'halaman' => Pages\BebanPelayanan::class, 'kelola' => false,
                'bawaan' => $halaman(fn (User $u) => $u->lihatSemuaJemaat())],
            'kaleidoskop' => ['label' => 'Kaleidoskop Pelayanan', 'grup' => 'Pelayanan', 'halaman' => Pages\KaleidoskopPelayanan::class, 'kelola' => false,
                'bawaan' => $halaman(fn (User $u) => $u->lihatSemuaJemaat())],
            'struktur-organisasi' => ['label' => 'Struktur Organisasi', 'grup' => 'Pelayanan', 'halaman' => Pages\StrukturOrganisasi::class, 'kelola' => false,
                'bawaan' => $halaman(fn (User $u) => $u->lihatSemuaJemaat())],
            'unit-organisasi' => self::model('Unit Organisasi & Periode', 'Pelayanan', Models\OrgUnit::class),

            'tamu' => self::model('Tamu & Follow-up', 'Pastoral', Models\Guest::class),
            'kunjungan' => self::model('Visitasi', 'Pastoral', Models\Visit::class),
            'daftar-perhatian' => ['label' => 'Daftar Perhatian', 'grup' => 'Pastoral', 'halaman' => Pages\DaftarPerhatian::class, 'kelola' => false,
                'bawaan' => $halaman(fn (User $u) => $u->lihatSemuaJemaat() || $u->peran('koordinator_wilayah', 'koordinator_komisi'))],
            'usulan-visitasi' => ['label' => 'Usulan Visitasi', 'grup' => 'Pastoral', 'halaman' => Pages\UsulanVisitasi::class, 'kelola' => false,
                'bawaan' => $halaman(fn (User $u) => $u->peran('sekretariat', 'pendeta', 'majelis', 'koordinator_wilayah'))],

            'pertumbuhan' => ['label' => 'Pertumbuhan', 'grup' => 'Laporan', 'halaman' => Pages\Pertumbuhan::class, 'kelola' => false,
                'bawaan' => $halaman(fn (User $u) => $u->lihatSemuaJemaat())],
            'target-pertumbuhan' => self::model('Target Pertumbuhan', 'Laporan', Models\GrowthTarget::class),

            'wilayah' => self::model('Wilayah', 'Pengaturan', Models\Region::class),
            'jenis-kegiatan' => self::model('Jenis Kegiatan', 'Pengaturan', Models\ActivityType::class),
        ];
    }

    private static function model(string $label, string $grup, string $model): array
    {
        return [
            'label' => $label, 'grup' => $grup, 'model' => $model, 'kelola' => true,
            'bawaan' => function (User $u) use ($model) {
                $policy = Gate::getPolicyFor($model);

                return match (true) {
                    ! $policy => self::TIDAK,
                    $policy->create($u) => self::KELOLA,
                    $policy->viewAny($u) => self::LIHAT,
                    default => self::TIDAK,
                };
            },
        ];
    }

    /** Model tambahan yang mengikuti menu lain. */
    private const MODEL_IKUT = [Models\OrgPeriod::class => 'unit-organisasi'];

    public static function menuUntukModel(string $class): ?string
    {
        static $peta = null;
        $peta ??= collect(self::menu())->filter(fn ($m) => isset($m['model']))->mapWithKeys(fn ($m, $k) => [$m['model'] => $k])->all()
            + self::MODEL_IKUT;

        return $peta[$class] ?? null;
    }

    /** Peran yang tampil sebagai kolom matriks. @return array<string, string> */
    public static function peranDiatur(): array
    {
        return collect(Opsi::PERAN)->except(self::PERAN_TETAP)->all();
    }

    /** @return array<string, array<string, int>> pengaturan tersimpan [peran][menu] */
    public static function tersimpan(): array
    {
        return Cache::rememberForever('sij.hak_akses', fn () => DB::table('role_menu_access')->get()
            ->groupBy('role')->map(fn ($rows) => $rows->pluck('level', 'menu')->map(fn ($v) => (int) $v)->all())->all());
    }

    public static function lupakanCache(): void
    {
        Cache::forget('sij.hak_akses');
        self::$memo = [];
    }

    /** Level tersimpan atau bawaan kode peran baru; null = belum diatur (pakai aturan lama). */
    public static function levelDiatur(string $peran, string $menu): ?int
    {
        return self::tersimpan()[$peran][$menu] ?? self::BAWAAN_PERAN[$peran][$menu] ?? null;
    }

    /** Aturan bawaan (lama) untuk satu peran, dihitung dengan user bayangan berperan itu saja. */
    public static function levelBawaanPeran(string $peran, string $menu): int
    {
        if (isset(self::BAWAAN_PERAN[$peran])) {
            return self::BAWAAN_PERAN[$peran][$menu] ?? self::TIDAK;
        }
        $kunci = "bawaan:{$peran}:{$menu}";
        if (! array_key_exists($kunci, self::$memo)) {
            $u = new User;
            $u->setRelation('roles', Role::where('name', $peran)->get());
            self::$memo[$kunci] = self::denganBawaan(fn () => (self::menu()[$menu]['bawaan'])($u));
        }

        return self::$memo[$kunci];
    }

    /** Level efektif untuk peran (tersimpan, kode, atau bawaan) — untuk tampilan matriks. */
    public static function levelPeran(string $peran, string $menu): int
    {
        return self::levelDiatur($peran, $menu) ?? self::levelBawaanPeran($peran, $menu);
    }

    /**
     * Level user untuk menu. null = tidak ada peran user yang diatur di matriks:
     * pemanggil memakai aturan lama apa adanya (perilaku tidak berubah).
     */
    public static function levelUser(?User $u, string $menu): ?int
    {
        if (! $u || self::$hitungBawaan) {
            return null;
        }
        if ($u->isSuperAdmin()) {
            return self::KELOLA;
        }
        $peran = $u->getRoleNames()->diff(self::PERAN_TETAP);
        $diatur = $peran->map(fn ($p) => self::levelDiatur($p, $menu))->filter(fn ($v) => $v !== null);
        if ($diatur->isEmpty()) {
            return null;
        }
        $lain = $peran->filter(fn ($p) => self::levelDiatur($p, $menu) === null)->map(fn ($p) => self::levelBawaanPeran($p, $menu));

        return (int) $diatur->merge($lain)->max();
    }

    /** Boleh membuka halaman/menu: matriks bila diatur, selain itu aturan lama. */
    public static function boleh(string $menu, int $perlu = self::LIHAT, ?User $u = null): bool
    {
        $u ??= auth()->user();
        if (! $u) {
            return false;
        }
        $level = self::levelUser($u, $menu);

        return $level !== null ? $level >= $perlu : (self::menu()[$menu]['bawaan'])($u) >= $perlu;
    }

    /** Lihat SEMUA data (bukan hanya wilayah/komisi) karena matriks memberi akses ke peran tak terbatas. */
    public static function lihatSemua(User $u, string $menu): bool
    {
        if (self::$hitungBawaan) {
            return false;
        }

        return $u->getRoleNames()->diff([...self::PERAN_TETAP, ...self::PERAN_TERBATAS])
            ->contains(fn ($p) => (self::levelDiatur($p, $menu) ?? self::TIDAK) >= self::LIHAT);
    }

    /** Pemeriksaan Gate untuk model yang terdaftar di matriks. null = serahkan ke policy. */
    public static function gate(User $u, string $ability, array $args): ?bool
    {
        $model = $args[0] ?? null;
        $class = is_object($model) ? $model::class : (is_string($model) ? $model : null);
        $menu = $class ? self::menuUntukModel($class) : null;
        if (! $menu) {
            return null;
        }
        $perlu = match ($ability) {
            'viewAny', 'view' => self::LIHAT,
            'create', 'update', 'replicate', 'reorder', 'restore', 'restoreAny',
            'attach', 'attachAny', 'detach', 'detachAny', 'associate', 'dissociate', 'dissociateAny' => self::KELOLA,
            default => null, // hapus & aksi khusus tetap mengikuti policy
        };
        $level = $perlu === null ? null : self::levelUser($u, $menu);
        if ($level === null) {
            return null;
        }
        if ($level < $perlu) {
            return false;
        }
        // Data per baris tetap dibatasi cakupan peran (wilayah, komisi, KK tugas).
        if (is_object($model) && method_exists($model, 'scopeVisibleTo')) {
            return $class::query()->visibleTo($u)->whereKey($model->getKey())->exists();
        }

        return true;
    }

    private static function denganBawaan(Closure $fn): mixed
    {
        $lama = self::$hitungBawaan;
        self::$hitungBawaan = true;
        try {
            return $fn();
        } finally {
            self::$hitungBawaan = $lama;
        }
    }

    /** Simpan matriks: sel yang sama dengan bawaan dihapus agar tetap mengikuti aturan lama. */
    public static function simpan(array $matriks): void
    {
        DB::transaction(function () use ($matriks) {
            foreach (self::peranDiatur() as $peran => $_) {
                foreach (self::menu() as $menu => $m) {
                    $nilai = $matriks[$peran][$menu] ?? null;
                    if ($nilai === null) {
                        continue;
                    }
                    $nilai = min((int) $nilai, $m['kelola'] ? self::KELOLA : self::LIHAT);
                    $bawaan = self::BAWAAN_PERAN[$peran][$menu] ?? (isset(self::BAWAAN_PERAN[$peran]) ? self::TIDAK : self::levelBawaanPeran($peran, $menu));
                    if ($nilai === $bawaan) {
                        DB::table('role_menu_access')->where(['role' => $peran, 'menu' => $menu])->delete();
                    } else {
                        DB::table('role_menu_access')->updateOrInsert(['role' => $peran, 'menu' => $menu], ['level' => $nilai, 'updated_at' => now(), 'created_at' => now()]);
                    }
                }
            }
        });
        self::lupakanCache();
    }

    public static function kembalikanBawaan(string $peran): void
    {
        DB::table('role_menu_access')->where('role', $peran)->delete();
        self::lupakanCache();
    }
}
