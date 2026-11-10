<?php

namespace App\Services;

use App\Imports\RawImport;
use App\Models\Family;
use App\Models\Member;
use App\Models\MemberEvent;
use App\Models\Region;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * DJ-09: import Excel dengan pemetaan kolom, pratinjau, dan laporan baris gagal.
 * Juga menerapkan aturan migrasi bagian 2, 5, dan 7 SRS.
 */
class MemberImporter
{
    /** Field tujuan => [label, kata kunci header untuk tebakan otomatis]. */
    public const FIELDS = [
        'code' => ['Kode jemaat (Display No / SIB...)', ['display no', 'id jemaat', 'kode jemaat', 'display']],
        'old_registration_no' => ['No Induk lama', ['no induk', 'nomor induk', 'induk']],
        'name' => ['Nama *', ['nama lengkap', 'nama jemaat', 'nama']],
        'alias' => ['Alias / panggilan', ['alias', 'panggilan']],
        'gender' => ['Kelamin', ['kelamin', 'jenis kelamin', 'gender', 'l/p', 'sex']],
        'birth_place' => ['Tempat lahir', ['tempat lahir']],
        'birth_date' => ['Tanggal lahir', ['tanggal lahir', 'tgl lahir', 'tgl. lahir', 'lahir']],
        'phone' => ['No. HP', ['no. handphone', 'no handphone', 'handphone', 'no hp', 'hp', 'telepon', 'kontak']],
        'address' => ['Alamat', ['alamat']],
        'region' => ['Wilayah', ['wilayah', 'region', 'rayon']],
        'family_code' => ['ID KK', ['id kk', 'kode kk', 'no kk', 'kk']],
        'family_relation' => ['Hubungan keluarga', ['hubungan', 'status keluarga', 'status dalam keluarga', 'posisi']],
        'status' => ['Status (format lama)', ['status jemaat', 'status']],
        'marriage_date' => ['Tanggal pernikahan', ['tgl pernikahan', 'tanggal pernikahan', 'tgl nikah', 'pernikahan']],
        'baptis_date' => ['Tanggal baptis', ['tgl baptis', 'tanggal baptis']],
        'sidi_date' => ['Tanggal sidi', ['tgl sidi', 'tanggal sidi']],
        'atestasi_masuk_date' => ['Tanggal atestasi masuk', ['atestasi masuk', 'tgl atestasi masuk']],
        'atestasi_masuk_church' => ['Gereja asal atestasi masuk', ['gereja asal']],
        'atestasi_keluar_date' => ['Tanggal atestasi keluar', ['atestasi keluar', 'tgl atestasi keluar']],
        'atestasi_keluar_church' => ['Gereja tujuan atestasi keluar', ['gereja tujuan']],
        'death_date' => ['Tanggal wafat', ['tgl wafat', 'tanggal wafat', 'wafat', 'meninggal']],
    ];

    /** Pemetaan status lama (bagian 5 SRS). */
    public const STATUS_LAMA = [
        'jemaat aktif' => ['anggota', 'aktif'],
        'jemaat non-aktif' => ['anggota', 'pasif'],
        'jemaat non aktif' => ['anggota', 'pasif'],
        'non-jemaat aktif' => ['simpatisan', 'aktif'],
        'non jemaat aktif' => ['simpatisan', 'aktif'],
        'non-jemaat non-aktif' => ['simpatisan', 'pasif'],
        'non jemaat non aktif' => ['simpatisan', 'pasif'],
        'atestasi keluar' => ['keluar', null],
        'meninggal' => ['meninggal', null],
        'aktif' => ['anggota', 'aktif'],
    ];

    private array $regionCache = [];

    /** @return array{sheets: array<int,string>, headings: array<int,string>, rows: array<int,array>} */
    public function read(string $path, int $sheet = 0): array
    {
        $all = Excel::toArray(new RawImport, $path);
        $data = $all[$sheet] ?? [];
        // Cari baris header: baris pertama yang punya >= 3 sel terisi.
        $headerIndex = 0;
        foreach ($data as $i => $row) {
            if (count(array_filter($row, fn ($v) => $v !== null && $v !== '')) >= 3) {
                $headerIndex = $i;
                break;
            }
        }
        $headings = array_map(fn ($h) => trim((string) $h), $data[$headerIndex] ?? []);
        $rows = array_slice($data, $headerIndex + 1);
        $rows = array_values(array_filter($rows, fn ($r) => count(array_filter($r, fn ($v) => $v !== null && trim((string) $v) !== '')) > 0));

        return ['sheets' => array_keys($all), 'headings' => $headings, 'rows' => $rows, 'header_row' => $headerIndex + 1];
    }

