<?php

namespace App\Filament\Pages;

use App\Exports\ArrayExport;
use App\Filament\Widgets\AttendanceTrendChart;
use App\Models\ActivitySession;
use App\Models\ActivityType;
use App\Models\Attendance;
use App\Models\Commission;
use App\Models\Member;
use App\Models\Region;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/** KH-08: laporan kehadiran per sesi, per jemaat, per wilayah, per komisi, dan tren. */
class LaporanKehadiran extends Page
{
    protected string $view = 'filament.pages.laporan-kehadiran';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|\UnitEnum|null $navigationGroup = 'Kehadiran';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Laporan Kehadiran';

    public string $dari;

    public string $sampai;

    public ?int $jenis = null;

    public string $cari = '';

    public function mount(): void
    {
        $this->dari = today()->subMonths(3)->toDateString();
        $this->sampai = today()->toDateString();
        $this->jenis = ActivityType::where('is_general_worship', true)->value('id');
    }

    public static function canAccess(): bool
    {
        return auth()->user()->lihatSemuaJemaat() || auth()->user()->peran('koordinator_wilayah', 'koordinator_komisi');
    }

    protected function getHeaderWidgets(): array
    {
        return [AttendanceTrendChart::class];
    }

    protected function sesiQuery()
    {
        return ActivitySession::query()->whereBetween('date', [$this->dari, $this->sampai])
            ->when($this->jenis, fn ($q) => $q->where('activity_type_id', $this->jenis));
    }

    protected function perJemaat(): array
    {
        $sesiIds = $this->sesiQuery()->pluck('id');
        $n = $sesiIds->count();
        $hadir = Attendance::whereIn('activity_session_id', $sesiIds)->whereNotNull('member_id')
            ->select('member_id', DB::raw('COUNT(*) as total'))->groupBy('member_id')->pluck('total', 'member_id');

        $type = $this->jenis ? ActivityType::find($this->jenis) : null;
        $q = Member::query()->visibleTo(auth()->user())->aktif()->with('family.region')->search($this->cari);
        if ($type?->commission_id) {
            $q->whereHas('commissions', fn ($c) => $c->where('commissions.id', $type->commission_id));
        }

        return [$n, $q->orderBy('name')->get()->map(fn (Member $m) => [
            'member' => $m, 'hadir' => (int) ($hadir[$m->id] ?? 0),
            'pct' => $n ? round(($hadir[$m->id] ?? 0) / $n * 100) : 0,
        ])->sortBy('pct')->values()];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('excel')->label('Ekspor per jemaat')->icon(Heroicon::OutlinedArrowDownTray)
                ->action(function () {
                    [$n, $rows] = $this->perJemaat();

                    return Excel::download(new ArrayExport(
                        ['Kode', 'Nama', 'Wilayah', 'Hadir', 'Dari sesi', 'Persen'],
                        $rows->map(fn ($r) => [$r['member']->code, $r['member']->name, $r['member']->family?->region?->name, $r['hadir'], $n, $r['pct'].'%'])->all(),
                        'Kehadiran per jemaat'), 'kehadiran-'.$this->dari.'-'.$this->sampai.'.xlsx');
                }),
        ];
    }

    protected function getViewData(): array
    {
        $sesi = $this->sesiQuery()->with('activityType')->withCount('attendances')->orderByDesc('date')->get();
        $sesiIds = $sesi->pluck('id');

        // Per wilayah: jumlah check-in rata-rata per sesi & jemaat unik.
        $perWilayah = Attendance::query()
            ->whereIn('activity_session_id', $sesiIds)->whereNotNull('attendances.member_id')
            ->join('members', 'members.id', '=', 'attendances.member_id')
            ->leftJoin('families', 'families.id', '=', 'members.family_id')
            ->leftJoin('regions', 'regions.id', '=', 'families.region_id')
            ->selectRaw("COALESCE(regions.name, 'Belum didefinisikan') as wilayah, COUNT(*) as total, COUNT(DISTINCT attendances.member_id) as unik")
            ->groupBy('wilayah')->orderBy('wilayah')->get();
        $aktifPerWilayah = Member::aktif()->leftJoin('families', 'families.id', '=', 'members.family_id')
            ->leftJoin('regions', 'regions.id', '=', 'families.region_id')
            ->selectRaw("COALESCE(regions.name, 'Belum didefinisikan') as wilayah, COUNT(*) as n")->groupBy('wilayah')->pluck('n', 'wilayah');

        // Per komisi: rata-rata hadir per sesi komisi dalam periode.
        $perKomisi = Commission::where('is_active', true)->get()->map(function (Commission $c) {
            $s = ActivitySession::whereBetween('date', [$this->dari, $this->sampai])
                ->whereHas('activityType', fn ($t) => $t->where('commission_id', $c->id))->withCount('attendances')->get();
            $tot = $s->sum(fn ($x) => $x->manual_count ?? $x->attendances_count);

            return ['komisi' => $c->name, 'sesi' => $s->count(), 'rata' => $s->count() ? round($tot / $s->count(), 1) : 0,
                'anggota' => $c->activeMembers()->count()];
        });

        [$n, $perJemaat] = $this->perJemaat();

        return [
            'sesi' => $sesi,
            'perWilayah' => $perWilayah,
            'aktifPerWilayah' => $aktifPerWilayah,
            'perKomisi' => $perKomisi,
            'perJemaat' => $perJemaat->take(200),
            'nSesi' => $n,
            'jenisOpsi' => ActivityType::orderByDesc('is_general_worship')->orderBy('name')->pluck('name', 'id'),
        ];
    }
}
