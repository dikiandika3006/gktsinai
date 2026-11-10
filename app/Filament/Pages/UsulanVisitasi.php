<?php

namespace App\Filament\Pages;

use App\Models\Family;
use App\Models\Region;
use App\Models\Visit;
use App\Services\VisitSuggestionService;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/** VS-03 (usulan otomatis) & VS-07 (cakupan kunjungan per wilayah). */
class UsulanVisitasi extends Page
{
    protected string $view = 'filament.pages.usulan-visitasi';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLightBulb;

    protected static string|\UnitEnum|null $navigationGroup = 'Visitasi';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Usulan & Cakupan Visitasi';

    public int $tahun;

    public function mount(): void
    {
        $this->tahun = now()->year;
    }

    public static function canAccess(): bool
    {
        return auth()->user()->peran('super_admin', 'sekretariat', 'pendeta', 'majelis', 'koordinator_wilayah');
    }

    protected function getViewData(): array
    {
        $visible = Family::query()->visibleTo(auth()->user())->pluck('id')->flip();
        $usulan = app(VisitSuggestionService::class)->suggestions()
            ->filter(fn ($r) => $visible->has($r['family']->id))->values();

        // VS-07
        $cakupan = Region::orderBy('name')->get()->map(function (Region $r) {
            $total = $r->families()->whereHas('members', fn ($q) => $q->tercatat())->count();
            $dikunjungi = Visit::where('status', 'selesai')->whereYear('realized_date', $this->tahun)
                ->whereHas('family', fn ($f) => $f->where('region_id', $r->id))->distinct()->count('family_id');

            return ['wilayah' => $r->name, 'total' => $total, 'dikunjungi' => $dikunjungi,
                'pct' => $total ? round($dikunjungi / $total * 100) : 0];
        })->filter(fn ($r) => $r['total'] > 0);

        return [
            'usulan' => $usulan,
            'cakupan' => $cakupan,
            'bisaJadwal' => auth()->user()->can('create', Visit::class),
            'tahunOpsi' => range(now()->year, now()->year - 4),
        ];
    }
}
