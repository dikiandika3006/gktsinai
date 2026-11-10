<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Commissions\CommissionResource;
use App\Models\ActivitySession;
use App\Models\Commission;
use App\Services\AttendanceService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Dashboard koordinator komisi: anggota, rata-rata hadir 4 pertemuan terakhir, dan yang perlu perhatian. */
class KomisiSaya extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $heading = 'Komisi saya';

    public static function canView(): bool
    {
        return auth()->user()->hasRole('koordinator_komisi') && auth()->user()->komisiDikoordinasi() !== [];
    }

    protected function getStats(): array
    {
        $perhatian = app(AttendanceService::class)->attentionList();

        return Commission::whereIn('id', auth()->user()->komisiDikoordinasi())->withCount(['members' => fn ($q) => $q->tercatat()])
            ->get()->map(function (Commission $c) use ($perhatian) {
                $sesi = ActivitySession::whereHas('activityType', fn ($t) => $t->where('commission_id', $c->id))
                    ->whereDate('date', '<=', today())->orderByDesc('date')->limit(4)->withCount('attendances')->get();
                $rata = $sesi->isEmpty() ? null : round($sesi->avg(fn ($s) => $s->manual_count ?? $s->attendances_count));
                $idAnggota = $c->members()->pluck('members.id')->flip();
                $n = $perhatian->filter(fn ($x) => $idAnggota->has($x['member']->id))->count();

                return Stat::make($c->name, $c->members_count.' anggota')
                    ->description(($rata !== null ? "Rata-rata hadir {$rata} (4 pertemuan)" : 'Belum ada pertemuan tercatat').($n ? " · {$n} perlu perhatian" : ''))
                    ->color($n ? 'warning' : 'primary')->icon('heroicon-o-user-group')
                    ->url(CommissionResource::getUrl('edit', ['record' => $c]));
            })->all();
    }
}
