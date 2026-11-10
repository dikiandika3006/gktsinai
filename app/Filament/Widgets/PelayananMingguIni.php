<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\JadwalBulanan;
use App\Models\WorshipSchedule;
use App\Services\PemeriksaJadwal;
use Filament\Widgets\Widget;

/** Dashboard: petugas ibadah Minggu terdekat, per kebaktian & peran, dengan peringatan dobel. */
class PelayananMingguIni extends Widget
{
    protected static ?int $sort = 0;

    protected string $view = 'filament.widgets.pelayanan-minggu-ini';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $u = auth()->user();

        return $u->lihatSemuaJemaat() || $u->peran('koordinator_komisi') || $u->kelolaJadwal();
    }

    protected function getViewData(): array
    {
        $j = WorshipSchedule::with(['assignments.member', 'assignments.alias'])
            ->whereDate('date', '>=', today())->orderBy('date')->first();
        $cek = $j ? app(PemeriksaJadwal::class)->periksa(collect([$j]), PHP_INT_MAX) : ['peringatan' => [], 'tandai' => []];

        return [
            'jadwal' => $j,
            'kelompok' => $j ? $j->assignments->sortBy('urutan')
                ->groupBy(fn ($a) => $a->kebaktian ?: 'Semua kebaktian')->sortKeys() : collect(),
            'peringatan' => $cek['peringatan'],
            'urlJadwal' => JadwalBulanan::getUrl(),
        ];
    }
}
