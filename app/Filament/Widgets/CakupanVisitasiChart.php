<?php

namespace App\Filament\Widgets;

use App\Models\Family;
use App\Models\Region;
use Filament\Widgets\ChartWidget;

/** VS-07: persentase KK yang sudah dikunjungi per wilayah, tahun berjalan. */
class CakupanVisitasiChart extends ChartWidget
{
    protected static ?int $sort = 6;

    protected ?string $maxHeight = '280px';

    public static function canView(): bool
    {
        return auth()->user()->lihatSemuaJemaat() || auth()->user()->peran('koordinator_wilayah');
    }

    public function getHeading(): ?string
    {
        return 'Cakupan visitasi '.now()->year.' (% KK dikunjungi per wilayah)';
    }

    /** @return list<array{wilayah:string, kk:int, dikunjungi:int, persen:float}> */
    public static function data(): array
    {
        $tahun = now()->year;
        $kk = Family::query()->whereHas('members', fn ($q) => $q->tercatat())
            ->withExists(['visits as dikunjungi' => fn ($v) => $v->where('status', 'selesai')->whereYear('realized_date', $tahun)])
            ->get(['id', 'region_id']);
        $nama = Region::pluck('name', 'id');

        return $kk->groupBy(fn ($f) => $f->region_id ?? 0)->map(fn ($g, $id) => [
            'wilayah' => $nama[$id] ?? 'Belum ada wilayah',
            'kk' => $g->count(),
            'dikunjungi' => $g->where('dikunjungi', true)->count(),
            'persen' => round($g->where('dikunjungi', true)->count() / max(1, $g->count()) * 100, 1),
        ])->sortByDesc('kk')->values()->all();
    }

    protected function getData(): array
    {
        $d = collect(self::data());

        return [
            'datasets' => [[
                'label' => '% KK dikunjungi',
                'data' => $d->pluck('persen')->all(),
                'backgroundColor' => '#4f46e5',
            ]],
            'labels' => $d->map(fn ($r) => $r['wilayah'].' ('.$r['dikunjungi'].'/'.$r['kk'].')')->all(),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'scales' => ['x' => ['min' => 0, 'max' => 100]],
            'plugins' => ['legend' => ['display' => false]],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
