<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Visits\VisitResource;
use App\Models\Visit;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Dashboard tim visitasi / pendeta / majelis: kunjungan yang ditugaskan ke saya. */
class KunjunganSaya extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $heading = 'Kunjungan saya';

    public static function canView(): bool
    {
        $u = auth()->user();

        return $u->peran('tim_visitasi', 'pendeta', 'majelis', 'koordinator_wilayah')
            && Visit::whereHas('team', fn ($t) => $t->whereKey($u->id))->exists();
    }

    protected function getStats(): array
    {
        $saya = fn () => Visit::query()->whereHas('team', fn ($t) => $t->whereKey(auth()->id()));
        $url = VisitResource::getUrl('index', ['filters' => ['saya' => ['isActive' => true]]]);
        $terlambat = $saya()->where('status', 'rencana')->whereDate('planned_date', '<', today())->count();
        $berikut = $saya()->where('status', 'rencana')->whereDate('planned_date', '>=', today())->orderBy('planned_date')->with('family.head', 'member')->first();

        return [
            Stat::make('Rencana 7 hari ke depan', $saya()->where('status', 'rencana')
                ->whereBetween('planned_date', [today(), today()->addDays(7)])->count())
                ->description($berikut ? 'Berikutnya: '.$berikut->planned_date->format('d/m').' · '.$berikut->target_name : 'Tidak ada')
                ->icon('heroicon-o-calendar-days')->url($url),
            Stat::make('Lewat tanggal, belum dilaporkan', $terlambat)->icon('heroicon-o-clock')
                ->color($terlambat ? 'danger' : 'gray')->url($url),
            Stat::make('Selesai tahun ini', $saya()->where('status', 'selesai')->whereYear('realized_date', now()->year)->count())
                ->icon('heroicon-o-check-circle')->color('success')->url($url),
        ];
    }
}