    /** Tebak pemetaan kolom dari judul kolom. @return array<string, ?int> field => index kolom */
    public function guessMapping(array $headings): array
    {
        $norm = array_map(fn ($h) => Str::of($h)->lower()->squish()->value(), $headings);
        $map = [];
        $terpakai = [];
        foreach (self::FIELDS as $field => [, $keys]) {
            $map[$field] = null;
            foreach ($keys as $k) {
                foreach ($norm as $i => $h) {
                    if (isset($terpakai[$i]) || $h === '') {
                        continue;
                    }
                    if ($h === $k || (strlen($k) > 3 && str_contains($h, $k))) {
                        $map[$field] = $i;
                        $terpakai[$i] = true;
                        break 2;
                    }
                }
            }
        }

        return $map;
    }

    /** Ubah satu baris mentah jadi data bersih + daftar error/peringatan. */
    public function transform(array $row, array $mapping): array
    {
        $ambil = function (string $f) use ($row, $mapping) {
            $i = $mapping[$f] ?? null;
            if ($i === null || $i === '') {
                return null;
            }
            $v = $row[(int) $i] ?? null;
            if (is_string($v)) {
                $v = trim(preg_replace('/\s+/u', ' ', $v));
            }

            return ($v === '' || $v === '-') ? null : $v;
        };

        $err = [];
        $warn = [];
        $d = [];

        $nama = $ambil('name');
        if (! $nama) {
            $err[] = 'Nama kosong';
        } else {
            // Nama huruf kapital semua -> Title Case.
            $d['name'] = (mb_strtoupper($nama) === $nama && preg_match('/[A-Z]/', $nama)) ? Str::title(mb_strtolower($nama)) : $nama;
        }

        $d['code'] = $ambil('code') ? strtoupper((string) $ambil('code')) : null;
        $d['old_registration_no'] = $ambil('old_registration_no') !== null ? (string) $ambil('old_registration_no') : null;
        $d['alias'] = $ambil('alias');
        $d['birth_place'] = $ambil('birth_place');
        $d['phone'] = $ambil('phone') !== null ? (string) $ambil('phone') : null;
        $d['address'] = $ambil('address');

        $g = Str::lower((string) $ambil('gender'));
        $d['gender'] = match (true) {
            in_array($g, ['l', 'laki-laki', 'laki laki', 'pria', 'male', 'm'], true) => 'L',
            in_array($g, ['p', 'perempuan', 'wanita', 'female', 'f', 'w'], true) => 'P',
            default => null,
        };
        if (! $d['gender']) {
            $warn[] = 'Kelamin kosong';
        }

        foreach (['birth_date', 'marriage_date', 'baptis_date', 'sidi_date', 'atestasi_masuk_date', 'atestasi_keluar_date', 'death_date'] as $f) {
            $raw = $ambil($f);
            $d[$f] = $this->parseDate($raw);
            if ($raw !== null && $d[$f] === null && ! $this->isEmptyDate($raw)) {
                $warn[] = "Tanggal tidak terbaca ({$f}: {$raw})";
            }
        }
        if (! $d['birth_date']) {
            $warn[] = 'Tanggal lahir kosong';
        }

        $d['atestasi_masuk_church'] = $ambil('atestasi_masuk_church');
        $d['atestasi_keluar_church'] = $ambil('atestasi_keluar_church');

        $d['region_raw'] = $ambil('region');
        $d['family_code'] = $ambil('family_code') ? strtoupper(str_replace(' ', '', (string) $ambil('family_code'))) : null;

        $h = Str::lower((string) $ambil('family_relation'));
        $d['family_relation'] = match (true) {
            str_contains($h, 'kepala') => 'kepala',
            str_contains($h, 'suami') => 'suami',
            str_contains($h, 'istri') || str_contains($h, 'isteri') => 'istri',
            str_contains($h, 'anak') => 'anak',
            str_contains($h, 'orang tua') || str_contains($h, 'ayah') || str_contains($h, 'ibu') => 'orang_tua',
            $h !== '' => 'lainnya',
            default => null,
        };

        $status = Str::of((string) $ambil('status'))->lower()->squish()->value();
        [$d['membership_status'], $d['activity_status']] = self::STATUS_LAMA[$status] ?? ['simpatisan', 'aktif'];
        if ($status !== '' && ! isset(self::STATUS_LAMA[$status])) {
            $warn[] = "Status tidak dikenal: {$status} (dianggap Simpatisan Aktif)";
        }
        if ($d['death_date']) {
            $d['membership_status'] = 'meninggal';
            $d['activity_status'] = null;
        }
        if ($d['code'] && ! preg_match('/^[A-Z]{2,4}\d+$/', $d['code'])) {
            $warn[] = "Format kode tidak biasa: {$d['code']}";
        }

        return ['data' => $d, 'errors' => $err, 'warnings' => $warn];
    }

