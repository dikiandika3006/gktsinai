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
                : $this->nominatim($query);
        } catch (\Throwable $e) {
            Log::warning('Geocoding gagal untuk '.$family->code.': '.$e->getMessage());
            $this->release(30);

            return;
        }

        $this->save($family, $lat, $lng, $status);
    }

    private function nominatim(string $query): array
    {
        $res = Http::withHeaders(['User-Agent' => 'SIJ-Gereja/1.0'])
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
