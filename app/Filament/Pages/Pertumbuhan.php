<?php

namespace App\Filament\Pages;

use App\Exports\ArrayExport;
use App\Filament\Widgets\AgePyramidChart;
use App\Filament\Widgets\CommissionCompositionChart;
use App\Filament\Widgets\GrowthChart;
use App\Models\GrowthTarget;
use App\Services\GrowthService;
use App\Support\HakAkses;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Maatwebsite\Excel\Facades\Excel;

/** Modul Pertumbuhan Jemaat (PJ-01 s.d. PJ-06). */
class Pertumbuhan extends Page
{
    protected string $view = 'filament.pages.pertumbuhan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|\UnitEnum|null $navigationGroup = 'Laporan';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Pertumbuhan Jemaat';

    public static function canAccess(): bool
    {
        return HakAkses::boleh('pertumbuhan');
    }

    protected function getHeaderActions(): array
    {
        $bisaEkspor = auth()->user()->peran('super_admin', 'sekretariat', 'pendeta');

        return [
            Action::make('excel')->label('Ekspor Excel')->icon(Heroicon::OutlinedArrowDownTray)->visible($bisaEkspor)
                ->action(function () {
                    $m = app(GrowthService::class)->regionMatrix();
                    $head = array_keys($m->first() ?? ['wilayah' => '']);
                    $head[0] = 'Wilayah';

                    return Excel::download(new ArrayExport($head, $m->map(fn ($r) => array_values($r))->all(), 'Wilayah x Status'),
                        'rekap-wilayah-status-'.now()->format('Ymd').'.xlsx');
                }),
            Action::make('pdf')->label('PDF rekap')->icon(Heroicon::OutlinedDocument)->visible($bisaEkspor)
                ->url(route('pdf.rekap'), true),
            Action::make('bulanan')->label('Laporan bulanan majelis')->icon(Heroicon::OutlinedDocumentChartBar)->color('primary')
                ->visible($bisaEkspor)
                ->schema([
                    Select::make('bulan')->label('Bulan')->required()->default(now()->subMonth()->format('Y-m'))
                        ->options(collect(range(0, 23))->mapWithKeys(fn ($i) => [now()->subMonths($i)->format('Y-m') => now()->subMonths($i)->translatedFormat('F Y')])),
                ])
                ->action(fn (array $data) => redirect()->away(route('pdf.monthly', ['bulan' => $data['bulan']]))),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [GrowthChart::class, AgePyramidChart::class, CommissionCompositionChart::class];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 3;
    }

    protected function getViewData(): array
    {
        $svc = app(GrowthService::class);
        $target = GrowthTarget::where('year', now()->year)->first();

        return [
            'summary' => $svc->statusSummary(),
            'matrix' => $svc->regionMatrix(),
            'columns' => GrowthService::KOLOM_STATUS,
            'totals' => $svc->totals(),
            'target' => $target,
            'realisasi' => $svc->newMembersInYear(now()->year),
            'series' => $svc->growthSeries('month', 12),
        ];
    }
}
