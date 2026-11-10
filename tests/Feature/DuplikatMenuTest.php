<?php

namespace Tests\Feature;

use App\Filament\Pages\DuplikatJemaat;
use App\Models\Member;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DuplikatMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_duplikat_hanya_muncul_bila_ada_duplikat(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::where('username', 'admin')->firstOrFail());
        Member::create(['name' => 'BUDI SANTOSO', 'birth_date' => '1980-05-01', 'membership_status' => 'sidi']);

        $this->assertFalse(DuplikatJemaat::shouldRegisterNavigation());

        Member::create(['name' => 'Budi Santoso', 'birth_date' => '1980-05-02', 'membership_status' => 'simpatisan']);
        $this->assertTrue(DuplikatJemaat::shouldRegisterNavigation());
        $this->assertSame('1', DuplikatJemaat::getNavigationBadge());
        $this->get('/admin/members')->assertOk()->assertSee('Kemungkinan Duplikat');
        $this->get('/admin/import-jemaat')->assertNotFound();
    }
}
