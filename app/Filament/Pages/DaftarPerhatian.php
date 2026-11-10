<?php

namespace App\Filament\Pages;

use App\Models\Member;
use App\Models\Visit;
use App\Services\AttendanceService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;

/** KH-07 (daftar perhatian) & KH-09 (usulan status Pasif). */
class DaftarPerhatian extends Page
{
    protected string $view = 'filament.pages.daftar-perhatian';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static string|\UnitEnum|null $navigationGroup = 'Pastoral';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Daftar Perhatian';

    /** @var array<int> */
    public array $pasifIds = [];

    public static function canAccess(): bool
    {
        return auth()->user()->lihatSemuaJemaat() || auth()->user()->peran('koordinator_wilayah', 'koordinator_komisi');
    }

    public function setujuiPasif(): void
    {
        abort_unless(auth()->user()->kelolaJemaat(), 403);
        $n = 0;
        Member::whereIn('id', $this->pasifIds)->get()->each(function (Member $m) use (&$n) {
            $m->update(['activity_status' => 'pasif']);
            $n++;
        });
        $this->pasifIds = [];
        Cache::forget('sij.attention_count');
        Notification::make()->success()->title("{$n} jemaat diubah menjadi Pasif")->send();
    }

    protected function getViewData(): array
    {
        $svc = app(AttendanceService::class);
        $visible = Member::query()->visibleTo(auth()->user())->pluck('id')->flip();

        return [
            'perhatian' => $svc->attentionList()->filter(fn ($r) => $visible->has($r['member']->id))->sortBy('member.name')->values(),
            'pasif' => $svc->passiveCandidates()->filter(fn ($m) => $visible->has($m->id))->values(),
            'threshold' => config('sij.absence_threshold'),
            'bulan' => config('sij.passive_after_months'),
            'bisaKelola' => auth()->user()->kelolaJemaat(),
            'bisaJadwal' => auth()->user()->can('create', Visit::class),
        ];
    }
}
