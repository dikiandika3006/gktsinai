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

    /**
     * Per tanggal (12 Minggu terakhir): jumlah hadir per kebaktian, dan total hari itu dengan jemaat
     * yang hadir di lebih dari satu kebaktian dihitung sekali.
     *
     * @return array{labels: list<string>, perKebaktian: array<string, list<?int>>, total: list<int>}
     */
    public static function data(int $minggu = 12): array
    {
        $umum = ActivitySession::query()->whereHas('activityType', fn ($q) => $q->where('is_general_worship', true))
            ->whereDate('date', '<=', today());
        $tanggal = (clone $umum)->select('date')->distinct()->orderByDesc('date')->limit($minggu)->pluck('date')
            ->map(fn ($d) => substr((string) $d, 0, 10))->sort()->values();
        $sesi = $tanggal->isEmpty() ? collect() : (clone $umum)->whereDate('date', '>=', $tanggal->first())
            ->with(['attendances:id,activity_session_id,member_id,guest_id'])->get()
            ->groupBy(fn ($s) => $s->date->toDateString());

        $urutan = array_keys(config('sij.kebaktian', []));
        $nama = $sesi->flatten()->map(fn ($s) => $s->kebaktian ?? 'Ibadah Umum')->unique()
            ->sortBy(fn ($k) => ($i = array_search($k, $urutan)) === false ? 99 : $i)->values();

        $perKebaktian = [];
        foreach ($nama as $k) {
            $perKebaktian[$k] = $tanggal->map(function ($t) use ($sesi, $k) {
                $s = ($sesi[$t] ?? collect())->filter(fn ($x) => ($x->kebaktian ?? 'Ibadah Umum') === $k);

                return $s->isEmpty() ? null : $s->sum(fn ($x) => $x->manual_count ?? $x->attendances->count());
            })->all();
        }
        $total = $tanggal->map(function ($t) use ($sesi) {
            $s = $sesi[$t] ?? collect();
            $jemaat = $s->flatMap(fn ($x) => $x->attendances->whereNotNull('member_id')->pluck('member_id'))->unique()->count();
            $tamu = $s->sum(fn ($x) => $x->attendances->whereNotNull('guest_id')->count());

            return $jemaat + $tamu + $s->sum(fn ($x) => $x->manual_count ?? 0);
        })->all();

        return [
            'labels' => $tanggal->map(fn ($t) => \Illuminate\Support\Carbon::parse($t)->format('d/m'))->all(),
            'perKebaktian' => $perKebaktian,
            'total' => $total,
        ];
    }

    protected function getData(): array
    {
        $d = self::data();
        $warna = ['#4f46e5', '#0891b2', '#d97706', '#16a34a', '#db2777'];
        $datasets = [];
        $i = 0;
        foreach ($d['perKebaktian'] as $k => $nilai) {
            $datasets[] = ['label' => $k, 'data' => $nilai, 'borderColor' => $warna[$i++ % 5], 'spanGaps' => true, 'tension' => .25];
        }
        if (count($d['perKebaktian']) > 1) {
            $datasets[] = ['label' => 'Total hari itu (jemaat dihitung 1x)', 'data' => $d['total'], 'borderColor' => '#111827',
                'backgroundColor' => 'rgba(17,24,39,.06)', 'fill' => true, 'borderWidth' => 3, 'tension' => .25];
        }

        return ['datasets' => $datasets, 'labels' => $d['labels']];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
