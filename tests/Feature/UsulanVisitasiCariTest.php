<?php

namespace Tests\Feature;

use App\Filament\Pages\UsulanVisitasi;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UsulanVisitasiCariTest extends TestCase
{
    use RefreshDatabase;

    public function test_cari_usulan_visitasi(): void
    {
        $this->seed([DatabaseSeeder::class, DemoSeeder::class]);
        $this->actingAs(User::where('username', 'admin')->firstOrFail());

        $page = Livewire::test(UsulanVisitasi::class)->assertOk();
        $usulan = app(\App\Services\VisitSuggestionService::class)->suggestions();
        $this->assertNotEmpty($usulan);
        $kk = $usulan->first()['family'];

        $page->set('cari', $kk->code)->assertSee($kk->display_name)
            ->set('cari', 'nama-yang-tidak-ada-xyz')->assertSee('Tidak ada usulan yang cocok')
            ->set('cari', '')->set('jenis', $usulan->first()['type'])->assertSee($kk->display_name);
    }
}
