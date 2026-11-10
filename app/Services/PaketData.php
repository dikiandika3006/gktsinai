<?php

namespace App\Services;

use App\Models\Family;
use App\Models\Member;
use App\Models\Region;
use App\Models\ServantAlias;
use App\Models\ServiceAssignment;
use App\Models\WorshipSchedule;
use Illuminate\Support\Facades\DB;

/**
 * Kirim sebagian data antar server (mis. lokal -> hosting) tanpa menimpa data lain:
 * pin peta KK + batas wilayah, dan jadwal pelayanan. Dicocokkan lewat kode KK, kode jemaat,
 * nama wilayah, dan tanggal jadwal (bukan ID), jadi perubahan lain di server tujuan tetap aman.
 */
class PaketData
{
    public const VERSI = 1;

    public function ekspor(array $bagian): array
    {
        $paket = ['sij_paket' => self::VERSI, 'dibuat' => now()->toIso8601String(), 'sumber' => config('app.url')];

        if (in_array('peta', $bagian, true)) {
            $paket['wilayah'] = Region::query()->get(['name', 'map_color', 'area_geojson'])
                ->map(fn (Region $r) => ['nama' => $r->name, 'warna' => $r->map_color, 'area' => $r->area_geojson])->all();
            // Kode jemaat kepala keluarga ikut dikirim: lebih stabil daripada kode KK otomatis (KK-001, ...).
            $paket['pin_kk'] = Family::query()->whereNotNull('latitude')->whereNotNull('longitude')->with('head:id,code,name')
                ->get(['id', 'code', 'head_member_id', 'latitude', 'longitude', 'geocode_status', 'geocoded_at'])
                ->map(fn (Family $f) => ['kode' => $f->code, 'kepala' => $f->head?->code, 'nama' => $f->head?->name,
                    'lat' => $f->latitude, 'lng' => $f->longitude,
                    'status' => $f->geocode_status, 'waktu' => $f->geocoded_at?->toIso8601String()])->all();
        }

        if (in_array('pelayanan', $bagian, true)) {
            $kode = Member::query()->pluck('code', 'id');
            $paket['nama_pelayan'] = ServantAlias::query()->get()
                ->map(fn (ServantAlias $a) => ['kunci' => $a->key, 'nama' => $a->display_name, 'manual' => $a->is_manual,
                    'kategori' => $a->category, 'jemaat' => $a->is_manual && $a->member_id ? $kode[$a->member_id] ?? null : null])->all();
            $paket['jadwal'] = WorshipSchedule::with('assignments')->orderBy('date')->get()
                ->map(fn (WorshipSchedule $s) => [
                    'tanggal' => $s->date->toDateString(),
                    'info' => $s->only(['tema', 'ayat_referensi', 'introitus', 'phb', 'keterangan']),
                    'petugas' => $s->assignments->map(fn (ServiceAssignment $a) => $a->only(['kebaktian', 'peran', 'urutan', 'nama', 'kunci', 'is_keluarga']))->all(),
                ])->all();
        }

        return $paket;
    }

    /**
     * Tiap bagian diimpor sendiri-sendiri: bila jadwal pelayanan gagal (mis. tabelnya belum
     * dimigrasi di server tujuan), pin peta dan batas wilayah tetap masuk.
     *
     * @return array<string, int|string> ringkasan
     */
    public function impor(array $paket): array
    {
        if (($paket['sij_paket'] ?? null) !== self::VERSI) {
            throw new \InvalidArgumentException('File bukan paket data Sistem Informasi Jemaat.');
        }
        $bagian = array_filter([
            'Batas wilayah' => isset($paket['wilayah']) ? fn () => $this->imporWilayah($paket['wilayah']) : null,
            'Pin KK' => isset($paket['pin_kk']) ? fn () => $this->imporPin($paket['pin_kk']) : null,
            'Jadwal pelayanan' => isset($paket['jadwal']) ? fn () => $this->imporPelayanan($paket['nama_pelayan'] ?? [], $paket['jadwal']) : null,
        ]);

        $hasil = [];
        foreach ($bagian as $judul => $jalankan) {
            try {
                $hasil += DB::transaction($jalankan);
            } catch (\Throwable $e) {
                $hasil["{$judul} GAGAL"] = str($e->getMessage())->limit(300)->value()
                    .(str_contains($e->getMessage(), "doesn't exist") ? ' (jalankan "Update database" dulu)' : '');
            }
        }

        return $hasil;
    }

