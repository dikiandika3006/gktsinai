<?php

namespace App\Services;

use App\Models\Family;
use App\Models\Member;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Merapikan susunan keluarga: ganti kepala KK dan pindah anggota antar-KK. */
class KeluargaService
{
    /** Jadikan $baru kepala KK-nya sendiri; kepala lama (bila masih di KK ini) diberi hubungan baru. */
    public function jadikanKepala(Member $baru, string $hubunganKepalaLama = 'lainnya'): void
    {
        $family = $baru->family ?? throw new InvalidArgumentException('Jemaat belum masuk KK.');

        DB::transaction(function () use ($family, $baru, $hubunganKepalaLama) {
            $lama = $family->head;
            if ($lama && $lama->id !== $baru->id && $lama->family_id === $family->id) {
                $lama->update(['family_relation' => $hubunganKepalaLama]);
            }
            $baru->update(['family_relation' => 'kepala']);
            $family->update(['head_member_id' => $baru->id]);
        });
    }

    /**
     * Pindahkan satu/beberapa jemaat ke KK lain, atau ke KK baru bila $tujuan null.
     *
     * Opsi:
     *  - hubungan: hubungan baru di KK tujuan (untuk yang bukan kepala); null = tetap.
     *  - kepala_id: jemaat (di antara yang dipindah) yang menjadi kepala KK tujuan. Wajib untuk KK baru.
     *  - kepala_pengganti: [family_id lama => member_id] kepala baru untuk KK yang ditinggal kepalanya.
     *  - data_kk: [address, region_id, phone] untuk KK baru.
     *
     * @param  Collection<int, Member>  $members
     * @return array{family: Family, catatan: list<string>}
     */
    public function pindah(Collection $members, ?Family $tujuan, array $opsi = []): array
    {
        if ($members->isEmpty()) {
            throw new InvalidArgumentException('Tidak ada jemaat yang dipilih.');
        }
        $ids = $members->pluck('id');
        $kepalaId = $opsi['kepala_id'] ?? null;
        if ($kepalaId && ! $ids->contains($kepalaId)) {
            throw new InvalidArgumentException('Kepala KK harus salah satu jemaat yang dipindah.');
        }
        if (! $tujuan && ! $kepalaId) {
            $kepalaId = $members->count() === 1 ? $members->first()->id : throw new InvalidArgumentException('Pilih kepala untuk KK baru.');
        }

        return DB::transaction(function () use ($members, $ids, $tujuan, $opsi, $kepalaId) {
            $catatan = [];
            $kkLama = Family::whereIn('id', $members->pluck('family_id')->filter()->unique())
                ->when($tujuan, fn ($q) => $q->whereKeyNot($tujuan->id))->get();

            $tujuan ??= Family::create(collect($opsi['data_kk'] ?? [])->only(['address', 'region_id', 'phone'])->all());

            foreach ($members as $m) {
                $hubungan = $m->id === $kepalaId ? 'kepala' : ($opsi['hubungan'] ?? $m->family_relation);
                if ($hubungan === 'kepala' && $m->id !== $kepalaId) {
                    $hubungan = 'lainnya'; // hanya satu kepala per KK
                }
                $m->update(['family_id' => $tujuan->id, 'family_relation' => $hubungan]);
            }

            if ($kepalaId) {
                $lama = $tujuan->head;
                if ($lama && $lama->id !== $kepalaId && $lama->family_id === $tujuan->id && $lama->family_relation === 'kepala') {
                    $lama->update(['family_relation' => 'lainnya']);
                }
                $tujuan->update(['head_member_id' => $kepalaId]);
            }

            // KK yang ditinggalkan: cari kepala pengganti, hapus bila kosong.
            foreach ($kkLama as $f) {
                if ($f->head_member_id && $ids->contains($f->head_member_id)) {
                    $pengganti = Member::where('family_id', $f->id)->find($opsi['kepala_pengganti'][$f->id] ?? null);
                    $f->update(['head_member_id' => $pengganti?->id]);
                    $pengganti?->update(['family_relation' => 'kepala']);
                    $catatan[] = $pengganti
                        ? "{$f->code}: kepala keluarga sekarang {$pengganti->name}."
                        : "{$f->code}: belum ada kepala keluarga, mohon ditentukan.";
                }
                if (! $f->members()->exists()) {
                    if ($f->visits()->exists()) {
                        $catatan[] = "{$f->code} sekarang kosong (tidak dihapus karena punya riwayat visitasi).";
                    } else {
                        $catatan[] = "{$f->code} kosong dan dihapus.";
                        $f->delete();
                    }
                }
            }

            return ['family' => $tujuan->refresh(), 'catatan' => $catatan];
        });
    }
}
