<?php

namespace Tests\Feature;

use App\Filament\Resources\Families\Pages\ViewFamily;
use App\Filament\Resources\Families\RelationManagers\MembersRelationManager;
use App\Models\Family;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Regresi: "Attempt to read property alias on null" di daftar anggota KK (hosting). */
class FamilyMembersRelationTest extends TestCase
{
    use RefreshDatabase;

    public function test_family_members_table_and_edit_action(): void
    {
        $this->seed([DatabaseSeeder::class, DemoSeeder::class]);
        $this->actingAs(User::where('email', 'admin@gktsinai.test')->first());

        $family = Family::has('members', '>=', 2)->first();
        $member = $family->members()->first();
        $member->update(['alias' => 'Panggilan Uji']);

        Livewire::test(MembersRelationManager::class, ['ownerRecord' => $family, 'pageClass' => ViewFamily::class])
            ->assertOk()
            ->assertCanSeeTableRecords($family->members)
            ->assertSee('Panggilan Uji')
            ->mountAction(TestAction::make('edit')->table($member))
            ->assertHasNoActionErrors()
            ->callMountedAction()
            ->assertHasNoActionErrors();
    }
}
