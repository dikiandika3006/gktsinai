<?php

namespace App\Filament\Widgets;

use App\Services\GrowthService;
use Filament\Widgets\ChartWidget;

/** PJ-04: piramida usia. */
class AgePyramidChart extends ChartWidget
{
    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Piramida usia';

    protected ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $p = array_reverse(app(GrowthService::class)->agePyramid(), true);

        return [
            'datasets' => [
                ['label' => 'Laki-laki', 'data' => array_map(fn ($r) => -$r['L'], array_values($p)), 'backgroundColor' => '#2563eb'],
                ['label' => 'Perempuan', 'data' => array_map(fn ($r) => $r['P'], array_values($p)), 'backgroundColor' => '#db2777'],
            ],
            'labels' => array_keys($p),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'scales' => ['x' => ['stacked' => true], 'y' => ['stacked' => true]],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
