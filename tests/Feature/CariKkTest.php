<?php

namespace Tests\Feature;

use App\Filament\Resources\Families\Pages\ListFamilies;
use App\Models\Family;
use App\Models\Member;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Daftar KK bisa dicari lewat nama / alias / kode anggota keluarga, bukan hanya kepala keluarga. */
class CariKkTest extends TestCase
{
    use RefreshDatabase;

    public function test_cari_kk_lewat_anggota(): void
    {
        $this->seed(DatabaseSeeder::class);
        config(['sij.geocode_on_save' => false]);
        $this->actingAs(User::where('username', 'admin')->firstOrFail());

        $kk = Family::create(['address' => 'Jl. Uji 3']);
        $kepala = Member::create(['name' => 'Hendra Kepala', 'family_id' => $kk->id, 'family_relation' => 'kepala']);
        $kk->forceFill(['head_member_id' => $kepala->id])->saveQuietly();
        $anak = Member::create(['name' => 'Hizkia Anugrah', 'alias' => 'Kiki', 'family_id' => $kk->id, 'family_relation' => 'anak']);
        $lain = Family::create(['address' => 'Jl. Lain 9']);
        Member::create(['name' => 'Orang Lain', 'family_id' => $lain->id]);

        Livewire::test(ListFamilies::class)
            ->searchTable('kiki')
            ->assertCanSeeTableRecords([$kk])->assertCanNotSeeTableRecords([$lain])
            ->assertSee('Anggota: Hizkia Anugrah');

        Livewire::test(ListFamilies::class)->searchTable('Hizkia')->assertCanSeeTableRecords([$kk]);
        Livewire::test(ListFamilies::class)->searchTable($anak->code)->assertCanSeeTableRecords([$kk])->assertCanNotSeeTableRecords([$lain]);
        // Nama kepala & alamat tetap bisa dicari.
        Livewire::test(ListFamilies::class)->searchTable('Hendra')->assertCanSeeTableRecords([$kk])->assertDontSee('Anggota: Hizkia');
        Livewire::test(ListFamilies::class)->searchTable('Lain 9')->assertCanSeeTableRecords([$lain]);
    }
}
