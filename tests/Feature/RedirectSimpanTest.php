<?php

namespace Tests\Feature;

use App\Filament\Resources\Members\MemberResource;
use App\Filament\Resources\Members\Pages\CreateMember;
use App\Filament\Resources\Members\Pages\EditMember;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\UserResource;
use App\Models\Member;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Setelah simpan form tambah/ubah, pengguna kembali ke halaman daftar. */
class RedirectSimpanTest extends TestCase
{
    use RefreshDatabase;

    public function test_simpan_kembali_ke_daftar(): void
    {
        $this->seed(DatabaseSeeder::class);
        config(['sij.geocode_on_save' => false]);
        $admin = User::where('username', 'admin')->firstOrFail();
        $this->actingAs($admin);

        Livewire::test(CreateMember::class)
            ->fillForm(['name' => 'JEMAAT UJI REDIRECT', 'membership_status' => 'simpatisan'])
            ->call('create')->assertHasNoFormErrors()
            ->assertRedirect(MemberResource::getUrl('index'));

        Livewire::test(EditMember::class, ['record' => Member::where('name', 'JEMAAT UJI REDIRECT')->first()->getRouteKey()])
            ->fillForm(['alias' => 'Uji'])
            ->call('save')->assertHasNoFormErrors()
            ->assertRedirect(MemberResource::getUrl('index'));

        Livewire::test(EditUser::class, ['record' => $admin->getRouteKey()])
            ->call('save')->assertHasNoFormErrors()
            ->assertRedirect(UserResource::getUrl('index'));
    }
}
