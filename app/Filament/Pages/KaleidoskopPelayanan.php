<?php

namespace App\Filament\Pages;

use App\Models\ServiceAssignment;
use App\Models\WorshipSchedule;
use App\Support\HakAkses;
use App\Support\Opsi;
use BackedEnum;
use Carbon\Carbon;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

/** Kaleidoskop: rekap tahunan pelayanan ibadah umum (siapa melayani, berapa kali, seberapa merata). */
class KaleidoskopPelayanan extends Page
{
    protected string $view = 'filament.pages.kaleidoskop-pelayanan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|\UnitEnum|null $navigationGroup = 'Pelayanan';

    protected static ?int $navigationSort = 5;

    protected static ?string $title = 'Kaleidoskop Pelayanan';

    public int $tahun;

    public string $peranA = 'liturgis';

    public string $peranB = 'singer';

    public function mount(): void
    {
        $terakhir = WorshipSchedule::query()->where('date', '<=', now()->endOfYear())->max('date');
        $this->tahun = $terakhir ? Carbon::parse($terakhir)->year : now()->year;
    }

    public static function canAccess(): bool
    {
        return HakAkses::boleh('kaleidoskop');
    }

    /** Jumlah ibadah dalam satu jadwal: gabungan = 1, KU I + KU II = 2, belum ada petugas = 0. */
    public static function jumlahIbadah(WorshipSchedule $s): int
    {
        if ($s->assignments->isEmpty()) {
            return 0;
        }

        return $s->assignments->every(fn ($a) => $a->kebaktian === 'Gabungan') ? 1 : 2;
    }

    /** Rekap satu peran: orang (dikelompokkan per jemaat / per nama), jumlah tugas, urut terbanyak. */
    private function rekapPeran(Collection $tugas, string $peran): array
    {
        $orang = $tugas->where('peran', $peran)
            ->groupBy(fn (ServiceAssignment $a) => $a->member_id ? 'm'.$a->member_id : 'k'.$a->kunci)
            ->map(fn (Collection $g, $k) => [
                'kunci' => $k,
                // Nama seperti biasa ditulis di jadwal (penulisan yang paling sering).
                'nama' => $g->countBy('nama')->sortDesc()->keys()->first(),
                'n' => $g->count(),
            ])
            ->sortBy([['n', 'desc'], ['nama', 'asc']])->values();

        $maks = (int) $orang->max('n');
        $min = (int) $orang->min('n');

        // Daftar teratas: maksimal 8 baris; yang seri di urutan terakhir diringkas "N orang lainnya".
        $baris = [];
        if ($orang->isNotEmpty()) {
            $batas = $orang->get(min(7, $orang->count() - 1))['n'];
            $atas = $orang->where('n', '>', $batas)->values();
            $seri = $orang->where('n', $batas)->values();
            $baris = $atas->map(fn ($o) => ['nama' => $o['nama'], 'n' => $o['n']])->all();
            if (count($baris) + $seri->count() <= 8) {
                foreach ($seri as $o) {
                    $baris[] = ['nama' => $o['nama'], 'n' => $o['n']];
                }
            } else {
                $baris[] = ['nama' => $seri->count().' orang lainnya', 'n' => $batas, 'grup' => true];
            }
            $ditampilkan = $atas->count() + $seri->count();
        }

        // Peringkat berikutnya: "4× (8 orang), 3× (4 orang) ..."
        $berikut = $orang->slice($ditampilkan ?? 0)->countBy('n')->sortKeysDesc()
            ->map(fn ($jml, $n) => "{$n}× ({$jml} orang)")->values()->join(', ');

        // Sebaran: kelompok kali bertugas.
        $kelompok = $maks <= 3 ? [[1, 1], [2, 2], [3, 3]] : [[1, 1], [2, 3], [4, 5], [6, PHP_INT_MAX]];
        if ($maks >= 4 && $maks <= 6) {
            $kelompok = [[1, 2], [3, 4], [5, 6]];
        }
        $sebaran = collect($kelompok)->map(function ($k) use ($orang, $maks) {
            [$a, $b] = $k;
            $b = min($b, max($maks, $a));

            return [
                'label' => $a === $b ? "{$a}×" : "{$a}–{$b}×",
                'n' => $orang->filter(fn ($o) => $o['n'] >= $a && $o['n'] <= $b)->count(),
            ];
        })->filter(fn ($s) => $s['n'] > 0)->values()->all();

        $banyak = $orang->where('n', '>=', max(2, (int) ceil($maks * 0.75)))->count();
        $sekali = $orang->where('n', 1)->count();
        $ringkas = $orang->isEmpty() ? 'Belum ada data.'
            : ($maks - $min <= 6 && $sekali <= $orang->count() / 4
                ? "Beban cukup merata: {$orang->count()} orang, selisih terbesar {$maks}× vs {$min}×."
                : "{$banyak} orang bertugas ".max(2, (int) ceil($maks * 0.75))."× atau lebih, sementara {$sekali} orang hanya 1×.");

        return [
            'label' => Opsi::PERAN_PELAYANAN[$peran] ?? $peran,
            'tugas' => $orang->sum('n'),
            'orang' => $orang->count(),
            'kunci' => $orang->pluck('kunci')->all(),
            'maks' => max(1, $maks),
            'baris' => $baris,
            'berikut' => $berikut,
            'sebaran' => $sebaran,
            'ringkas' => $ringkas,
        ];
    }