    public function isEmptyDate($v): bool
    {
        if ($v === null) {
            return true;
        }
        $s = trim((string) $v);

        return $s === '' || $s === '-' || $s === '0' || str_starts_with($s, '1900-01-0') || $s === '01/01/1900' || $s === '1' || $s === '0000-00-00';
    }

    public function parseDate($v): ?string
    {
        if ($this->isEmptyDate($v)) {
            return null;
        }
        try {
            if (is_numeric($v)) {
                $n = (float) $v;
                if ($n < 367) { // serial 1900-01-01 s.d. 1901
                    return null;
                }

                return Carbon::instance(ExcelDate::excelToDateTimeObject($n))->toDateString();
            }
            $s = trim((string) $v);
            foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'd.m.Y', 'Y/m/d', 'd/m/y', 'Y-m-d H:i:s'] as $fmt) {
                $c = \DateTime::createFromFormat('!'.$fmt, $s);
                if ($c && $c->format($fmt) === $s) {
                    return $c->format('Y') <= 1900 ? null : $c->format('Y-m-d');
                }
            }
            $c = Carbon::parse($s);

            return $c->year <= 1900 ? null : $c->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /** Cocokkan nama wilayah (dengan salah ketik) ke master wilayah. */
    public function resolveRegion(?string $raw, bool $create = true): ?Region
    {
        if ($raw === null) {
            return null;
        }
        $bersih = Str::of($raw)->replaceMatches('/[^\pL\s]/u', '')->squish()->title()->value();
        if ($bersih === '' || preg_match('/belum (di)?definisikan|tidak diketahui/i', $bersih)) {
            return null;
        }
        $key = Str::lower($bersih);
        if (array_key_exists($key, $this->regionCache)) {
            return $this->regionCache[$key];
        }
        $best = null;
        $bestPct = 0;
        foreach (Region::all() as $r) {
            similar_text(Str::lower($r->name), $key, $pct);
            if ($pct > $bestPct) {
                $bestPct = $pct;
                $best = $r;
            }
        }
        if ($best && $bestPct >= 80) {
            return $this->regionCache[$key] = $best;
        }
        if (! $create) {
            return $this->regionCache[$key] = null;
        }

        return $this->regionCache[$key] = Region::create(['name' => $bersih, 'map_color' => self::randomColor()]);
    }

    public static function randomColor(): string
    {
        $pal = ['#2563eb', '#dc2626', '#16a34a', '#d97706', '#7c3aed', '#db2777', '#0891b2', '#65a30d', '#ea580c', '#4f46e5',
            '#0d9488', '#be123c', '#a16207', '#9333ea', '#0284c7', '#15803d', '#b91c1c', '#c2410c', '#6d28d9', '#0f766e'];

        return $pal[Region::count() % count($pal)];
    }

    /** Pratinjau: hasil transformasi semua baris tanpa menyimpan. */
    public function preview(array $rows, array $mapping, DuplicateFinder $dup): array
    {
        $hasil = [];
        foreach ($rows as $i => $row) {
            $t = $this->transform($row, $mapping);
            if (empty($t['errors']) && ! empty($t['data']['name'])) {
                $kandidat = $dup->candidatesFor($t['data']['name'], $t['data']['birth_date'])
                    ->filter(fn ($m) => $m->code !== $t['data']['code']);
                if ($kandidat->isNotEmpty()) {
                    $t['warnings'][] = 'Kemungkinan duplikat: '.$kandidat->map(fn ($m) => "{$m->code} {$m->name}")->join(', ');
                }
            }
            $hasil[] = ['line' => $i + 1] + $t;
        }

        return $hasil;
    }

