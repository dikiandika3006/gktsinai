<?php

namespace Tests\Feature;

use App\Filament\Resources\Families\Pages\ListFamilies;
use App\Filament\Resources\Members\Pages\ListMembers;
use App\Filament\Resources\Members\Pages\ViewMember;
use App\Models\Family;
use App\Models\Member;
use App\Models\Region;
use App\Models\User;
use App\Services\KeluargaService;
use Database\Seeders\DatabaseSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class KeluargaTest extends TestCase
{
    use RefreshDatabase;

    private Family $kk;

    private Member $ayah;

    private Member $ibu;

    private Member $anak;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::where('username', 'admin')->first());
        config(['sij.geocode_on_save' => false]);

        $this->kk = Family::create(['address' => 'Jl. Uji 1', 'region_id' => Region::value('id')]);
        $this->ayah = Member::create(['name' => 'AYAH UJI', 'gender' => 'L', 'family_id' => $this->kk->id, 'family_relation' => 'kepala', 'birth_date' => '1970-01-01']);
        $this->ibu = Member::create(['name' => 'IBU UJI', 'gender' => 'P', 'family_id' => $this->kk->id, 'family_relation' => 'istri', 'birth_date' => '1972-01-01']);
        $this->anak = Member::create(['name' => 'ANAK UJI', 'gender' => 'L', 'family_id' => $this->kk->id, 'family_relation' => 'anak', 'birth_date' => '1995-01-01']);
        $this->kk->update(['head_member_id' => $this->ayah->id]);
    }

    public function test_ganti_kepala_menyesuaikan_hubungan(): void
    {
        app(KeluargaService::class)->jadikanKepala($this->ibu->refresh(), 'suami');

        $this->assertSame($this->ibu->id, $this->kk->refresh()->head_member_id);
        $this->assertSame('kepala', $this->ibu->refresh()->family_relation);
        $this->assertSame('suami', $this->ayah->refresh()->family_relation);
    }

    public function test_ganti_kepala_lewat_form_edit_kk_tidak_menyisakan_dua_kepala(): void
    {
        $this->kk->update(['head_member_id' => $this->ibu->id]);

        $this->assertSame('kepala', $this->ibu->refresh()->family_relation);
        $this->assertSame('lainnya', $this->ayah->refresh()->family_relation);
    }

    public function test_anak_pindah_ke_kk_baru_dengan_salin_alamat(): void
    {
        $hasil = app(KeluargaService::class)->pindah(collect([$this->anak]), null, ['data_kk' => ['address' => 'Jl. Baru 2', 'region_id' => Region::value('id')]]);

        $baru = $hasil['family'];
        $this->assertNotSame($this->kk->id, $baru->id);
        $this->assertSame($this->anak->id, $baru->head_member_id);
        $this->assertSame('Jl. Baru 2', $baru->address);
        $this->assertSame('kepala', $this->anak->refresh()->family_relation);
        $this->assertSame($this->ayah->id, $this->kk->refresh()->head_member_id);
    }

    public function test_kepala_pindah_menunjuk_pengganti_dan_kk_kosong_dihapus(): void
    {
        $tujuan = Family::create(['address' => 'Jl. Lain']);
        $s = app(KeluargaService::class);

        $s->pindah(collect([$this->ayah->refresh()]), $tujuan, ['hubungan' => 'orang_tua', 'kepala_pengganti' => [$this->kk->id => $this->ibu->id]]);
        $this->assertSame($this->ibu->id, $this->kk->refresh()->head_member_id);
        $this->assertSame('kepala', $this->ibu->refresh()->family_relation);
        $this->assertSame('orang_tua', $this->ayah->refresh()->family_relation);

        $hasil = $s->pindah(collect([$this->ibu->refresh(), $this->anak->refresh()]), $tujuan, []);
        $this->assertDatabaseMissing('families', ['id' => $this->kk->id]);
        $this->assertStringContainsString('dihapus', implode(' ', $hasil['catatan']));
        // Hanya satu kepala di KK tujuan: ibu (dulu kepala) menjadi "lainnya".
        $this->assertSame('lainnya', $this->ibu->refresh()->family_relation);
    }

    public function test_aksi_di_halaman_admin(): void
    {
        Livewire::test(ListMembers::class)
            ->mountAction(TestAction::make('keluarga')->table($this->anak))
            ->assertSee('IBU UJI')
            ->assertSee('AYAH UJI');

        Livewire::test(ListMembers::class)
            ->callAction(TestAction::make('pindah_kk')->table($this->anak), ['mode' => 'baru', 'address' => 'Jl. Anak'])
            ->assertHasNoActionErrors();
        $this->assertSame('Jl. Anak', $this->anak->refresh()->family->address);

        Livewire::test(ListMembers::class)
            ->selectTableRecords([$this->ayah->id, $this->ibu->id])
            ->callAction(TestAction::make('pindah_kk')->table()->bulk(), ['mode' => 'ada', 'family_id' => $this->anak->family_id, 'kepala_id' => null])
            ->assertHasNoActionErrors();
        $this->assertSame($this->anak->family_id, $this->ibu->refresh()->family_id);

        Livewire::test(ViewMember::class, ['record' => $this->ibu->id])
            ->assertOk()
            ->assertSee('Anggota keluarga')
            ->callAction('jadikan_kepala', ['hubungan_lama' => 'anak'])
            ->assertHasNoActionErrors();
        $this->assertSame($this->ibu->id, $this->ibu->refresh()->family->head_member_id);

        Livewire::test(ListFamilies::class)
            ->filterTable('rapikan', 'kepala_ganda')
            ->assertOk();
    }
}
