<?php

namespace App\Filament\Pages;

use App\Models\Family;
use App\Models\Region;
use App\Models\Visit;
use App\Services\VisitSuggestionService;
use App\Support\Opsi;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/** VS-03 (usulan otomatis) & VS-07 (cakupan kunjungan per wilayah). */
class UsulanVisitasi extends Page
{
    protected string $view = 'filament.pages.usulan-visitasi';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLightBulb;

    protected static string|\UnitEnum|null $navigationGroup = 'Pastoral';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Usulan & Cakupan Visitasi';

    public int $tahun;

    public string $cari = '';

    public string $wilayah = '';

    public string $jenis = '';

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
        $semua = $usulan->count();
        $jenisOpsi = $usulan->countBy('type')->mapWithKeys(fn ($n, $k) => [$k => Opsi::label(Opsi::JENIS_VISITASI, $k)." ($n)"])->all();
        $wilayahOpsi = $usulan->map(fn ($r) => $r['family']->region?->name)->filter()->unique()->sort()->values()->all();

        // Pencarian: kode/nama KK, nama anggota keluarga, wilayah, atau alasan.
        $cari = trim($this->cari);
        if ($cari !== '') {
            $kkAnggota = Family::query()->whereIn('id', $usulan->pluck('family.id'))
                ->whereHas('members', fn ($query) => $query->where('name', 'like', "%{$cari}%")->orWhere('alias', 'like', "%{$cari}%"))
                ->pluck('id')->flip();
            $c = mb_strtolower($cari);
            $usulan = $usulan->filter(fn ($r) => $kkAnggota->has($r['family']->id)
                || str_contains(mb_strtolower($r['family']->display_name.' '.$r['family']->region?->name.' '.$r['reason']), $c));
        }
        $usulan = $usulan
            ->when($this->wilayah !== '', fn ($c) => $c->filter(fn ($r) => $r['family']->region?->name === $this->wilayah))
            ->when($this->jenis !== '', fn ($c) => $c->where('type', $this->jenis))
            ->values();

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
            'semua' => $semua,
            'jenisOpsi' => $jenisOpsi,
            'wilayahOpsi' => $wilayahOpsi,
            'cakupan' => $cakupan,
            'bisaJadwal' => auth()->user()->can('create', Visit::class),
            'tahunOpsi' => range(now()->year, now()->year - 4),
        ];
    }
}
