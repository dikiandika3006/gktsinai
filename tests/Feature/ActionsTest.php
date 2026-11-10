<?php

namespace Tests\Feature;

use App\Filament\Pages\DaftarPerhatian;
use App\Filament\Pages\PetaJemaat;
use App\Filament\Resources\Families\Pages\EditFamily;
use App\Filament\Resources\Guests\Pages\ListGuests;
use App\Filament\Resources\Members\Pages\CreateMember;
use App\Filament\Resources\Visits\Pages\ListVisits;
use App\Models\Family;
use App\Models\Guest;
use App\Models\Member;
use App\Models\User;
use App\Models\Visit;
use App\Services\AttendanceService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([DatabaseSeeder::class, DemoSeeder::class]);
    }

    private function as(string $email): void
    {
        $this->actingAs(User::where('email', $email)->firstOrFail());
    }

    public function test_create_member_and_edit_family(): void
    {
        $this->as('sekretariat@gktsinai.test');
        $f = Family::first();
        Livewire::test(CreateMember::class)
            ->fillForm(['name' => 'jemaat baru uji', 'gender' => 'P', 'birth_date' => '1999-09-09', 'family_id' => $f->id,
                'family_relation' => 'anak', 'membership_status' => 'simpatisan', 'activity_status' => 'aktif', 'phone' => '0812 0000 1111'])
            ->call('create')->assertHasNoFormErrors();
        $m = Member::where('name', 'jemaat baru uji')->first();
        $this->assertNotNull($m);
        $this->assertMatchesRegularExpression('/^SIB\d{4}$/', $m->code);

        Livewire::test(EditFamily::class, ['record' => $f->getRouteKey()])
            ->fillForm(['latitude' => -7.88, 'longitude' => 112.53])
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame('manual', $f->fresh()->geocode_status);
    }

    public function test_convert_guest_action(): void
    {
        $this->as('sekretariat@gktsinai.test');
        $g = Guest::first();
        Livewire::test(ListGuests::class)
            ->callTableAction('convert', $g, ['family_mode' => 'baru', 'new_family_address' => 'Jl. Uji 1', 'membership_status' => 'simpatisan'])
            ->assertHasNoTableActionErrors();
        $this->assertNotNull($g->fresh()->converted_member_id);
    }

    public function test_visit_report_by_team(): void
    {
        $this->as('visitasi@gktsinai.test');
        $v = Visit::where('status', 'rencana')->first();
        Livewire::test(ListVisits::class)
            ->callTableAction('laporan', $v, ['realized_date' => today()->toDateString(), 'conversation' => 'Uji', 'is_confidential' => true])
            ->assertHasNoTableActionErrors();
        $this->assertSame('selesai', $v->fresh()->status);
        $this->assertTrue($v->fresh()->report->is_confidential);
    }

    public function test_pages_livewire(): void
    {
        $this->as('sekretariat@gktsinai.test');
        Livewire::test(PetaJemaat::class)->set('keaktifan', 'aktif')->assertDispatched('peta-data');
        $ids = app(AttendanceService::class)->attentionList()->pluck('member.id')->take(2)->all();
        Livewire::test(DaftarPerhatian::class)->set('pasifIds', $ids)->call('setujuiPasif')->assertSuccessful();
        foreach ($ids as $id) {
            $this->assertSame('pasif', Member::find($id)->activity_status);
        }
    }
}