    private function imporWilayah(array $wilayah): array
    {
        $n = 0;
        foreach ($wilayah as $w) {
            $r = Region::where('name', $w['nama'])->first();
            if ($r && $w['area']) {
                $r->update(['area_geojson' => $w['area'], 'map_color' => $w['warna'] ?: $r->map_color]);
                $n++;
            }
        }

        return ['Batas wilayah diperbarui' => $n];
    }

    private function imporPin(array $pin): array
    {
        $n = $lewat = 0;
        $tidakAda = [];
        $kk = Family::query()->with('head:id,code')->whereIn('code', array_column($pin, 'kode'))->get()->keyBy('code');
        $perKepala = Family::query()->with('head:id,code')
            ->whereHas('head', fn ($q) => $q->whereIn('code', array_filter(array_column($pin, 'kepala'))))
            ->get()->keyBy(fn (Family $f) => $f->head->code);

        foreach ($pin as $p) {
            // Utamakan kode jemaat kepala keluarga; kode KK dipakai bila kepalanya sama/tidak diketahui.
            $kepala = $p['kepala'] ?? null;
            $f = $kepala ? ($perKepala[$kepala] ?? null) : null;
            if (! $f && ($g = $kk[$p['kode']] ?? null) && (! $kepala || ! $g->head || $g->head->code === $kepala)) {
                $f = $g;
            }
            if (! $f) {
                $tidakAda[] = $p['kode'].(! empty($p['nama']) ? " ({$p['nama']})" : '');

                continue;
            }
            $sama = $f->latitude !== null && abs($f->latitude - $p['lat']) < 1e-7 && abs($f->longitude - $p['lng']) < 1e-7;
            // Pin yang sudah digeser manual di server ini tidak ditimpa.
            if ($sama || ($f->geocode_status === 'manual' && $f->latitude !== null)) {
                $lewat++;

                continue;
            }
            $f->latitude = $p['lat'];
            $f->longitude = $p['lng'];
            $f->geocode_status = $p['status'] ?: 'tepat';
            $f->geocoded_at = $p['waktu'] ?? now();
            $f->saveQuietly(); // tanpa memicu geocoding ulang
            $f->forceFill(['suggested_region_id' => Region::suggestFor($f->latitude, $f->longitude)?->id])->saveQuietly();
            $n++;
        }

        return array_filter([
            'Pin KK diperbarui' => $n,
            'Pin KK dilewati (sama / pin manual di sini)' => $lewat,
            'KK tidak ditemukan di server ini' => count($tidakAda),
            'Daftar KK tidak ditemukan' => $tidakAda ? implode(', ', array_slice($tidakAda, 0, 50)).(count($tidakAda) > 50 ? ', ...' : '') : null,
        ], fn ($v) => $v !== null);
    }

    private function imporPelayanan(array $nama, array $jadwal): array
    {
        $member = Member::query()->pluck('id', 'code');
        $tidakCocok = 0;
        foreach ($nama as $a) {
            $alias = ServantAlias::firstOrNew(['key' => $a['kunci']]);
            $alias->display_name = $alias->display_name ?: $a['nama'];
            $alias->category = $a['kategori'] ?? $alias->category;
            if ($a['manual']) {
                $id = $a['jemaat'] ? ($member[$a['jemaat']] ?? null) : null;
                if ($a['jemaat'] && ! $id) {
                    $tidakCocok++;
                } else {
                    $alias->member_id = $id;
                    $alias->is_manual = true;
                }
            }
            $alias->save();
        }

        foreach ($jadwal as $j) {
            $s = WorshipSchedule::updateOrCreate(['date' => $j['tanggal']], $j['info']);
            $s->assignments()->delete();
            foreach ($j['petugas'] as $p) {
                $s->assignments()->create($p);
            }
        }

        // Nama tanpa hubungan manual dicocokkan dengan data jemaat di server ini.
        $cocok = app(PelayanMatcher::class)->cocokkanUlang();
        ServantAlias::query()->each(fn (ServantAlias $a) => ServiceAssignment::where('kunci', $a->key)->update(['member_id' => $a->member_id]));

        return ['Jadwal ibadah' => count($jadwal), 'Nama cocok otomatis' => $cocok, 'Hubungan manual: kode jemaat tidak ada di sini' => $tidakCocok];
    }
}
