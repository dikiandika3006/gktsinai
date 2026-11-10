<?php

namespace App\Filament\Pages;

use App\Exports\ArrayExport;
use App\Models\ServantAlias;
use App\Models\ServiceAssignment;
use App\Support\Opsi;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;

/** Beban pelayanan: berapa kali tiap orang melayani dalam rentang tanggal. */
class BebanPelayanan extends Page
{
    protected string $view = 'filament.pages.beban-pelayanan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|\UnitEnum|null $navigationGroup = 'Pelayanan';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Beban Pelayanan';

    public string $dari;

    public string $sampai;

    public string $peran = '';

    public string $status = '';

    public string $cari = '';

    public function mount(): void
    {
        $this->dari = now()->startOfMonth()->subMonths(2)->toDateString();
        $this->sampai = now()->addMonth()->endOfMonth()->toDateString();
    }

    public static function canAccess(): bool
    {
        return auth()->user()->lihatSemuaJemaat();
    }

    /** Satu baris per orang (jemaat terdaftar, atau nama di jadwal bila belum terdaftar). */
    public function rekap(): Collection
    {
        $tugas = ServiceAssignment::query()
            ->with(['member', 'alias', 'schedule'])
            ->whereHas('schedule', fn ($query) => $query->whereBetween('date', [$this->dari, $this->sampai]))
            ->when($this->peran, fn ($query) => $query->where('peran', $this->peran))
            ->get();

        $rows = $tugas->groupBy(fn (ServiceAssignment $a) => $a->member_id ? 'm'.$a->member_id : 'k'.$a->kunci)
            ->map(function (Collection $g) {
                $a = $g->first();
                $tanggal = $g->map(fn ($x) => $x->schedule->date->toDateString());
                $perTanggal = $tanggal->countBy();

                return [
                    'nama' => $a->member?->name ?? $a->alias?->display_name ?? $a->nama,
                    'ditulis' => $g->pluck('nama')->unique()->join(', '),
                    'member' => $a->member,
                    'status' => ServantAlias::statusLabel($a->member, $a->alias?->category),
                    'warna' => ServantAlias::statusColor($a->member, $a->alias?->category),
                    'terdaftar' => (bool) $a->member,
                    'peran' => $g->countBy('peran')->all(),
                    'total' => $g->count(),
                    'minggu' => $perTanggal->count(),
                    'rangkap' => $perTanggal->filter(fn ($n) => $n > 1)->count(),
                    'terakhir' => $tanggal->filter(fn ($t) => $t <= today()->toDateString())->max(),
                    'berikut' => $tanggal->filter(fn ($t) => $t > today()->toDateString())->min(),
                ];
            });

        $cari = mb_strtolower(trim($this->cari));

        return $rows
            ->when($this->status === 'terdaftar', fn ($c) => $c->where('terdaftar', true))
            ->when($this->status === 'belum', fn ($c) => $c->where('terdaftar', false))
            ->when($cari !== '', fn ($c) => $c->filter(fn ($r) => str_contains(mb_strtolower($r['nama'].' '.$r['ditulis']), $cari)))
            ->sortBy([['total', 'desc'], ['nama', 'asc']])
            ->values();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ekspor')->label('Ekspor Excel')->icon(Heroicon::OutlinedArrowDownTray)->color('gray')
                ->action(function () {
                    $peran = Opsi::PERAN_PELAYANAN;
                    $rows = $this->rekap()->map(fn ($r) => array_merge(
                        [$r['nama'], $r['status']],
                        array_map(fn ($p) => $r['peran'][$p] ?? 0, array_keys($peran)),
                        [$r['total'], $r['minggu'], $r['rangkap'], $r['terakhir'], $r['berikut']],
                    ))->all();

                    return Excel::download(new ArrayExport(
                        array_merge(['Nama', 'Status'], array_values($peran), ['Total tugas', 'Jumlah Minggu', 'Rangkap di hari sama', 'Terakhir', 'Berikutnya']),
                        $rows, 'Beban Pelayanan'
                    ), "beban-pelayanan-{$this->dari}-{$this->sampai}.xlsx");
                }),
        ];
    }

    protected function getViewData(): array
    {
        $rekap = $this->rekap();
        $mingguTotal = ServiceAssignment::query()->join('worship_schedules', 'worship_schedules.id', '=', 'service_assignments.worship_schedule_id')
            ->whereBetween('worship_schedules.date', [$this->dari, $this->sampai])->distinct()->count('worship_schedules.id');

        return [
            'rekap' => $rekap,
            'opsiPeran' => Opsi::PERAN_PELAYANAN,
            'peranTampil' => $this->peran ? [$this->peran => Opsi::PERAN_PELAYANAN[$this->peran] ?? $this->peran] : Opsi::PERAN_PELAYANAN,
            'ringkas' => [
                'Pelayan' => $rekap->count(),
                'Belum terdaftar' => $rekap->where('terdaftar', false)->count(),
                'Minggu terjadwal' => $mingguTotal,
                'Rata-rata tugas / orang' => $rekap->count() ? round($rekap->avg('total'), 1) : 0,
            ],
            'warna' => ['success' => '#16a34a', 'warning' => '#d97706', 'danger' => '#dc2626', 'gray' => '#6b7280'],
            'batasBerat' => max(3, (int) ceil($mingguTotal / 2)),
        ];
    }
}
