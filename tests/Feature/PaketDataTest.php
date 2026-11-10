<?php

namespace Tests\Feature;

use App\Models\Family;
use App\Models\Member;
use App\Models\ServantAlias;
use App\Models\User;
use App\Models\WorshipSchedule;
use App\Services\PaketData;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $this->expectException(\InvalidArgumentException::class);
        app(PaketData::class)->impor(['bukan' => 'paket']);
    }

    public function test_tombol_pemeliharaan(): void
    {
        $this->seed([DatabaseSeeder::class]);
        $this->actingAs(User::where('username', 'admin')->firstOrFail());
        \Livewire\Livewire::test(\App\Filament\Pages\Pemeliharaan::class)
            ->callAction('paketEkspor', ['bagian' => ['peta', 'pelayanan']])
            ->assertFileDownloaded();
    }
}
