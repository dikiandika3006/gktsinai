<?php

namespace App\Services;

use App\Models\Family;
use App\Models\Member;
use App\Models\Visit;
use Illuminate\Support\Collection;

/**
 * VS-03: usulan visitasi otomatis.
 */
class VisitSuggestionService
{
    public function __construct(private AttendanceService $attendance) {}

    /** @return Collection<int, array{family: Family, member: ?Member, type: string, reason: string}> */
    public function suggestions(): Collection
    {
        $hasil = collect();
        $sudahDijadwalkan = Visit::where('status', 'rencana')->pluck('family_id')->filter()->flip();

        $tambah = function (?Family $f, ?Member $m, string $jenis, string $alasan) use ($hasil, $sudahDijadwalkan) {
            if (! $f || $sudahDijadwalkan->has($f->id)) {
                return;
            }
            $key = $f->id;
            if ($hasil->has($key)) {
                $row = $hasil->get($key);
                $row['reason'] .= '; '.$alasan;
                $hasil->put($key, $row);

                return;
            }
            $hasil->put($key, ['family' => $f, 'member' => $m, 'type' => $jenis, 'reason' => $alasan]);
        };

        // 1. Tidak hadir (KH-07)
        foreach ($this->attendance->attentionList() as $row) {
            $tambah($row['member']->family, $row['member'], 'pasif', $row['member']->name.': '.$row['reason']);
        }

        // 2. Jemaat baru (bergabung/konversi tamu 3 bulan terakhir)
        Member::tercatat()->with('family')->where(fn ($q) => $q->where('joined_at', '>=', today()->subMonths(3)))
            ->get()->each(fn (Member $m) => $tambah($m->family, $m, 'jemaat_baru', $m->name.': jemaat baru'));

        // 3. Lansia 70+
        $usiaLansia = (int) config('sij.elderly_age', 70);
        $batasKunjung = today()->subMonths(6);
        Member::tercatat()->with('family')->ageBetween($usiaLansia, null)->whereNotNull('birth_date')->get()
            ->filter(fn (Member $m) => $m->family && ! $m->family->visits()->where('status', 'selesai')->where('realized_date', '>=', $batasKunjung)->exists())
            ->each(fn (Member $m) => $tambah($m->family, $m, 'rutin', $m->name.": lansia {$m->age} tahun"));

        // 4. KK belum dikunjungi > 12 bulan
        $bulan = (int) config('sij.visit_overdue_months', 12);
        Family::query()
            ->whereHas('members', fn ($q) => $q->tercatat())
            ->whereDoesntHave('visits', fn ($q) => $q->where('status', 'selesai')->where('realized_date', '>=', today()->subMonths($bulan)))
            ->with('head')->get()
            ->each(fn (Family $f) => $tambah($f, null, 'rutin', "Belum dikunjungi lebih dari {$bulan} bulan"));

        return $hasil->values();
    }
}
