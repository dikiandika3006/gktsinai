<?php

namespace App\Services;

use App\Models\Guest;
use App\Models\Member;
use App\Models\MemberEvent;
use App\Models\Region;
use App\Support\Opsi;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Modul Pertumbuhan Jemaat (PJ). Semua angka dihitung dari data & riwayat mutasi.
 */
class GrowthService
{
    /** PJ-01: rekap gabungan seperti sheet "Rekapitulasi Statistik". */
    public function statusSummary(): array
    {
        $rows = Member::query()
            ->selectRaw('membership_status, activity_status, COUNT(*) as total')
            ->groupBy('membership_status', 'activity_status')->get();

        $hasil = [];
        foreach ($rows as $r) {
            $hasil[$this->statusLabel($r->membership_status, $r->activity_status)] =
                ($hasil[$this->statusLabel($r->membership_status, $r->activity_status)] ?? 0) + $r->total;
        }
        arsort($hasil);

        return $hasil;
    }

    public function statusLabel(?string $keanggotaan, ?string $keaktifan): string
    {
        return match (true) {
            $keanggotaan === 'meninggal' => 'Meninggal',
            $keanggotaan === 'keluar' => 'Atestasi Keluar',
            in_array($keanggotaan, Opsi::ANGGOTA_RESMI, true) => 'Anggota '.($keaktifan === 'pasif' ? 'Pasif' : 'Aktif'),
            default => 'Simpatisan '.($keaktifan === 'pasif' ? 'Pasif' : 'Aktif'),
        };
    }

    public const KOLOM_STATUS = ['Anggota Aktif', 'Anggota Pasif', 'Simpatisan Aktif', 'Simpatisan Pasif', 'Atestasi Keluar', 'Meninggal'];

    /** PJ-02: tabel wilayah x status. */
    public function regionMatrix(): Collection
    {
        $rows = Member::query()
            ->leftJoin('families', 'families.id', '=', 'members.family_id')
            ->leftJoin('regions', 'regions.id', '=', 'families.region_id')
            ->selectRaw("COALESCE(regions.name, 'Belum Didefinisikan') as wilayah, membership_status, activity_status, COUNT(*) as total")
            ->groupBy('wilayah', 'membership_status', 'activity_status')->get();

        $wilayah = Region::orderBy('name')->pluck('name')->push('Belum Didefinisikan');
        $matrix = $wilayah->mapWithKeys(fn ($w) => [$w => array_fill_keys(self::KOLOM_STATUS, 0)])->all();
        foreach ($rows as $r) {
            $label = $this->statusLabel($r->membership_status, $r->activity_status);
            $matrix[$r->wilayah][$label] = ($matrix[$r->wilayah][$label] ?? 0) + $r->total;
        }

        return collect($matrix)
            ->map(fn ($kolom, $w) => ['wilayah' => $w] + $kolom + ['Total' => array_sum($kolom)])
            ->filter(fn ($r) => $r['Total'] > 0 || $r['wilayah'] !== 'Belum Didefinisikan')
            ->values();
    }

    /**
     * PJ-03: masuk vs keluar per periode.
     * Masuk: baptis, sidi, atestasi masuk, tamu jadi anggota. Keluar: atestasi keluar, wafat.
     *
     * @param  'month'|'year'  $per
     */
    public function growthSeries(string $per = 'month', int $periods = 12, ?int $regionId = null): array
    {
        $akhir = today()->endOfMonth();
        $awal = $per === 'year' ? today()->startOfYear()->subYears($periods - 1) : today()->startOfMonth()->subMonths($periods - 1);
        $fmt = $per === 'year' ? 'Y' : 'Y-m';

        $label = [];
        for ($d = $awal->copy(); $d <= $akhir; $per === 'year' ? $d->addYear() : $d->addMonth()) {
            $label[$d->format($fmt)] = ['masuk' => 0, 'keluar' => 0, 'detail' => []];
        }

        $events = MemberEvent::query()->whereNotNull('date')->whereBetween('date', [$awal, $akhir])
            ->whereIn('type', ['baptis', 'sidi', 'atestasi_masuk', 'atestasi_keluar', 'wafat'])
            ->when($regionId, fn ($q) => $q->whereHas('member.family', fn ($f) => $f->where('region_id', $regionId)))
            ->get(['type', 'date']);

        foreach ($events as $e) {
            $k = $e->date->format($fmt);
            if (! isset($label[$k])) {
                continue;
            }
            $arah = in_array($e->type, ['atestasi_keluar', 'wafat'], true) ? 'keluar' : 'masuk';
            $label[$k][$arah]++;
            $label[$k]['detail'][$e->type] = ($label[$k]['detail'][$e->type] ?? 0) + 1;
        }

        Guest::query()->whereNotNull('converted_at')->whereBetween('converted_at', [$awal, $akhir])
            ->when($regionId, fn ($q) => $q->whereHas('convertedMember.family', fn ($f) => $f->where('region_id', $regionId)))
            ->get(['converted_at'])->each(function ($g) use (&$label, $fmt) {
                $k = $g->converted_at->format($fmt);
                if (isset($label[$k])) {
                    $label[$k]['masuk']++;
                    $label[$k]['detail']['tamu_jadi_anggota'] = ($label[$k]['detail']['tamu_jadi_anggota'] ?? 0) + 1;
                }
            });

        return $label;
    }

    /** PJ-04: piramida usia per 10 tahun. */
    public function agePyramid(): array
    {
        $kelompok = [];
        foreach (range(0, 80, 10) as $a) {
            $kelompok[$a >= 80 ? '80+' : "{$a}-".($a + 9)] = ['L' => 0, 'P' => 0];
        }
        Member::tercatat()->whereNotNull('birth_date')->get(['birth_date', 'gender', 'death_date'])
            ->each(function (Member $m) use (&$kelompok) {
                $u = $m->age;
                $k = $u >= 80 ? '80+' : (intdiv($u, 10) * 10).'-'.(intdiv($u, 10) * 10 + 9);
                if ($m->gender && isset($kelompok[$k])) {
                    $kelompok[$k][$m->gender]++;
                }
            });

        return $kelompok;
    }

    /** PJ-05: progres target tahun berjalan (anggota baru = baptis dewasa + sidi + atestasi masuk + tamu jadi anggota). */
    public function newMembersInYear(int $tahun): int
    {
        $awal = Carbon::create($tahun)->startOfYear();
        $akhir = $awal->copy()->endOfYear();

        return MemberEvent::whereIn('type', ['sidi', 'atestasi_masuk', 'baptis'])->whereBetween('date', [$awal, $akhir])->count()
            + Guest::whereBetween('converted_at', [$awal, $akhir])->count();
    }

    public function totals(): array
    {
        return [
            'tercatat' => Member::tercatat()->count(),
            'anggota' => Member::whereIn('membership_status', Opsi::ANGGOTA_RESMI)->count(),
            'aktif' => Member::aktif()->count(),
            'kk' => \App\Models\Family::whereHas('members', fn ($q) => $q->tercatat())->count(),
        ];
    }
}
