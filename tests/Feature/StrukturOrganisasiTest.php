<?php

namespace Tests\Feature;

use App\Filament\Pages\StrukturOrganisasi;
use App\Models\Member;
use App\Models\OrgPeriod;
use App\Models\OrgPosition;
use App\Models\OrgUnit;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StrukturOrganisasiTest extends TestCase
{
    use RefreshDatabase;

    public function test_bagan_kelola_dan_profil_jemaat(): void
    {
        $this->seed([DatabaseSeeder::class, DemoSeeder::class]);
        // Struktur awal 2025–2027 diisi oleh migrasi.
        $periode = OrgPeriod::where('name', '2025–2027')->firstOrFail();
        $this->assertTrue($periode->is_active);
        $this->assertSame(9, OrgUnit::where('name', 'Rapat Majelis Jemaat')->first()->positions()->where('role', 'penatua')->count());

        $m = Member::first();
        $pos = OrgPosition::where('name', 'Pnt. Hizkia Anugrah Dias')->where('role', 'penatua_pj')->first();
        $pos->update(['member_id' => $m->id]);

        $this->actingAs(User::where('email', 'sekretariat@gktsinai.test')->firstOrFail());
        $unit = OrgUnit::where('name', 'Ibadah')->first();
        foreach (['/admin/struktur-organisasi', '/admin/unit-organisasi', '/admin/unit-organisasi/tambah', "/admin/unit-organisasi/{$unit->id}/ubah"] as $u) {
            $res = $this->get($u);
            $this->assertSame(200, $res->status(), "GET {$u} -> {$res->status()} ".($res->exception?->getMessage() ?? ''));
        }
        $this->get('/admin/struktur-organisasi')->assertSee('Struktur Pertanggungjawaban Pelayanan')
            ->assertSee('Komisi Usia Indah (Lansia)')->assertSee('Bidang Ibadah');
        $this->get("/admin/jemaat/{$m->id}")->assertOk();

        // Periode baru menyalin struktur dan pengurus.
        Livewire::test(StrukturOrganisasi::class)
            ->callAction('periodeBaru', ['name' => '2028–2030', 'start_date' => '2028-01-01', 'end_date' => '2030-12-31', 'salin' => true, 'is_active' => true])
            ->assertHasNoActionErrors();
        $baru = OrgPeriod::where('name', '2028–2030')->firstOrFail();
        $this->assertTrue($baru->is_active);
        $this->assertFalse($periode->refresh()->is_active);
        $this->assertSame(OrgUnit::where('org_period_id', $periode->id)->count(), OrgUnit::where('org_period_id', $baru->id)->count());
        $this->assertSame(5, OrgUnit::where('org_period_id', $baru->id)->where('name', 'Ibadah')->first()->children()->count());
        $this->assertSame(2, $m->orgPositions()->count());

        // Majelis hanya melihat, tidak bisa mengelola.
        $this->flushSession();
        $this->actingAs(User::where('email', 'majelis@gktsinai.test')->firstOrFail());
        $this->get('/admin/struktur-organisasi')->assertOk();
        $this->get('/admin/unit-organisasi/tambah')->assertForbidden();
    }
}