    protected function getViewData(): array
    {
        $jadwal = WorshipSchedule::with('assignments')->whereYear('date', $this->tahun)->orderBy('date')->get();
        $tugas = $jadwal->flatMap->assignments;

        $perBulan = collect(range(1, 12))->mapWithKeys(fn ($b) => [$b => 0])->all();
        $kosong = [];
        foreach ($jadwal as $s) {
            $n = self::jumlahIbadah($s);
            $perBulan[$s->date->month] += $n;
            if ($n === 0) {
                $kosong[] = $s->date->translatedFormat('j F');
            }
        }
        $minggu5 = collect($jadwal)->countBy(fn ($s) => $s->date->month)->filter(fn ($c) => $c >= 5)->keys()
            ->map(fn ($b) => Carbon::create($this->tahun, $b, 1)->translatedFormat('M'))->all();

        $a = $this->rekapPeran($tugas, $this->peranA);
        $b = $this->rekapPeran($tugas, $this->peranB);

        // Peran lain dalam setahun.
        $lain = collect(Opsi::PERAN_PELAYANAN)->except([$this->peranA, $this->peranB])
            ->map(fn ($label, $p) => [
                'label' => $label,
                'tugas' => $tugas->where('peran', $p)->count(),
                'orang' => $tugas->where('peran', $p)->map(fn ($x) => $x->member_id ? 'm'.$x->member_id : 'k'.$x->kunci)->unique()->count(),
            ])->filter(fn ($r) => $r['tugas'] > 0)->values()->all();

        $semuaOrang = $tugas->map(fn ($x) => $x->member_id ? 'm'.$x->member_id : 'k'.$x->kunci)->unique()->count();

        return [
            'ibadah' => array_sum($perBulan),
            'perBulan' => $perBulan,
            'maksBulan' => max(1, max($perBulan)),
            'catatanBulan' => trim(($minggu5 ? 'Bulan dengan 5 Minggu ('.implode(', ', $minggu5).') paling padat.' : '')
                .($kosong ? ' Belum terisi: '.implode(', ', $kosong).'.' : '')),
            'a' => $a,
            'b' => $b,
            'duaPeran' => count(array_intersect($a['kunci'], $b['kunci'])),
            'lain' => $lain,
            'semuaOrang' => $semuaOrang,
            'opsiPeran' => Opsi::PERAN_PELAYANAN,
            'opsiTahun' => WorshipSchedule::query()->selectRaw('YEAR(date) as y')->distinct()->orderByDesc('y')->pluck('y')->all() ?: [now()->year],
            'bulanLabel' => collect(range(1, 12))->map(fn ($m) => Carbon::create(2000, $m, 1)->translatedFormat('M'))->all(),
        ];
    }
}
