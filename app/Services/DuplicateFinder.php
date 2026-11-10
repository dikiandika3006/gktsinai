<?php

namespace App\Services;

use App\Models\Member;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * DJ-10: deteksi duplikat (nama mirip + tanggal lahir +-3 hari) dan penggabungan.
 */
class DuplicateFinder
{
    public const CACHE_JUMLAH = 'sij.duplikat_count';

    /** Jumlah pasangan kemungkinan duplikat (di-cache untuk badge menu). */
    public function jumlah(): int
    {
        return Cache::remember(self::CACHE_JUMLAH, 3600, fn () => $this->allPairs()->count());
    }

    public static function normalize(string $nama): string
    {
        return Str::of($nama)->lower()->ascii()->replaceMatches('/[^a-z ]/', '')->squish()->value();
    }

    public static function similar(string $a, string $b): bool
    {
        $a = self::normalize($a);
        $b = self::normalize($b);
        if ($a === $b) {
            return true;
        }
        similar_text($a, $b, $pct);

        return $pct >= 85 || levenshtein($a, $b) <= 2;
    }

    /** Cari kandidat duplikat untuk satu data (saat input/import). */
    public function candidatesFor(string $nama, ?string $tanggalLahir, ?int $kecualiId = null): Collection
    {
        if (! $tanggalLahir) {
            return Member::query()->whereKeyNot($kecualiId)->whereNull('birth_date')->get()
                ->filter(fn ($m) => self::normalize($m->name) === self::normalize($nama))->values();
        }
        $tgl = Carbon::parse($tanggalLahir);

        return Member::query()
            ->when($kecualiId, fn ($q) => $q->whereKeyNot($kecualiId))
            ->whereBetween('birth_date', [$tgl->copy()->subDays(3), $tgl->copy()->addDays(3)])
            ->get()
            ->filter(fn (Member $m) => self::similar($m->name, $nama))
            ->values();
    }

    /** Semua pasangan kemungkinan duplikat di database. */
    public function allPairs(): Collection
    {
        $members = Member::whereNotNull('birth_date')->with('family')->orderBy('birth_date')->get();
        $pairs = collect();
        $n = $members->count();
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $a = $members[$i];
                $b = $members[$j];
                if ($a->birth_date->diffInDays($b->birth_date) > 3) {
                    break;
                }
                if (self::similar($a->name, $b->name)) {
                    $pairs->push([$a, $b]);
                }
            }
        }

        return $pairs;
    }

    /** Gabungkan $hapus ke $simpan: pindahkan relasi, isi kolom yang kosong, lalu hapus. */
    public function merge(Member $simpan, Member $hapus): Member
    {
        return DB::transaction(function () use ($simpan, $hapus) {
            foreach ($hapus->getAttributes() as $kolom => $nilai) {
                if (in_array($kolom, ['id', 'code', 'created_at', 'updated_at'], true)) {
                    continue;
                }
                if (($simpan->{$kolom} === null || $simpan->{$kolom} === '') && $nilai !== null) {
                    $simpan->{$kolom} = $nilai;
                }
            }
            if (! $simpan->old_registration_no && $hapus->old_registration_no) {
                $simpan->old_registration_no = $hapus->old_registration_no;
            }
            $simpan->notes = trim(($simpan->notes ?? '')."\nDigabung dari {$hapus->code} ({$hapus->name}) pada ".now()->format('d/m/Y'));
            $simpan->save();

            DB::table('member_events')->where('member_id', $hapus->id)->update(['member_id' => $simpan->id]);
            DB::table('visits')->where('member_id', $hapus->id)->update(['member_id' => $simpan->id]);
            DB::table('guests')->where('converted_member_id', $hapus->id)->update(['converted_member_id' => $simpan->id]);
            DB::table('families')->where('head_member_id', $hapus->id)->update(['head_member_id' => $simpan->id]);

            $sudahHadir = DB::table('attendances')->where('member_id', $simpan->id)->pluck('activity_session_id');
            DB::table('attendances')->where('member_id', $hapus->id)->whereNotIn('activity_session_id', $sudahHadir)->update(['member_id' => $simpan->id]);

            $sudahKomisi = DB::table('commission_members')->where('member_id', $simpan->id)->pluck('commission_id');
            DB::table('commission_members')->where('member_id', $hapus->id)->whereNotIn('commission_id', $sudahKomisi)->update(['member_id' => $simpan->id]);

            $hapus->delete();

            return $simpan->refresh();
        });
    }
}
