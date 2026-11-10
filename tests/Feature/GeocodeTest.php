<?php

namespace Tests\Feature;

use App\Jobs\GeocodeFamily;
use App\Models\Family;
use App\Models\Region;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeocodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_address_cleaning(): void
    {
        $this->assertSame('Jalan Sajid 16, Pesanggrahan', GeocodeFamily::bersihkanAlamat('Jln. Sajid No. 16 RT 001 RW 010, Pesanggrahan Batu'));
        $this->assertNull(GeocodeFamily::bersihkanAlamat('Satu Rumah (Dewi / Jordan)'));
        $this->assertSame('Jalan Mangga', GeocodeFamily::namaJalan(GeocodeFamily::bersihkanAlamat('Jl. Mangga CC 341, Panderman Hill')));
        $this->assertSame('Jalan darsono', GeocodeFamily::namaJalan(GeocodeFamily::bersihkanAlamat('jln darsono gg parkit n0 34 ngaglik batu')));
    }

    public function test_falls_back_to_street_then_region(): void
    {
        $this->seed(DatabaseSeeder::class);
        config(['sij.geocoder' => 'nominatim', 'sij.geocode_on_save' => false]);
        $r = Region::where('name', 'Pesanggrahan')->first();
        $f = Family::create(['address' => 'Jln. Sajid No. 16 RT 001 RW 010', 'region_id' => $r->id]);

        // Run 1: alamat lengkap tidak ketemu, nama jalan ketemu (tingkat jalan = tepat).
        // Run 2: alamat & jalan tidak ketemu, wilayah ketemu (perkiraan).
        Http::fakeSequence()
            ->push([])->push([['lat' => '-7.87', 'lon' => '112.52', 'place_rank' => 26]])
            ->push([])->push([])->push([['lat' => '-7.88', 'lon' => '112.53', 'place_rank' => 30]]);
        (new GeocodeFamily($f->id))->handle();
        $f->refresh();
        $this->assertSame('tepat', $f->geocode_status);
        $this->assertEqualsWithDelta(-7.87, (float) $f->latitude, 0.0001);
        $this->assertStringContainsString('google.com/maps', $f->google_maps_url);
        $this->assertStringContainsString('-7.87', $f->google_maps_url);

        // Hanya wilayah yang ketemu -> perkiraan.
        $f->forceFill(['geocode_status' => 'belum', 'latitude' => null, 'longitude' => null])->saveQuietly();
        (new GeocodeFamily($f->id))->handle();
        $this->assertSame('perkiraan', $f->refresh()->geocode_status);
    }

    public function test_google_maps_url_by_address_without_pin(): void
    {
        $f = new Family(['address' => 'Jl. Kartini 17']);
        $this->assertStringContainsString(rawurlencode('Jl. Kartini 17'), $f->google_maps_url);
        $this->assertNull((new Family(['address' => 'Belum Ada Alamat']))->google_maps_url);
    }
}
