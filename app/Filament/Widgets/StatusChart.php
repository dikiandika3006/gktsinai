<?php

namespace App\Filament\Widgets;

use App\Services\GrowthService;
use Filament\Widgets\ChartWidget;

/** PJ-01: komposisi status keanggotaan & keaktifan. */
class StatusChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Komposisi status jemaat';

    protected ?string $maxHeight = '260px';

    public static function canView(): bool
    {
        return auth()->user()->lihatSemuaJemaat();
    }

    protected function getData(): array
    {
        $s = app(GrowthService::class)->statusSummary();

        return [
            'datasets' => [[
                'data' => array_values($s),
                'backgroundColor' => ['#1f7a4d', '#6cc08b', '#d99a1e', '#8b1e2b', '#9ca3af', '#64748b'], 'borderWidth' => 2,
            ]],
            'labels' => array_keys($s),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
