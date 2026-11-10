<?php

namespace App\Filament\Widgets;

use App\Models\Commission;
use Filament\Widgets\ChartWidget;

/** PJ-04: komposisi kategorial. */
class CommissionCompositionChart extends ChartWidget
{
    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Komposisi kategorial (anggota komisi)';

    protected ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $c = Commission::where('is_active', true)->withCount('activeMembers')->get();

        return [
            'datasets' => [[
                'label' => 'Anggota',
                'data' => $c->pluck('active_members_count')->all(),
                'backgroundColor' => ['#db2777', '#2563eb', '#d97706', '#16a34a', '#7c3aed', '#0891b2'],
            ]],
            'labels' => $c->pluck('name')->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
