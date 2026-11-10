<?php

namespace Tests\Feature;

use App\Filament\Resources\Visits\Pages\CreateVisit;
use App\Filament\Resources\Visits\Pages\ListVisits;
use App\Models\Family;
use App\Models\Member;
use App\Models\User;
use App\Models\Visit;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Visitasi: tim (user) + pendamping dari data jemaat, dan anggota KK yang hadir dicentang. */
class VisitasiPendampingTest extends TestCase
{
    use RefreshDatabase;

    public function test_pendamping_dan_daftar_hadir(): void
    {
        $this->seed(DatabaseSeeder::class);
        config(['sij.geocode_on_save' => false]);
        $admin = User::where('username', 'admin')->firstOrFail();
        $this->actingAs($admin);

        $kk = Family::create(['address' => 'Jl. Uji 2']);
        $kepala = Member::create(['name' => 'Budi Kepala', 'family_id' => $kk->id, 'family_relation' => 'kepala']);
        $anak = Member::create(['name' => 'Ani Anak', 'family_id' => $kk->id, 'family_relation' => 'anak']);
        $p1 = Member::create(['name' => 'Kiki Pendamping', 'alias' => 'Kiki']);
        $p2 = Member::create(['name' => 'Lina Pendamping']);

        Livewire::test(CreateVisit::class)
            ->fillForm([
                'family_id' => $kk->id, 'type' => 'rutin', 'planned_date' => today()->addDay()->toDateString(), 'status' => 'rencana',
                'team' => [$admin->id], 'pendamping' => [$p1->id, $p2->id],
            ])
            ->call('create')->assertHasNoFormErrors();

        $v = Visit::latest('id')->first();
        $this->assertEqualsCanonicalizing([$p1->id, $p2->id], $v->pendamping()->pluck('members.id')->all());
        $this->assertSame([$admin->id], $v->team()->pluck('users.id')->all());

        Livewire::test(ListVisits::class)
            ->callTableAction('laporan', $v, [
                'realized_date' => today()->toDateString(), 'attendee_member_ids' => [$kepala->id, $anak->id],
                'attendees' => 'Oma (kerabat)', 'conversation' => 'Uji',
            ])->assertHasNoTableActionErrors();

        $r = $v->refresh()->report;
        $this->assertSame('selesai', $v->status);
        $this->assertEqualsCanonicalizing([$kepala->id, $anak->id], $r->attendee_member_ids);
        $this->assertSame('Ani Anak, Budi Kepala, Oma (kerabat)', $r->daftar_hadir);

        $this->get('/admin/kunjungan/'.$v->id)->assertOk()
            ->assertSee('Kiki Pendamping')->assertSee('Ani Anak, Budi Kepala, Oma (kerabat)');
    }
}
