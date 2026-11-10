<?php

namespace Tests\Feature;

use App\Filament\Resources\Members\Pages\ViewMember;
use App\Filament\Resources\Members\RelationManagers\AttendancesRelationManager;
use App\Filament\Resources\Members\RelationManagers\ServiceAssignmentsRelationManager;
use App\Filament\Resources\Members\RelationManagers\VisitsRelationManager;
use App\Models\ActivitySession;
use App\Models\ActivityType;
use App\Models\Attendance;
use App\Models\Family;
use App\Models\Member;
use App\Models\ServiceAssignment;
use App\Models\User;
use App\Models\Visit;
use App\Models\WorshipSchedule;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Tab Visitasi, Kehadiran, dan Pelayanan di profil jemaat. */
class ProfilJemaatTest extends TestCase
{
    use RefreshDatabase;

    private Member $budi;

    private Member $istri;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        config(['sij.geocode_on_save' => false]);
        $this->actingAs(User::where('username', 'admin')->firstOrFail());

        $kk = Family::create(['address' => 'Jl. Uji 1']);
        $this->budi = Member::create(['name' => 'Budi Uji', 'gender' => 'L', 'family_id' => $kk->id, 'family_relation' => 'kepala', 'membership_status' => 'sidi']);
        $this->istri = Member::create(['name' => 'Sari Uji', 'gender' => 'P', 'family_id' => $kk->id, 'family_relation' => 'istri', 'membership_status' => 'sidi']);
    }

    public function test_tab_visitasi_menampilkan_kunjungan_kk_dan_pribadi(): void
    {
        $kk = $this->budi->family_id;
        $keKk = Visit::create(['family_id' => $kk, 'type' => 'rutin', 'planned_date' => '2026-10-01', 'status' => 'selesai', 'realized_date' => '2026-10-01']);
        $keIstri = Visit::create(['member_id' => $this->istri->id, 'type' => 'sakit', 'planned_date' => '2026-10-10']);
        $lain = Visit::create(['family_id' => Family::create(['address' => 'Jl. Lain'])->id, 'type' => 'rutin', 'planned_date' => '2026-10-02']);

        $this->assertSame($kk, $keIstri->refresh()->family_id);
        Livewire::test(VisitsRelationManager::class, ['ownerRecord' => $this->budi, 'pageClass' => ViewMember::class])
            ->assertOk()->assertCanSeeTableRecords([$keKk, $keIstri])->assertCanNotSeeTableRecords([$lain])
            ->assertSee('Seluruh KK')->assertSee('Sari Uji');

        // Jemaat tanpa KK: hanya kunjungan khusus ke dirinya.
        $sendiri = Member::create(['name' => 'Tanpa KK']);
        $v = Visit::create(['member_id' => $sendiri->id, 'type' => 'rutin', 'planned_date' => '2026-10-05']);
        Livewire::test(VisitsRelationManager::class, ['ownerRecord' => $sendiri, 'pageClass' => ViewMember::class])
            ->assertCanSeeTableRecords([$v])->assertCanNotSeeTableRecords([$keKk]);
    }

    public function test_tab_kehadiran_dan_ringkasan(): void
    {
        $umum = ActivityType::where('is_general_worship', true)->firstOrFail();
        $minggu = today()->subWeeks(1)->startOfWeek()->addDays(6);
        $ku1 = ActivitySession::create(['activity_type_id' => $umum->id, 'date' => $minggu, 'kebaktian' => 'KU I']);
        $ku2 = ActivitySession::create(['activity_type_id' => $umum->id, 'date' => $minggu, 'kebaktian' => 'KU II']);
        ActivitySession::create(['activity_type_id' => $umum->id, 'date' => $minggu->copy()->subWeek()]);
        $a = Attendance::create(['activity_session_id' => $ku1->id, 'member_id' => $this->budi->id, 'method' => 'petugas', 'checked_in_at' => now()]);
        Attendance::create(['activity_session_id' => $ku2->id, 'member_id' => $this->budi->id, 'method' => 'qr', 'checked_in_at' => now()]);

        // KU I + KU II di hari yang sama = 1 ibadah; total 2 Minggu.
        $r = AttendancesRelationManager::ringkasan($this->budi);
        $this->assertSame(['hadir' => 1, 'total' => 2, 'persen' => 50], array_slice($r, 0, 3));

        Livewire::test(AttendancesRelationManager::class, ['ownerRecord' => $this->budi, 'pageClass' => ViewMember::class])
            ->assertOk()->assertCanSeeTableRecords([$a])->assertSee('KU I')->assertSee('hadir 1 dari 2');
    }

    public function test_tab_pelayanan(): void
    {
        $jadwal = WorshipSchedule::create(['date' => today()->addWeek(), 'tema' => 'Kasih']);
        $t = ServiceAssignment::create(['worship_schedule_id' => $jadwal->id, 'peran' => 'liturgis', 'nama' => 'Pnt. Budi', 'kunci' => 'budi', 'member_id' => $this->budi->id]);

        Livewire::test(ServiceAssignmentsRelationManager::class, ['ownerRecord' => $this->budi, 'pageClass' => ViewMember::class])
            ->assertOk()->assertCanSeeTableRecords([$t])->assertSee('Liturgis')->assertSee('tugas berikutnya');
    }

    public function test_halaman_profil_terbuka(): void
    {
        $this->get('/admin/jemaat/'.$this->budi->id)->assertOk()->assertSee('Visitasi')->assertSee('Kehadiran')->assertSee('Pelayanan');
    }
}
