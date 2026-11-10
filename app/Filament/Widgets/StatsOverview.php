<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\DaftarPerhatian;
use App\Filament\Resources\Guests\GuestResource;
use App\Filament\Resources\Members\MemberResource;
use App\Models\ActivitySession;
use App\Models\Guest;
use App\Models\Member;
use App\Services\AttendanceService;
use App\Services\GrowthService;
use App\Support\Opsi;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class StatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return auth()->user()->lihatSemuaJemaat();
    }

    protected function getStats(): array
    {
        $t = app(GrowthService::class)->totals();
        $perhatian = Cache::remember('sij.attention_count', 600, fn () => app(AttendanceService::class)->attentionList()->count());
        $fu = Guest::where('fu_stage', 'baru')->where('fu_due_at', '<', now())->count();
        $tanpaSakramen = Member::whereIn('membership_status', Opsi::ANGGOTA_RESMI)
            ->whereDoesntHave('events', fn ($e) => $e->whereIn('type', ['baptis', 'sidi'])->where('data_status', 'lengkap'))->count();

        // Warna aksen kartu (garis atas, ikon, lingkaran latar) diatur lewat CSS variabel --stat di tema.
        $warna = fn (string $hex) => ['style' => "--stat: {$hex}"];
        $tren = $this->trenJemaat();
        // Data hasil import tercatat sekaligus: tren baru bermakna bila sudah ada jemaat sejak awal rentang.
        if (! $tren || $tren[0] === 0) {
            $tren = null;
        }
        $baru = $tren ? end($tren) - $tren[array_key_last($tren) - 1] : 0;

        return [
            Stat::make('Jemaat tercatat', $t['tercatat'])
                ->description($t['kk'].' KK'.($baru > 0 ? " · +{$baru} bulan ini" : ''))
                ->descriptionIcon($baru > 0 ? 'heroicon-m-arrow-trending-up' : null)
                ->icon('heroicon-o-users')->color('info')->chart($tren)->url(MemberResource::getUrl())
                ->extraAttributes($warna('#2563eb')),
            Stat::make('Anggota resmi', $t['anggota'])->description('Sidi / baptis / anggota')->icon('heroicon-o-identification')
                ->extraAttributes($warna('#7c3aed')),
            Stat::make('Aktif beribadah', $t['aktif'])->description('Tren kehadiran ibadah umum')->icon('heroicon-o-check-badge')
                ->color('success')->chart($this->trenKehadiran())->extraAttributes($warna('#059669')),
            Stat::make('Perlu perhatian', $perhatian)->description('Absen 3x berturut-turut')->icon('heroicon-o-exclamation-triangle')
                ->descriptionIcon($perhatian ? 'heroicon-m-arrow-right' : null)
                ->color($perhatian ? 'warning' : 'gray')->url(DaftarPerhatian::getUrl())
                ->extraAttributes($warna($perhatian ? '#d97706' : '#94a3b8')),
            Stat::make('FU tamu terlambat', $fu)->description('Lewat batas 2x24 jam')->icon('heroicon-o-clock')
                ->descriptionIcon($fu ? 'heroicon-m-arrow-right' : null)
                ->color($fu ? 'danger' : 'gray')->url(GuestResource::getUrl())
                ->extraAttributes($warna($fu ? '#dc2626' : '#94a3b8')),
            Stat::make('Anggota tanpa data baptis/sidi', $tanpaSakramen)->description('Lengkapi bertahap (DJ-17)')->icon('heroicon-o-document-magnifying-glass')
                ->url(MemberResource::getUrl('index', ['filters' => ['kelengkapan' => ['value' => 'tanpa_sakramen']]]))
                ->extraAttributes($warna('#0d9488')),
        ];
    }

    /** Jumlah jemaat tercatat di akhir 8 bulan terakhir (grafik mini). */
    private function trenJemaat(): array
    {
        return Cache::remember('sij.tren_jemaat', 3600, fn () => collect(range(7, 0))
            ->map(fn ($i) => Member::query()->tercatat()->where('created_at', '<=', now()->subMonthsNoOverflow($i)->endOfMonth())->count())
            ->all());
    }

    /** Total hadir 10 ibadah umum terakhir (grafik mini). */
    private function trenKehadiran(): array
    {
        return Cache::remember('sij.tren_hadir', 600, fn () => ActivitySession::query()
            ->whereHas('activityType', fn ($query) => $query->where('is_general_worship', true))
            ->where('date', '<=', today())->withCount('attendances')
            ->orderByDesc('date')->limit(20)->get()
            ->groupBy(fn ($s) => $s->date->toDateString())
            ->map(fn ($g) => $g->sum(fn ($s) => $s->manual_count ?? $s->attendances_count))
            ->take(10)->reverse()->values()->all());
    }
}
