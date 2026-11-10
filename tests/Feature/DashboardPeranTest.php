<?php

namespace Tests\Feature;

use App\Filament\Pages\JadwalBulanan;
use App\Filament\Widgets\AttendanceTrendChart;
use App\Filament\Widgets\CakupanVisitasiChart;
use App\Filament\Widgets\HadirBaruKembali;
use App\Filament\Widgets\KomisiSaya;
use App\Filament\Widgets\KunjunganSaya;
use App\Filament\Widgets\PelayananMingguIni;
use App\Filament\Widgets\WilayahSaya;
use App\Models\ActivitySession;
use App\Models\ActivityType;
use App\Models\Attendance;
use App\Models\Commission;
use App\Models\Family;
use App\Models\Guest;
use App\Models\Member;
use App\Models\Region;
use App\Models\ServantAlias;
use App\Models\ServiceAssignment;
use App\Models\User;
use App\Models\Visit;
use App\Models\WorshipSchedule;
use App\Services\PemeriksaJadwal;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardPeranTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        config(['sij.geocode_on_save' => false]);
    }

    private function user(string $peran, array $attr = []): User
    {
        return tap(User::create(['name' => ucfirst($peran), 'username' => $peran, 'password' => 'password', 'is_active' => true] + $attr))->assignRole($peran);
    }

    public function test_tren_per_kebaktian_dan_total_unik(): void
    {
        $umum = ActivityType::where('is_general_worship', true)->firstOrFail();
        $d = today()->subDays(3);
        $ku1 = ActivitySession::create(['activity_type_id' => $umum->id, 'date' => $d, 'kebaktian' => 'KU I']);
        $ku2 = ActivitySession::create(['activity_type_id' => $umum->id, 'date' => $d, 'kebaktian' => 'KU II']);
        [$a, $b] = [Member::create(['name' => 'A']), Member::create(['name' => 'B'])];
        foreach ([[$ku1, $a], [$ku1, $b], [$ku2, $a]] as [$s, $m]) {
            Attendance::create(['activity_session_id' => $s->id, 'member_id' => $m->id, 'method' => 'petugas', 'checked_in_at' => now()]);
        }
        Attendance::create(['activity_session_id' => $ku2->id, 'guest_id' => Guest::create(['name' => 'Tamu', 'first_visit_at' => $d])->id, 'method' => 'petugas', 'checked_in_at' => now()]);

        $data = AttendanceTrendChart::data();
        $this->assertSame([2], $data['perKebaktian']['KU I']);
        $this->assertSame([2], $data['perKebaktian']['KU II']);
        $this->assertSame([3], $data['total']); // A dihitung sekali + B + 1 tamu
    }

    public function test_hadir_pertama_dan_kembali(): void
    {
        $umum = ActivityType::where('is_general_worship', true)->firstOrFail();
        $lama = ActivitySession::create(['activity_type_id' => $umum->id, 'date' => today()->subWeeks(6)]);
        $baru = ActivitySession::create(['activity_type_id' => $umum->id, 'date' => today()]);
        [$x, $y, $z] = [Member::create(['name' => 'Kembali']), Member::create(['name' => 'Baru']), Member::create(['name' => 'Rutin'])];
        Attendance::create(['activity_session_id' => $lama->id, 'member_id' => $x->id, 'method' => 'petugas', 'checked_in_at' => now()]);
        $rutin = ActivitySession::create(['activity_type_id' => $umum->id, 'date' => today()->subWeek()]);
        Attendance::create(['activity_session_id' => $rutin->id, 'member_id' => $z->id, 'method' => 'petugas', 'checked_in_at' => now()]);
        foreach ([$x, $y, $z] as $m) {
            Attendance::create(['activity_session_id' => $baru->id, 'member_id' => $m->id, 'method' => 'petugas', 'checked_in_at' => now()]);
        }

        $d = HadirBaruKembali::data();
        $this->assertSame(['Baru'], $d['pertama']->pluck('member.name')->all());
        $this->assertSame(['Kembali'], $d['kembali']->pluck('member.name')->all());
    }

    public function test_peringatan_jadwal_dobel_dan_terlalu_sering(): void
    {
        $budi = Member::create(['name' => 'Budi Pelayan']);
        $pdt = ServantAlias::create(['key' => 'henri', 'display_name' => 'Pdt. Henri', 'category' => 'pekerja']);
        foreach (range(0, 3) as $i) {
            $j = WorshipSchedule::create(['date' => today()->startOfMonth()->addDays($i * 7)]);
            ServiceAssignment::create(['worship_schedule_id' => $j->id, 'kebaktian' => 'KU I', 'peran' => 'singer', 'nama' => 'Budi', 'kunci' => 'budi', 'member_id' => $budi->id]);
            ServiceAssignment::create(['worship_schedule_id' => $j->id, 'kebaktian' => 'KU I', 'peran' => 'pembicara', 'nama' => 'Pdt. Henri', 'kunci' => 'henri']);
            ServiceAssignment::create(['worship_schedule_id' => $j->id, 'kebaktian' => 'KU II', 'peran' => 'pembicara', 'nama' => 'Pdt. Henri', 'kunci' => 'henri']);
        }
        $j0 = WorshipSchedule::orderBy('date')->first();
        ServiceAssignment::create(['worship_schedule_id' => $j0->id, 'kebaktian' => 'KU I', 'peran' => 'pemusik', 'nama' => 'Budi', 'kunci' => 'budi', 'member_id' => $budi->id]);

        $jadwal = WorshipSchedule::with('assignments.member', 'assignments.alias')->orderBy('date')->get();
        $cek = app(PemeriksaJadwal::class)->periksa($jadwal, 3);
        $jenis = collect($cek['peringatan'])->pluck('jenis')->all();
        $this->assertEqualsCanonicalizing(['dobel', 'sering'], $jenis); // pendeta (pekerja) & pembicara KU I+II tidak diperingatkan
        $this->assertArrayHasKey('m'.$budi->id, $cek['tandai']);
        $this->assertArrayNotHasKey('khenri', $cek['tandai']);

        $this->actingAs(User::where('username', 'admin')->firstOrFail());
        Livewire::test(JadwalBulanan::class)->assertSee('Perlu dicek')->assertSee('dobel sebagai Singer dan Pemusik');
    }

    public function test_widget_per_peran(): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        $this->actingAs($admin);
        $this->assertTrue(PelayananMingguIni::canView());
        $this->assertTrue(CakupanVisitasiChart::canView());
        $this->assertFalse(WilayahSaya::canView());
        $this->assertFalse(KomisiSaya::canView());
        Livewire::test(PelayananMingguIni::class)->assertSee('Belum ada jadwal');
        Livewire::test(HadirBaruKembali::class)->assertOk();

        // Koordinator wilayah
        $r = Region::firstOrFail();
        $kk = Family::create(['address' => 'Jl. A', 'region_id' => $r->id]);
        Member::create(['name' => 'Warga', 'family_id' => $kk->id, 'activity_status' => 'aktif']);
        $this->actingAs($this->user('koordinator_wilayah', ['region_id' => $r->id]));
        $this->assertTrue(WilayahSaya::canView());
        Livewire::test(WilayahSaya::class)->assertSee('Wilayah '.$r->name)->assertSee('KK belum dikunjungi');

        // Koordinator komisi
        $k = $this->user('koordinator_komisi');
        Commission::firstOrFail()->update(['coordinator_id' => $k->id]);
        $this->actingAs($k);
        $this->assertTrue(KomisiSaya::canView());
        Livewire::test(KomisiSaya::class)->assertSee(Commission::first()->name);

        // Tim visitasi dengan tugas
        $t = $this->user('tim_visitasi');
        Visit::create(['family_id' => $kk->id, 'type' => 'rutin', 'planned_date' => today()->addDays(2)])->team()->attach($t->id);
        $this->actingAs($t);
        $this->assertTrue(KunjunganSaya::canView());
        Livewire::test(KunjunganSaya::class)->assertSee('Rencana 7 hari ke depan');

        $this->actingAs($admin);
        $this->get('/admin')->assertOk();
    }
}
