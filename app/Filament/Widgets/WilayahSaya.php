<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Families\FamilyResource;
use App\Filament\Resources\Members\MemberResource;
use App\Models\Family;
use App\Models\Member;
use App\Services\AttendanceService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Dashboard koordinator wilayah: ringkasan wilayahnya sendiri. */
class WilayahSaya extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        $u = auth()->user();

        return $u->hasRole('koordinator_wilayah') && $u->region_id && ! $u->lihatSemuaJemaat();
    }

    protected function getHeading(): ?string
    {
        return 'Wilayah '.(auth()->user()->region?->name ?? '');
    }

    protected function getStats(): array
    {
        $r = auth()->user()->region_id;
        $jemaat = Member::query()->tercatat()->whereHas('family', fn ($f) => $f->where('region_id', $r));
        $kk = Family::where('region_id', $r)->whereHas('members', fn ($q) => $q->tercatat());
        $belumDikunjungi = (clone $kk)->whereDoesntHave('visits', fn ($v) => $v->where('status', 'selesai')
            ->whereDate('realized_date', '>=', today()->subMonths(12)))->count();
        $idWilayah = (clone $jemaat)->pluck('id')->flip();
        $perhatian = app(AttendanceService::class)->attentionList()->filter(fn ($x) => $idWilayah->has($x['member']->id))->count();

        return [
            Stat::make('Jemaat tercatat', (clone $jemaat)->count())->description($kk->count().' KK')
                ->icon('heroicon-o-users')->url(MemberResource::getUrl()),
            Stat::make('Aktif / Pasif', (clone $jemaat)->where('activity_status', 'aktif')->count().' / '.(clone $jemaat)->where('activity_status', 'pasif')->count())
                ->icon('heroicon-o-check-badge'),
            Stat::make('Perlu perhatian', $perhatian)->description('Absen 3x berturut-turut')
                ->icon('heroicon-o-exclamation-triangle')->color($perhatian ? 'warning' : 'gray'),
            Stat::make('KK belum dikunjungi >12 bulan', $belumDikunjungi)->icon('heroicon-o-home')
                ->color($belumDikunjungi ? 'warning' : 'success')->url(FamilyResource::getUrl()),
        ];
    }
}
