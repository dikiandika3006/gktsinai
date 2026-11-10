<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\JadwalBulanan;
use App\Filament\Resources\Members\MemberResource;
use App\Filament\Resources\Visits\VisitResource;
use App\Models\Member;
use App\Models\Visit;
use App\Models\WorshipSchedule;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;

/** Banner sapaan di atas dashboard: salam, tanggal, ibadah Minggu berikutnya, dan tombol pintas sesuai peran. */
class SapaanBanner extends Widget
{
    protected static ?int $sort = -10;

    protected string $view = 'filament.widgets.sapaan-banner';

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        $u = auth()->user();
        $jam = (int) now()->format('H');
        $salam = match (true) {
            $jam < 11 => 'Selamat pagi',
            $jam < 15 => 'Selamat siang',
            $jam < 18 => 'Selamat sore',
            default => 'Selamat malam',
        };

        $minggu = WorshipSchedule::with('assignments')->whereDate('date', '>=', today())->orderBy('date')->first();
        $pembicara = $minggu?->assignments->where('peran', 'pembicara')->pluck('nama')->unique()->join(', ');

        $aksi = array_values(array_filter([
            $u->kelolaKehadiran() ? ['Buka Absensi', route('absen.index'), Heroicon::OutlinedDevicePhoneMobile, true, true] : null,
            $u->kelolaJemaat() ? ['Tambah Jemaat', MemberResource::getUrl('create'), Heroicon::OutlinedUserPlus, false, false] : null,
            $u->kelolaTamu() ? ['Catat Tamu', route('tamu.create'), Heroicon::OutlinedHandRaised, false, true] : null,
            $u->can('create', Visit::class) ? ['Jadwalkan Kunjungan', VisitResource::getUrl('create'), Heroicon::OutlinedHome, false, false] : null,
            JadwalBulanan::canAccess() ? ['Jadwal Pelayanan', JadwalBulanan::getUrl(), Heroicon::OutlinedCalendarDays, false, false] : null,
        ]));

        return [
            'salam' => $salam,
            'nama' => str($u->name)->before(' ')->toString() ?: $u->name,
            'tanggal' => now()->translatedFormat('l, j F Y'),
            'minggu' => $minggu,
            'pembicara' => $pembicara,
            'ulangTahun' => $u->lihatSemuaJemaat() ? Member::query()->tercatat()->whereNotNull('birth_date')->get(['birth_date'])
                ->filter(fn ($m) => $m->birth_date->copy()->year(now()->year)->between(today()->startOfWeek(), today()->endOfWeek()))->count() : null,
            'aksi' => $aksi,
        ];
    }
}
