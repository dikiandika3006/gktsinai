<?php

namespace Tests\Feature;

use App\Filament\Pages\Pemeliharaan;
use App\Models\Family;
use App\Models\Member;
use App\Models\ServantAlias;
use App\Models\User;
use App\Models\WorshipSchedule;
use App\Services\PaketData;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class PaketDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_kirim_peta_dan_pelayanan_tanpa_menimpa_data_lain(): void
    {
        $this->seed([DatabaseSeeder::class, DemoSeeder::class]);
        [$f1, $f2] = Family::query()->orderBy('id')->take(2)->get()->all();
        $f1->forceFill(['latitude' => -7.87, 'longitude' => 112.52, 'geocode_status' => 'manual'])->saveQuietly();
        $f2->forceFill(['latitude' => -7.88, 'longitude' => 112.53, 'geocode_status' => 'tepat'])->saveQuietly();
        $m = Member::query()->orderBy('id')->first();
        $s = WorshipSchedule::create(['date' => '2026-11-01', 'tema' => 'Kasihilah TUHAN']);
        $s->simpanPetugas([['peran' => 'liturgis', 'kebaktian' => 'KU I', 'nama' => 'Sdr. Nama Unik Sekali']]);
        ServantAlias::where('key', 'nama unik sekali')->first()->update(['member_id' => $m->id, 'is_manual' => true]);

        $paket = json_decode(json_encode(app(PaketData::class)->ekspor(['peta', 'pelayanan'])), true);

        // Server tujuan: pin belum ada, kepala keluarga sudah diubah, jadwal belum ada.
        Family::query()->update(['latitude' => null, 'longitude' => null, 'geocode_status' => 'belum']);
        $f1->refresh()->forceFill(['notes' => 'diubah di hosting'])->saveQuietly();
        $f2->refresh()->forceFill(['latitude' => -7.9, 'longitude' => 112.6, 'geocode_status' => 'manual'])->saveQuietly();
        WorshipSchedule::query()->delete();
        ServantAlias::query()->delete();

        $hasil = app(PaketData::class)->impor($paket);

        $this->assertSame(-7.87, $f1->refresh()->latitude);
        $this->assertSame('diubah di hosting', $f1->notes);
        $this->assertSame(-7.9, $f2->refresh()->latitude, 'pin manual di server tujuan tidak ditimpa');
        $this->assertSame(1, $hasil['Jadwal ibadah']);
        $this->assertSame($m->id, WorshipSchedule::first()->assignments()->first()->member_id);

        // Kode KK berbeda di server tujuan: tetap cocok lewat kode jemaat kepala keluarga.
        $f1->forceFill(['latitude' => null, 'longitude' => null, 'geocode_status' => 'belum', 'code' => 'KK-LAIN-1'])->saveQuietly();
        $hasil = app(PaketData::class)->impor(['sij_paket' => 1, 'pin_kk' => $paket['pin_kk']]);
        $this->assertSame(-7.87, $f1->refresh()->latitude);

        // Bagian pelayanan gagal tidak membatalkan pin.
        $f1->forceFill(['latitude' => null, 'longitude' => null, 'geocode_status' => 'belum'])->saveQuietly();
        $hasil = app(PaketData::class)->impor(['sij_paket' => 1, 'pin_kk' => $paket['pin_kk'], 'jadwal' => [['tanggal' => 'bukan-tanggal']]]);
        $this->assertArrayHasKey('Jadwal pelayanan GAGAL', $hasil);
        $this->assertSame(-7.87, $f1->refresh()->latitude);

        $this->expectException(\InvalidArgumentException::class);
        app(PaketData::class)->impor(['bukan' => 'paket']);
    }

    public function test_tombol_proses_pin_berjalan_sampai_selesai(): void
    {
        $this->seed([DatabaseSeeder::class]);
        config(['sij.geocoder' => 'nominatim', 'sij.geocode_on_save' => false]);
        $this->actingAs(User::where('username', 'admin')->firstOrFail());
        foreach (range(1, 4) as $i) {
            Family::create(['address' => "Jl. Uji {$i}"]);
        }
        Family::create(['address' => 'Jl. Manual'])->forceFill(['latitude' => -7.9, 'longitude' => 112.6, 'geocode_status' => 'manual'])->saveQuietly();
        Http::fake(['*' => Http::response([['lat' => '-7.87', 'lon' => '112.52', 'place_rank' => 30]])]);

        $page = Livewire::test(Pemeliharaan::class)
            ->callAction('geocode', ['cakupan' => 'belum'])
            ->assertSet('pinTotal', 4)
            ->assertSet('pinJalan', true)
            ->assertSee('Pin alamat (OpenStreetMap)')
            ->call('prosesPin')
            ->assertSet('pinJalan', true)
            ->call('prosesPin')
            ->assertSet('pinJalan', false)
            ->assertSet('pinAntrean', []);

        $this->assertSame(4, $page->get('pinHasil')['tepat']);
        $this->assertSame(0, Family::where('geocode_status', 'belum')->count());
        $this->assertSame('manual', Family::where('address', 'Jl. Manual')->value('geocode_status'));
    }

    public function test_tombol_pemeliharaan(): void
    {
        $this->seed([DatabaseSeeder::class]);
        $this->actingAs(User::where('username', 'admin')->firstOrFail());
        Livewire::test(Pemeliharaan::class)
            ->callAction('paketEkspor', ['bagian' => ['peta', 'pelayanan']])
            ->assertFileDownloaded();
    }
}
