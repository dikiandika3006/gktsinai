<?php

namespace App\Filament\Widgets;

use App\Models\ActivitySession;
use Filament\Widgets\ChartWidget;

/** KH-08: tren kehadiran Ibadah Umum. */
class AttendanceTrendChart extends ChartWidget
{
    protected static ?int $sort = 4;

    protected ?string $heading = 'Kehadiran Ibadah Umum (12 minggu)';

    protected ?string $maxHeight = '260px';

    public static function canView(): bool
    {
        return auth()->user()->lihatSemuaJemaat() || auth()->user()->kelolaKehadiran();
    }

    protected function getData(): array
    {
        $sesi = ActivitySession::query()
            ->whereHas('activityType', fn ($q) => $q->where('is_general_worship', true))
            ->where('date', '<=', today())->orderByDesc('date')->limit(12)->withCount('attendances')->get()->reverse();

        $hadir = $sesi->map(fn ($s) => $s->manual_count ?? $s->attendances_count)->values();
        $avg = $hadir->count() ? round($hadir->avg(), 1) : 0;

        return [
            'datasets' => [
                ['label' => 'Hadir', 'data' => $hadir->all(), 'borderColor' => '#4f46e5', 'backgroundColor' => 'rgba(79,70,229,.15)', 'fill' => true],
                ['label' => 'Rata-rata', 'data' => array_fill(0, $hadir->count(), $avg), 'borderColor' => '#9ca3af', 'borderDash' => [6, 4], 'pointRadius' => 0],
            ],
            'labels' => $sesi->map(fn ($s) => $s->date->format('d/m'))->values()->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
