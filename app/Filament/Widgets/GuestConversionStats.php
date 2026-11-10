<?php

namespace App\Filament\Widgets;

use App\Models\Guest;
use App\Support\Opsi;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** TF-09: laporan konversi tamu. */
class GuestConversionStats extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Konversi tamu (12 bulan terakhir)';

    protected function getStats(): array
    {
        $tamu = Guest::where('first_visit_at', '>=', today()->subMonths(12))->withCount('attendances')->get();
        $n = $tamu->count();
        $datangLagi = $tamu->filter(fn ($g) => $g->attendances_count > 0 || in_array($g->fu_stage, ['datang_lagi', 'rutin', 'jemaat'], true))->count();
        $jemaat = $tamu->whereNotNull('converted_member_id')->count();
        $sumber = $tamu->whereNotNull('source')->countBy('source')->sortDesc();
        $bulanIni = Guest::where('first_visit_at', '>=', today()->startOfMonth())->count();
        $perBulan = collect(range(11, 0))->map(fn ($i) => $tamu->filter(fn ($g) => $g->first_visit_at?->format('Y-m') === today()->subMonths($i)->format('Y-m'))->count())->all();

        return [
            Stat::make('Tamu baru', $n)->description($bulanIni.' bulan ini')->chart($perBulan),
            Stat::make('Datang lagi', $n ? round($datangLagi / $n * 100).'%' : '-')->description($datangLagi.' orang'),
            Stat::make('Jadi jemaat', $n ? round($jemaat / $n * 100).'%' : '-')->description($jemaat.' orang')->color('success'),
            Stat::make('Sumber terbanyak', $sumber->keys()->first() ? Opsi::label(Opsi::SUMBER_INFO, $sumber->keys()->first()) : '-')
                ->description($sumber->first() ? $sumber->first().' tamu' : ''),
        ];
    }
}
