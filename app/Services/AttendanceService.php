<?php

namespace App\Services;

use App\Models\ActivitySession;
use App\Models\Attendance;
use App\Models\Commission;
use App\Models\Member;
use Illuminate\Support\Collection;

class AttendanceService
{
    /**
     * KH-07: jemaat aktif yang tidak hadir N sesi Ibadah Umum terakhir berturut-turut,
     * atau tidak hadir N pertemuan terakhir komisinya.
     *
     * @return Collection<int, array{member: Member, reason: string, last_seen: ?string}>
     */
    public function attentionList(?int $threshold = null): Collection
    {
        $n = $threshold ?? (int) config('sij.absence_threshold', 3);
        $hasil = collect();

        // Ibadah Umum
        // Satu Minggu bisa punya beberapa kebaktian (KU I, KU II): hitung per tanggal.
        $umum = ActivitySession::query()->whereHas('activityType', fn ($q) => $q->where('is_general_worship', true));
        $tanggal = (clone $umum)->where('date', '<=', today())
            ->select('date')->distinct()->orderByDesc('date')->limit($n)->pluck('date');
        $sesiUmum = $tanggal->isEmpty() ? collect()
            : (clone $umum)->where('date', '<=', today())->whereDate('date', '>=', $tanggal->last())->pluck('id');

        if ($tanggal->count() >= $n) {
            $hadir = Attendance::whereIn('activity_session_id', $sesiUmum)->whereNotNull('member_id')->pluck('member_id')->unique();
            Member::aktif()->whereNotIn('id', $hadir)->with('family.region')->get()
                ->each(fn (Member $m) => $hasil->put($m->id, [
                    'member' => $m,
                    'reason' => "Tidak hadir Ibadah Umum {$n} minggu berturut-turut",
                ]));
        }

        // Ibadah komisi (yang dicatat per nama)
        Commission::where('is_active', true)->where('count_only', false)->get()->each(function (Commission $c) use ($n, $hasil) {
            $sesi = ActivitySession::query()
                ->whereHas('activityType', fn ($q) => $q->where('commission_id', $c->id)->where('count_only', false))
                ->where('date', '<=', today())
                ->orderByDesc('date')->limit($n)->pluck('id');
            if ($sesi->count() < $n) {
                return;
            }
            $hadir = Attendance::whereIn('activity_session_id', $sesi)->whereNotNull('member_id')->pluck('member_id')->unique();
            $c->activeMembers()->aktif()->whereNotIn('members.id', $hadir)->with('family.region')->get()
                ->each(function (Member $m) use ($c, $n, $hasil) {
                    $alasan = "Tidak hadir {$c->name} {$n} pertemuan berturut-turut";
                    if ($hasil->has($m->id)) {
                        $row = $hasil->get($m->id);
                        $row['reason'] .= '; '.$alasan;
                        $hasil->put($m->id, $row);
                    } else {
                        $hasil->put($m->id, ['member' => $m, 'reason' => $alasan]);
                    }
                });
        });

        $terakhir = Attendance::query()
            ->whereIn('member_id', $hasil->keys())
            ->join('activity_sessions', 'activity_sessions.id', '=', 'attendances.activity_session_id')
            ->selectRaw('member_id, MAX(activity_sessions.date) as last_date')
            ->groupBy('member_id')->pluck('last_date', 'member_id');

        return $hasil->map(fn ($row, $id) => $row + ['last_seen' => $terakhir[$id] ?? null])->values();
    }

    /** ID jemaat dalam daftar perhatian (dipakai peta & usulan visitasi). */
    public function attentionMemberIds(): array
    {
        return $this->attentionList()->pluck('member.id')->all();
    }

    /**
     * KH-09: jemaat aktif yang tidak tercatat hadir di kegiatan mana pun selama N bulan.
     * Hanya dihitung bila sistem sudah punya data kehadiran selama N bulan.
     */
    public function passiveCandidates(): Collection
    {
        $bulan = (int) config('sij.passive_after_months', 3);
        $batas = today()->subMonths($bulan);

        $sesiPertama = ActivitySession::min('date');
        if (! $sesiPertama || $sesiPertama > $batas->toDateString()) {
            return collect();
        }

        $hadir = Attendance::query()
            ->join('activity_sessions', 'activity_sessions.id', '=', 'attendances.activity_session_id')
            ->where('activity_sessions.date', '>=', $batas)
            ->whereNotNull('member_id')->distinct()->pluck('member_id');

        return Member::aktif()->whereNotIn('id', $hadir)->with('family.region')->orderBy('name')->get();
    }
}
