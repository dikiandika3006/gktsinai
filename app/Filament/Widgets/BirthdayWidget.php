<?php

namespace App\Filament\Widgets;

use App\Models\Member;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** DJ-11: ulang tahun & ulang tahun pernikahan minggu ini. */
class BirthdayWidget extends Widget
{
    protected static ?int $sort = 5;

    protected string $view = 'filament.widgets.birthday-widget';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()->lihatSemuaJemaat() || auth()->user()->peran('koordinator_wilayah', 'koordinator_komisi');
    }

    /** Daftar orang yang tanggal (hari & bulan) jatuh pada minggu ini. */
    public static function thisWeek(string $kolom): Collection
    {
        $awal = Carbon::today()->startOfWeek(Carbon::MONDAY);
        $hari = collect(range(0, 6))->map(fn ($i) => $awal->copy()->addDays($i));
        $md = $hari->map(fn ($d) => $d->format('m-d'))->all();

        return Member::query()->visibleTo(auth()->user())->tercatat()->whereNotNull($kolom)
            ->whereRaw("DATE_FORMAT({$kolom}, '%m-%d') IN (".implode(',', array_fill(0, count($md), '?')).')', $md)
            ->with('family.region')->get()
            ->map(function (Member $m) use ($kolom, $awal) {
                $tgl = $m->{$kolom};
                $hariIni = Carbon::create($awal->year, $tgl->month, $tgl->day);
                if ($hariIni->lt($awal)) {
                    $hariIni->addYear();
                }

                return ['member' => $m, 'date' => $hariIni, 'years' => $hariIni->year - $tgl->year];
            })
            ->sortBy('date')->values();
    }

    protected function getViewData(): array
    {
        return [
            'birthdays' => self::thisWeek('birth_date'),
            'anniversaries' => self::thisWeek('marriage_date')
                ->unique(fn ($r) => $r['member']->family_id ?: $r['member']->id),
        ];
    }
}
