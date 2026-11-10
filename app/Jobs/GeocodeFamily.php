<?php

namespace App\Jobs;

use App\Models\Family;
use App\Models\Region;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * DJ-14: ubah alamat KK menjadi koordinat. Nominatim dibatasi 1 permintaan/detik
 * (rate limiter "geocoding" di AppServiceProvider).
 */
class GeocodeFamily implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $familyId) {}

    public function middleware(): array
    {
        return [new RateLimited('geocoding')];
    }

    public function handle(): void
    {
        $family = Family::find($this->familyId);
        if (! $family || ! $family->address || $family->geocode_status === 'manual') {
            return;
        }

        $alamat = $family->address;
        if (preg_match('/belum ada alamat/i', $alamat)) {
            $this->save($family, null, null, 'gagal');

            return;
        }
        $query = trim($alamat.', '.($family->region?->name ? $family->region->name.', ' : '').config('sij.geocode_suffix'));

        try {
            [$lat, $lng, $status] = config('sij.geocoder') === 'google' && config('sij.google_geocoding_key')
                ? $this->google($query)
                : $this->nominatimBertahap($alamat, $family->region?->name);
        } catch (\Throwable $e) {
            Log::warning('Geocoding gagal untuk '.$family->code.': '.$e->getMessage());
            $this->release(30);

            return;
        }

        $this->save($family, $lat, $lng, $status);
    }

    /**
     * Nominatim kurang kenal format RT/RW & nomor rumah, jadi dicoba bertahap:
     * alamat dibersihkan -> nama jalan saja -> wilayah (hasilnya "perkiraan").
     */
    private function nominatimBertahap(string $alamat, ?string $wilayah): array
    {
        $wilayah = $wilayah ? trim(preg_replace('/^luar kota\s*/i', '', $wilayah)) : null;
        // Alamat di luar Kota Batu (Malang, Surabaya, ...) jangan ditambah "Kota Batu".
        $luarBatu = preg_match('/\b(malang|surabaya|sidoarjo|lawang|blimbing|purwodadi|singosari|karang ?ploso|pasuruan|kediri|jakarta)\b/i', $alamat.' '.$wilayah);
        $suffix = $luarBatu ? 'Jawa Timur, Indonesia' : config('sij.geocode_suffix');
        $ekor = ($wilayah ? $wilayah.', ' : '').$suffix;
        $bersih = self::bersihkanAlamat($alamat);
        $jalan = self::namaJalan($bersih);

        $coba = array_values(array_unique(array_filter([
            $bersih ? "{$bersih}, {$ekor}" : null,
            $jalan && $jalan !== $bersih ? "{$jalan}, {$ekor}" : null,
            $wilayah ? "{$wilayah}, {$suffix}" : null,
        ])));

        foreach ($coba as $i => $q) {
            if ($i > 0) {
                usleep(1_100_000); // kebijakan Nominatim: maks. 1 permintaan per detik
            }
            [$lat, $lng, $status] = $this->nominatim($q);
            if ($lat !== null) {
                // Hanya pencarian alamat/jalan yang bisa "tepat"; pencarian wilayah selalu perkiraan.
                $pakaiWilayahSaja = $wilayah && $q === "{$wilayah}, {$suffix}";

                return [$lat, $lng, $pakaiWilayahSaja ? 'perkiraan' : $status];
            }
        }

        return [null, null, 'gagal'];
    }

    /** "Jln. Sajid No. 16 RT 001 RW 010 Pesanggrahan Batu" -> "Jalan Sajid 16, Pesanggrahan Batu". */
    public static function bersihkanAlamat(string $alamat): ?string
    {
        $s = preg_replace('/\s+/u', ' ', trim($alamat));
        if ($s === '' || preg_match('/^(satu rumah|belum ada|-)/i', $s)) {
            return null;
        }
        $s = preg_replace('/\b(RT|RW)\.?\s*:?\s*\d+\s*\/?\s*/i', ' ', $s);
        $s = preg_replace('/\bJ(a)?l(n)?\b\.?\s*,?\s*/i', 'Jalan ', $s);
        $s = preg_replace('/\bGg\b\.?\s*/i', 'Gang ', $s);
        $s = preg_replace('/\bPerum\b\.?\s*/i', 'Perumahan ', $s);
        $s = preg_replace('/\b(No|Nomor|Nmr|N0|Kav|Blok)\b\.?\s*/i', '', $s);
        $s = preg_replace('/\b(Kota\s+)?Batu\b\.?$/i', '', trim($s));
        $s = trim(preg_replace('/\s*,\s*/', ', ', preg_replace('/\s+/', ' ', $s)), " ,.");

        return $s !== '' ? $s : null;
    }

    /** Ambil nama jalan saja (tanpa nomor rumah & sisanya). */
    public static function namaJalan(?string $bersih): ?string
    {
        if (! $bersih || ! preg_match('/^((?:Jalan|Gang|Perumahan)\s+[^\d,\/(]+)/iu', $bersih, $m)) {
            return null;
        }
        $j = preg_split('/\s+(?=Gang\b|Perumahan\b|Kel\.|Kec\.)/i', trim($m[1]))[0];
        // Buang sisa kode blok/rumah di ujung (mis. "CC", "II B", "B").
        $j = preg_replace('/(\s+([A-Z]{1,2}|[IVX]+\s+[A-Z]))+$/u', '', $j);

        return trim($j, " .-") ?: null;
    }

    private function nominatim(string $query): array
    {
        $res = Http::withHeaders(['User-Agent' => 'SistemInformasiJemaat/1.0'])
            ->timeout(20)
            ->get(config('sij.nominatim_url'), array_filter([
                'q' => $query,
                'format' => 'jsonv2',
                'limit' => 1,
                'countrycodes' => 'id',
                'email' => config('sij.nominatim_email'),
            ]))->throw()->json();

        if (empty($res[0])) {
            return [null, null, 'gagal'];
        }
        $hit = $res[0];
        // place_rank >= 26 = tingkat jalan/rumah; di bawahnya hanya kelurahan/kota.
        $status = ((int) ($hit['place_rank'] ?? 0)) >= 26 ? 'tepat' : 'perkiraan';

        return [(float) $hit['lat'], (float) $hit['lon'], $status];
    }

    private function google(string $query): array
    {
        $res = Http::timeout(20)->get('https://maps.googleapis.com/maps/api/geocode/json', [
            'address' => $query,
            'key' => config('sij.google_geocoding_key'),
            'region' => 'id',
        ])->throw()->json();

        $hit = $res['results'][0] ?? null;
        if (! $hit) {
            return [null, null, 'gagal'];
        }
        $type = $hit['geometry']['location_type'] ?? '';
        $status = in_array($type, ['ROOFTOP', 'RANGE_INTERPOLATED'], true) ? 'tepat' : 'perkiraan';

        return [$hit['geometry']['location']['lat'], $hit['geometry']['location']['lng'], $status];
    }

    private function save(Family $family, ?float $lat, ?float $lng, string $status): void
    {
        $family->forceFill([
            'latitude' => $lat,
            'longitude' => $lng,
            'geocode_status' => $status,
            'geocoded_at' => now(),
            'suggested_region_id' => Region::suggestFor($lat, $lng)?->id,
        ])->saveQuietly();
    }
}
