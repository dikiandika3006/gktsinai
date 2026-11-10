<?php

namespace App\Filament\Widgets;

use App\Models\ActivitySession;
use App\Models\Attendance;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;

/** Ibadah Umum terakhir: jemaat yang hadir pertama kali tercatat, atau kembali setelah lama tidak hadir. */
class HadirBaruKembali extends Widget
{
    protected static ?int $sort = 5;

    protected string $view = 'filament.widgets.hadir-baru-kembali';

    public static function canView(): bool
    {
        return auth()->user()->lihatSemuaJemaat() || auth()->user()->kelolaKehadiran();
    }

    /** @return array{tanggal: ?Carbon, pertama: \Illuminate\Support\Collection, kembali: \Illuminate\Support\Collection} */
    public static function data(int $mingguAbsen = 4): array
    {
        $umum = fn () => ActivitySession::query()->whereHas('activityType', fn ($q) => $q->where('is_general_worship', true));
        $terakhir = $umum()->whereDate('date', '<=', today())->max('date');
        if (! $terakhir) {
            return ['tanggal' => null, 'pertama' => collect(), 'kembali' => collect()];
        }
        $tgl = Carbon::parse($terakhir);
        $hadir = Attendance::whereNotNull('member_id')->whereIn('activity_session_id', $umum()->whereDate('date', $tgl)->pluck('id'))
            ->with('member.family.head')->get()->unique('member_id');
        $sebelum = Attendance::query()->whereIn('member_id', $hadir->pluck('member_id'))
            ->join('activity_sessions', 'activity_sessions.id', '=', 'attendances.activity_session_id')
            ->whereDate('activity_sessions.date', '<', $tgl)
            ->groupBy('member_id')->selectRaw('member_id, MAX(activity_sessions.date) terakhir')->pluck('terakhir', 'member_id');

        $pertama = $hadir->filter(fn ($a) => ! isset($sebelum[$a->member_id]))->map(fn ($a) => ['member' => $a->member, 'sejak' => null]);
        $kembali = $hadir->filter(fn ($a) => isset($sebelum[$a->member_id]) && Carbon::parse($sebelum[$a->member_id])->lte($tgl->copy()->subWeeks($mingguAbsen)))
            ->map(fn ($a) => ['member' => $a->member, 'sejak' => Carbon::parse($sebelum[$a->member_id])]);

        return [
            'tanggal' => $tgl,
            'pertama' => $pertama->filter(fn ($x) => $x['member'])->sortBy('member.name')->values(),
            'kembali' => $kembali->filter(fn ($x) => $x['member'])->sortBy('sejak')->values(),
        ];
    }

    protected function getViewData(): array
    {
        return self::data();
    }
}
