<?php

namespace App\Services;

use App\Models\Member;
use App\Models\ServantAlias;
use App\Support\Opsi;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Mencocokkan nama pelayan di jadwal ("Pnt. Anis W.", "Kel. Bp. Doni Kaleb", "Ibu Fera")
 * dengan data jemaat. Hasil manual disimpan di servant_aliases dan selalu diutamakan.
 */
class PelayanMatcher
{
    /** Gelar/sapaan => jenis kelamin yang tersirat (null = tidak diketahui). */
    public const GELAR = [
        'kel' => 'L', 'keluarga' => 'L', 'klg' => 'L',
        'bp' => 'L', 'bpk' => 'L', 'bapak' => 'L', 'pak' => 'L', 'sdr' => 'L', 'saudara' => 'L', 'mas' => 'L', 'opa' => 'L', 'om' => 'L', 'tn' => 'L',
        'ibu' => 'P', 'ib' => 'P', 'bu' => 'P', 'sdri' => 'P', 'saudari' => 'P', 'mbak' => 'P', 'oma' => 'P', 'tante' => 'P', 'ny' => 'P', 'nn' => 'P',
        'sdra' => null, 'pnt' => null, 'pdt' => null, 'ev' => null, 'ls' => null, 'vik' => null, 'gb' => null,
        'dr' => null, 'drs' => null, 'dra' => null, 'ir' => null, 'kak' => null, 'kakak' => null,
    ];

    /** Gelar hamba Tuhan: bila tidak ditemukan di data jemaat, diusulkan sebagai "pekerja". */
    public const GELAR_PEKERJA = ['pdt', 'ev', 'ls', 'vik', 'gb'];

    private ?Collection $jemaat = null;

    /** "Ibu Wiwit & Sdra. Henry" -> ["Ibu Wiwit", "Sdra. Henry"]. */
    public function pecah(string $teks): array
    {
        $teks = trim(preg_replace('/\s+/u', ' ', $teks));
        if ($teks === '' || $teks === '-') {
            return [];
        }
        $bagian = preg_split('/\s*(?:&|\/|;|,|\+|\bdan\b)\s*/iu', $teks);

        return array_values(array_filter(array_map('trim', $bagian), fn ($s) => $s !== '' && $s !== '-'));
    }

    /** Pisahkan gelar dari nama. */
    public function urai(string $nama): array
    {
        $kata = preg_split('/\s+/u', trim(str_replace('.', '. ', $nama)), -1, PREG_SPLIT_NO_EMPTY);
        $gelar = [];
        while (count($kata) > 1 && array_key_exists($k = mb_strtolower(rtrim($kata[0], '.')), self::GELAR)) {
            $gelar[] = $k;
            array_shift($kata);
        }
        $bersih = rtrim(trim(preg_replace('/\s+/u', ' ', implode(' ', $kata))), '.');

        $kelamin = null;
        foreach ($gelar as $g) {
            $kelamin ??= self::GELAR[$g];
        }

        return [
            'bersih' => $bersih,
            'kunci' => self::normal($bersih),
            'gelar' => $gelar,
            'kelamin' => $kelamin,
            'keluarga' => (bool) array_intersect($gelar, ['kel', 'keluarga', 'klg']),
            'pekerja' => (bool) array_intersect($gelar, self::GELAR_PEKERJA),
        ];
    }

    public static function normal(?string $s): string
    {
        $s = preg_replace('/[^a-z0-9 ]+/', ' ', Str::lower(Str::ascii((string) $s)));

        return trim(preg_replace('/\s+/', ' ', $s));
    }

