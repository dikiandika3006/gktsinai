<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\DaftarPerhatian;
use App\Filament\Resources\Guests\GuestResource;
use App\Filament\Resources\Members\MemberResource;
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

        return [
            Stat::make('Jemaat tercatat', $t['tercatat'])->description($t['kk'].' KK')
                ->icon('heroicon-o-users')->url(MemberResource::getUrl()),
            Stat::make('Anggota resmi', $t['anggota'])->description('Sidi / baptis / anggota')->icon('heroicon-o-identification'),
            Stat::make('Aktif beribadah', $t['aktif'])->description('Status keaktifan Aktif')->icon('heroicon-o-check-badge')->color('success'),
            Stat::make('Perlu perhatian', $perhatian)->description('Absen 3x berturut-turut')->icon('heroicon-o-exclamation-triangle')
                ->color($perhatian ? 'warning' : 'gray')->url(DaftarPerhatian::getUrl()),
            Stat::make('FU tamu terlambat', $fu)->description('Lewat batas 2x24 jam')->icon('heroicon-o-clock')
                ->color($fu ? 'danger' : 'gray')->url(GuestResource::getUrl()),
            Stat::make('Anggota tanpa data baptis/sidi', $tanpaSakramen)->description('Lengkapi bertahap (DJ-17)')->icon('heroicon-o-document-magnifying-glass')
                ->url(MemberResource::getUrl('index', ['filters' => ['kelengkapan' => ['value' => 'tanpa_sakramen']]])),
        ];
    }
}
