<?php

namespace App\Filament\Pages;

use App\Filament\Resources\WorshipSchedules\Pages\ListWorshipSchedules;
use App\Filament\Resources\WorshipSchedules\WorshipScheduleResource;
use App\Models\WorshipSchedule;
use BackedEnum;
use Carbon\Carbon;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/** Jadwal pelayanan satu bulan dalam format seperti jadwal cetak gereja. */
class JadwalBulanan extends Page
{
    protected string $view = 'filament.pages.jadwal-bulanan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static string|\UnitEnum|null $navigationGroup = 'Pelayanan';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Jadwal Bulanan';

    public string $bulan;

    public function mount(): void
    {
        $this->bulan = now()->format('Y-m');
    }

    public static function canAccess(): bool
    {
        return auth()->user()->lihatSemuaJemaat();
    }

    public function geser(int $n): void
    {
        $this->bulan = $this->awal()->addMonthsNoOverflow($n)->format('Y-m');
    }

    private function awal(): Carbon
    {
        return rescue(fn () => Carbon::createFromFormat('Y-m-d', $this->bulan.'-01')->startOfDay(), now()->startOfMonth(), false);
    }

    protected function getHeaderActions(): array
    {
        return [ListWorshipSchedules::importAction()];
    }

    protected function getViewData(): array
    {
        $awal = $this->awal();
        $jadwal = WorshipSchedule::with(['assignments.member', 'assignments.alias'])
            ->whereBetween('date', [$awal->toDateString(), $awal->copy()->endOfMonth()->toDateString()])
            ->orderBy('date')->get();
        $cek = app(\App\Services\PemeriksaJadwal::class)->periksa($jadwal);

        return [
            'judul' => $awal->translatedFormat('F Y'),
            'jadwal' => $jadwal,
            'peringatan' => $cek['peringatan'],
            'tandai' => $cek['tandai'],
            'batasTugas' => (int) config('sij.batas_tugas_per_bulan', 3),
            'bisaKelola' => auth()->user()->kelolaJadwal(),
            'urlBaru' => WorshipScheduleResource::getUrl('create'),
            'urlDaftar' => WorshipScheduleResource::getUrl('index'),
        ];
    }
}