    /**
     * Jalankan import. Mengikuti urutan migrasi: regions -> families -> members -> member_events.
     *
     * @return array{created:int, updated:int, failed: array<int, array{line:int, name:?string, error:string}>}
     */
    public function import(array $rows, array $mapping, array $opsi = []): array
    {
        $buatWilayah = $opsi['create_regions'] ?? true;
        $sakramenBelumDiketahui = $opsi['placeholder_sacraments'] ?? true;
        $hasil = ['created' => 0, 'updated' => 0, 'failed' => []];

        config(['sij.geocode_on_save' => $opsi['geocode'] ?? false]);

        foreach ($rows as $i => $row) {
            $t = $this->transform($row, $mapping);
            $d = $t['data'];
            if ($t['errors']) {
                $hasil['failed'][] = ['line' => $i + 1, 'name' => $d['name'] ?? null, 'error' => implode('; ', $t['errors'])];

                continue;
            }

            try {
                DB::transaction(function () use ($d, $buatWilayah, $sakramenBelumDiketahui, &$hasil) {
                    $region = $this->resolveRegion($d['region_raw'], $buatWilayah);

                    $family = null;
                    if ($d['family_code']) {
                        $family = Family::firstOrNew(['code' => $d['family_code']]);
                    } elseif ($d['address']) {
                        $family = Family::where('address', trim(preg_replace('/\s+/u', ' ', $d['address'])))->first() ?? new Family;
                    }
                    if ($family) {
                        if (! $family->address && $d['address']) {
                            $family->address = $d['address'];
                        }
                        if (! $family->region_id && $region) {
                            $family->region_id = $region->id;
                        }
                        if (! $family->phone && $d['phone']) {
                            $family->phone = $d['phone'];
                        }
                        $family->save();
                    }

                    $member = $d['code'] ? Member::firstOrNew(['code' => $d['code']]) : new Member;
                    $baru = ! $member->exists;
                    $member->fill([
                        'old_registration_no' => $d['old_registration_no'] ?? $member->old_registration_no,
                        'name' => $d['name'],
                        'alias' => $d['alias'] ?? $member->alias,
                        'gender' => $d['gender'] ?? $member->gender,
                        'birth_place' => $d['birth_place'] ?? $member->birth_place,
                        'birth_date' => $d['birth_date'] ?? $member->birth_date,
                        'phone' => $d['phone'] ?? $member->phone,
                        'family_id' => $family?->id ?? $member->family_id,
                        'family_relation' => $d['family_relation'] ?? $member->family_relation,
                        'membership_status' => $d['membership_status'],
                        'activity_status' => $d['activity_status'],
                        'marriage_date' => $d['marriage_date'] ?? $member->marriage_date,
                        'death_date' => $d['death_date'] ?? $member->death_date,
                    ]);
                    $member->save();

                    if ($family && ! $family->head_member_id && ($d['family_relation'] === 'kepala' || $d['family_relation'] === 'suami' || $baru && $family->members()->count() === 1)) {
                        $family->forceFill(['head_member_id' => $member->id])->saveQuietly();
                    }

                    $events = [
                        'nikah' => [$d['marriage_date'], null],
                        'baptis' => [$d['baptis_date'], null],
                        'sidi' => [$d['sidi_date'], null],
                        'atestasi_masuk' => [$d['atestasi_masuk_date'], $d['atestasi_masuk_church']],
                        'atestasi_keluar' => [$d['atestasi_keluar_date'], $d['atestasi_keluar_church']],
                        'wafat' => [$d['death_date'], null],
                    ];
                    foreach ($events as $jenis => [$tgl, $gereja]) {
                        if ($tgl) {
                            MemberEvent::withoutEvents(fn () => MemberEvent::updateOrCreate(
                                ['member_id' => $member->id, 'type' => $jenis],
                                ['date' => $tgl, 'church' => $gereja, 'data_status' => 'lengkap']
                            ));
                        }
                    }
                    if ($d['membership_status'] === 'keluar' && ! $d['atestasi_keluar_date']) {
                        MemberEvent::withoutEvents(fn () => MemberEvent::firstOrCreate(
                            ['member_id' => $member->id, 'type' => 'atestasi_keluar'], ['data_status' => 'belum_diketahui']));
                    }
                    if ($d['membership_status'] === 'meninggal' && ! $d['death_date']) {
                        MemberEvent::withoutEvents(fn () => MemberEvent::firstOrCreate(
                            ['member_id' => $member->id, 'type' => 'wafat'], ['data_status' => 'belum_diketahui']));
                    }
                    // Baptis/sidi untuk anggota dibuat berstatus "belum diketahui" (diisi bertahap, DJ-17).
                    if ($sakramenBelumDiketahui && $d['membership_status'] === 'anggota') {
                        foreach (['baptis', 'sidi'] as $s) {
                            if (! $d[$s.'_date']) {
                                MemberEvent::withoutEvents(fn () => MemberEvent::firstOrCreate(
                                    ['member_id' => $member->id, 'type' => $s], ['data_status' => 'belum_diketahui']));
                            }
                        }
                    }

                    $hasil[$baru ? 'created' : 'updated']++;
                });
            } catch (\Throwable $e) {
                $hasil['failed'][] = ['line' => $i + 1, 'name' => $d['name'] ?? null, 'error' => Str::limit($e->getMessage(), 200)];
            }
        }

        return $hasil;
    }
}