    /**
     * Kunci + jemaat yang cocok untuk satu nama. Membuat entri kamus nama bila belum ada.
     *
     * @return array{kunci:string, keluarga:bool, member_id:?int}|null
     */
    public function resolve(string $nama): ?array
    {
        $u = $this->urai($nama);
        if ($u['kunci'] === '') {
            return null;
        }

        $alias = ServantAlias::firstOrNew(['key' => $u['kunci']]);
        if (! $alias->exists || (! $alias->is_manual && ! $alias->member_id)) {
            $alias->display_name = $alias->display_name ?: $nama;
            $alias->member_id = $this->cocokkan($u)?->id;
            if (! $alias->member_id && ! $alias->category && $u['pekerja']) {
                $alias->category = 'pekerja';
            }
            $alias->save();
        }

        return ['kunci' => $u['kunci'], 'keluarga' => $u['keluarga'], 'member_id' => $alias->member_id];
    }

    /** Cocokkan ulang semua nama yang tidak dihubungkan manual. Mengembalikan jumlah yang cocok. */
    public function cocokkanUlang(): int
    {
        $this->jemaat = null;
        $n = 0;
        ServantAlias::where('is_manual', false)->get()->each(function (ServantAlias $a) use (&$n) {
            $a->member_id = $this->cocokkan($this->urai($a->display_name))?->id;
            $a->save();
            $n += $a->member_id ? 1 : 0;
        });

        return $n;
    }

    /** Jemaat yang cocok, hanya bila hasilnya tunggal (tidak ambigu). */
    public function cocokkan(array $u): ?Member
    {
        $token = $u['kunci'] === '' ? [] : explode(' ', $u['kunci']);
        if (! $token) {
            return null;
        }
        // Huruf tunggal / disingkat ("Anis W.", "Monika K.") dianggap inisial.
        $asli = preg_split('/\s+/u', $u['bersih']);
        $inisial = [];
        foreach ($token as $i => $t) {
            $inisial[$i] = strlen($t) === 1 || str_ends_with($asli[$i] ?? '', '.');
        }

        $kandidat = $this->jemaat()->filter(function (array $m) use ($token, $inisial, $u) {
            if ($m['nama'] === $u['kunci'] || $m['alias'] === $u['kunci']) {
                return true;
            }
            foreach ($token as $i => $t) {
                $ok = false;
                foreach ($m['kata'] as $w) {
                    if ($w === $t || ($inisial[$i] && str_starts_with($w, $t))) {
                        $ok = true;
                        break;
                    }
                }
                if (! $ok) {
                    return false;
                }
            }

            return true;
        });

        // Persempit bila ambigu: nama persis, jenis kelamin dari sapaan, kepala keluarga untuk "Kel. Bp.", masih tercatat.
        $saring = [
            fn ($c) => $c->filter(fn ($m) => $m['nama'] === $u['kunci'] || $m['alias'] === $u['kunci']),
            fn ($c) => $u['kelamin'] ? $c->filter(fn ($m) => $m['kelamin'] === $u['kelamin']) : $c,
            fn ($c) => $u['keluarga'] ? $c->filter(fn ($m) => $m['kepala']) : $c,
            fn ($c) => $c->filter(fn ($m) => $m['tercatat']),
        ];
        foreach ($saring as $f) {
            if ($kandidat->count() <= 1) {
                break;
            }
            $sempit = $f($kandidat);
            if ($sempit->isNotEmpty()) {
                $kandidat = $sempit;
            }
        }

        return $kandidat->count() === 1 ? Member::find($kandidat->keys()->first()) : null;
    }

    private function jemaat(): Collection
    {
        return $this->jemaat ??= Member::query()->where('membership_status', '!=', 'meninggal')
            ->get(['id', 'name', 'alias', 'gender', 'family_relation', 'membership_status'])
            ->mapWithKeys(function (Member $m) {
                $nama = self::normal($m->name);
                $alias = $m->alias ? self::normal($m->alias) : null;

                return [$m->id => [
                    'nama' => $nama,
                    'alias' => $alias,
                    'kata' => array_values(array_unique(array_filter(explode(' ', $nama.' '.$alias)))),
                    'kelamin' => $m->gender,
                    'kepala' => $m->family_relation === 'kepala',
                    'tercatat' => in_array($m->membership_status, Opsi::MASIH_TERCATAT, true),
                ]];
            });
    }
}
