<?php

namespace App\Services;

use App\Models\ServiceAssignment;
use App\Models\WorshipSchedule;
use App\Support\Opsi;
use Illuminate\Support\Collection;

/**
 * Peringatan jadwal pelayanan:
 *  - dobel: satu orang memegang dua peran berbeda di kebaktian yang sama (bertabrakan waktunya);
 *  - terlalu sering: satu orang bertugas lebih dari N Minggu dalam jadwal yang diperiksa.
 * Pelayan berkategori "pekerja" (pendeta, penginjil, dst.) tidak dihitung "terlalu sering".
 */
class PemeriksaJadwal
{
    /** Kunci identitas pelayan: jemaat yang sama, atau nama yang sama bila belum tersambung. */
    public static function identitas(ServiceAssignment $a): string
    {
        return $a->member_id ? 'm'.$a->member_id : 'k'.$a->kunci;
    }

    private static function namaTampil(ServiceAssignment $a): string
    {
        return $a->member?->name ?? $a->nama;
    }

    /** "Gabungan"/kosong berlaku untuk semua kebaktian di tanggal itu. */
    private static function bertabrakan(?string $k1, ?string $k2): bool
    {
        $semua = fn ($k) => $k === null || $k === 'Gabungan';

        return $k1 === $k2 || $semua($k1) || $semua($k2);
    }

    /**
     * @param  Collection<int, WorshipSchedule>  $jadwal  (assignments.member & assignments.alias dimuat)
     * @return array{peringatan: list<array{jenis:string, teks:string}>, tandai: array<string, true>}
     */
    public function periksa(Collection $jadwal, ?int $batas = null): array
    {
        $batas ??= (int) config('sij.batas_tugas_per_bulan', 3);
        $peringatan = [];
        $tandai = [];

        foreach ($jadwal as $j) {
            foreach ($j->assignments->groupBy(fn ($a) => self::identitas($a)) as $id => $tugas) {
                $tugas = $tugas->values();
                for ($x = 0; $x < $tugas->count(); $x++) {
                    for ($y = $x + 1; $y < $tugas->count(); $y++) {
                        [$a, $b] = [$tugas[$x], $tugas[$y]];
                        if ($a->peran !== $b->peran && self::bertabrakan($a->kebaktian, $b->kebaktian)) {
                            $peringatan["dobel|{$j->id}|{$id}"] = ['jenis' => 'dobel', 'teks' => sprintf(
                                '%s: %s bertugas dobel sebagai %s dan %s%s.',
                                $j->date->translatedFormat('d M'), self::namaTampil($a),
                                Opsi::label(Opsi::PERAN_PELAYANAN, $a->peran), Opsi::label(Opsi::PERAN_PELAYANAN, $b->peran),
                                $a->kebaktian && $a->kebaktian === $b->kebaktian ? " di {$a->kebaktian}" : '',
                            )];
                            $tandai[$id] = true;
                        }
                    }
                }
            }
        }

        $semua = $jadwal->flatMap(fn ($j) => $j->assignments->map(fn ($a) => [$a, $j->date->toDateString()]));
        foreach ($semua->groupBy(fn ($p) => self::identitas($p[0])) as $id => $daftar) {
            $a = $daftar->first()[0];
            if ($a->alias?->category === 'pekerja') {
                continue;
            }
            $minggu = $daftar->pluck(1)->unique();
            if ($minggu->count() > $batas) {
                $peringatan["sering|{$id}"] = ['jenis' => 'sering', 'teks' => sprintf(
                    '%s bertugas %d Minggu (batas %d): %s.', self::namaTampil($a), $minggu->count(), $batas,
                    $minggu->sort()->map(fn ($d) => \Illuminate\Support\Carbon::parse($d)->format('d/m'))->join(', '),
                )];
                $tandai[$id] = true;
            }
        }

        return ['peringatan' => array_values($peringatan), 'tandai' => $tandai];
    }
}
