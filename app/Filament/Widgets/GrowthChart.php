<?php

namespace App\Filament\Widgets;

use App\Services\GrowthService;
use Filament\Widgets\ChartWidget;

/** PJ-03: grafik pertumbuhan masuk vs keluar. */
class GrowthChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Pertumbuhan jemaat: masuk vs keluar';

    protected ?string $description = 'Masuk: baptis, sidi, atestasi masuk, tamu jadi anggota. Keluar: atestasi keluar, wafat.';

    protected ?string $maxHeight = '260px';

    public ?string $filter = 'month';

    public static function canView(): bool
    {
        return auth()->user()->lihatSemuaJemaat() || auth()->user()->peran('koordinator_wilayah');
    }

    protected function getFilters(): ?array
    {
        return ['month' => '12 bulan terakhir', 'year' => '5 tahun terakhir'];
    }

    protected function getData(): array
    {
        $per = $this->filter === 'year' ? 'year' : 'month';
        $regionId = auth()->user()->lihatSemuaJemaat() ? null : auth()->user()->region_id;
        $series = app(GrowthService::class)->growthSeries($per, $per === 'year' ? 5 : 12, $regionId);

        return [
            'datasets' => [
                ['label' => 'Masuk', 'data' => array_column($series, 'masuk'), 'backgroundColor' => '#1f7a4d'],
                ['label' => 'Keluar', 'data' => array_map(fn ($r) => -$r['keluar'], $series), 'backgroundColor' => '#8b1e2b'],
            ],
            'labels' => array_keys($series),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
