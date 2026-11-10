<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Families\FamilyResource;
use App\Models\Commission;
use App\Models\Family;
use App\Models\Region;
use App\Services\AttendanceService;
use App\Support\Opsi;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

/** 4.6 Peta Persebaran Jemaat (PT-01 s.d. PT-06). */
class PetaJemaat extends Page
{
    protected string $view = 'filament.pages.peta-jemaat';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAsiaAustralia;

    protected static string|\UnitEnum|null $navigationGroup = 'Data Jemaat';

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'Peta Persebaran';

    public array $wilayah = [];

    public ?string $keanggotaan = null;

    public ?string $keaktifan = null;

    public ?int $komisi = null;

    public ?string $umur = null;

    public static function canAccess(): bool
    {
        // Koordinat hanya untuk peran yang boleh melihat alamat.
        return auth()->user()->lihatKontak();
    }

    public function updated(): void
    {
        $this->dispatch('peta-data', data: $this->points());
    }

    public function points(): array
    {
        $user = auth()->user();
        $perhatianMember = collect(app(AttendanceService::class)->attentionList())->pluck('member.family_id')->filter()->flip();
        $memberFilter = function (Builder $m) {
            $m->tercatat()
                ->when($this->keanggotaan, fn ($q) => $q->where('membership_status', $this->keanggotaan))
                ->when($this->keaktifan, fn ($q) => $q->where('activity_status', $this->keaktifan))
                ->when($this->komisi, fn ($q) => $q->whereHas('commissions', fn ($c) => $c->where('commissions.id', $this->komisi)))
                ->when($this->umur, fn ($q) => $q->ageGroup($this->umur));
        };
        $adaFilterAnggota = $this->keanggotaan || $this->keaktifan || $this->komisi || $this->umur;

        return Family::query()->visibleTo($user)
            ->whereNotNull('latitude')->whereNotNull('longitude')
            ->when($this->wilayah, fn ($q) => $q->whereIn('region_id', $this->wilayah))
            ->when($adaFilterAnggota, fn ($q) => $q->whereHas('members', $memberFilter))
            ->whereHas('members', fn ($q) => $q->tercatat())
            ->with(['region', 'head', 'lastVisit', 'members' => $memberFilter])
            ->get()
            ->map(function (Family $f) use ($perhatianMember) {
                $belumDikunjungi = ! $f->lastVisit || $f->lastVisit->realized_date?->lt(today()->subMonths(12));

                return [
                    'id' => $f->id,
                    'lat' => $f->latitude,
                    'lng' => $f->longitude,
                    'color' => $f->region?->map_color ?? '#6b7280',
                    'code' => $f->code,
                    'head' => $f->head?->name ?? $f->members->first()?->name,
                    'region' => $f->region?->name ?? 'Belum didefinisikan',
                    'address' => $f->address,
                    'phone' => $f->phone,
                    'members' => $f->members->map(fn ($m) => $m->name.($m->age !== null ? " ({$m->age})" : ''))->all(),
                    'last_visit' => $f->lastVisit?->realized_date?->format('d/m/Y'),
                    'attention' => $perhatianMember->has($f->id) || $belumDikunjungi,
                    'attention_reason' => trim(($perhatianMember->has($f->id) ? 'Ada anggota absen 3x. ' : '').($belumDikunjungi ? 'Belum dikunjungi >12 bulan.' : '')),
                    'url' => FamilyResource::getUrl('view', ['record' => $f]),
                ];
            })->values()->all();
    }

    protected function getViewData(): array
    {
        return [
            'initial' => $this->points(),
            'regions' => Region::orderBy('name')->get(['id', 'name', 'map_color', 'area_geojson']),
            'komisiOpsi' => Commission::orderBy('name')->pluck('name', 'id'),
            'center' => config('sij.map_center'),
            'tanpaKoordinat' => Family::visibleTo(auth()->user())->whereNull('latitude')->whereHas('members', fn ($q) => $q->tercatat())->count(),
            'opsiKeanggotaan' => Opsi::KEANGGOTAAN,
            'opsiKeaktifan' => Opsi::KEAKTIFAN,
            'opsiUmur' => Opsi::KELOMPOK_UMUR,
        ];
    }
}
