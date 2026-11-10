<?php

namespace Tests\Feature;

use App\Models\ActivitySession;
use App\Models\ActivityType;
use App\Models\Attendance;
use App\Models\Commission;
use App\Models\Family;
use App\Models\Guest;
use App\Models\Member;
use App\Models\User;
use App\Models\Visit;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([DatabaseSeeder::class, DemoSeeder::class]);
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    public function test_admin_pages_render_for_sekretariat(): void
    {
        $this->actingAs($this->user('sekretariat@gktsinai.test'));
        $m = Member::first();
        $f = Family::first();
        $s = ActivitySession::first();
        $g = Guest::first();
        $v = Visit::first();

        $urls = [
            '/admin', '/admin/members', '/admin/members/create', "/admin/members/{$m->id}", "/admin/members/{$m->id}/edit",
            '/admin/families', '/admin/families/create', "/admin/families/{$f->id}", "/admin/families/{$f->id}/edit",
            '/admin/regions', '/admin/regions/create', '/admin/commissions', '/admin/commissions/'.Commission::first()->id.'/edit', '/admin/activity-types',
            '/admin/activity-sessions', "/admin/activity-sessions/{$s->id}", '/admin/activity-sessions/create',
            '/admin/guests', "/admin/guests/{$g->id}", '/admin/visits', "/admin/visits/{$v->id}",
            '/admin/growth-targets', '/admin/pertumbuhan', '/admin/laporan-kehadiran', '/admin/daftar-perhatian',
            '/admin/usulan-visitasi', '/admin/peta-jemaat', '/admin/import-jemaat', '/admin/duplikat-jemaat',
            '/absen', "/absen/{$s->id}", "/absen/{$s->id}/data", "/sesi/{$s->id}/qr",
            "/pdf/jemaat/{$m->id}", "/pdf/kk/{$f->id}", '/pdf/rekap', '/pdf/bulanan?bulan='.now()->format('Y-m'),
        ];
        foreach ($urls as $u) {
            $res = $this->get($u);
            $this->assertSame(200, $res->status(), "GET {$u} -> {$res->status()} ".($res->exception ? $res->exception->getMessage().' @ '.$res->exception->getFile().':'.$res->exception->getLine() : ''));
        }
    }

    public function test_login_with_username_or_email(): void
    {
        \Livewire\Livewire::test(\App\Filament\Pages\Auth\Login::class)
            ->fillForm(['username' => 'admin@gktsinai.test', 'password' => 'password'])->call('authenticate')
            ->assertHasNoFormErrors();
        $this->assertAuthenticated();

        auth()->logout();
        \Livewire\Livewire::test(\App\Filament\Pages\Auth\Login::class)
            ->fillForm(['username' => 'Admin', 'password' => 'password'])->call('authenticate')
            ->assertHasNoFormErrors();
        $this->assertAuthenticated();
    }

    public function test_maintenance_page_super_admin_only(): void
    {
        $this->actingAs($this->user('admin@gktsinai.test'));
        $this->get('/admin/pemeliharaan')->assertOk()->assertSee('Status database');

        $this->flushSession();
        $this->actingAs($this->user('sekretariat@gktsinai.test'));
        $this->get('/admin/pemeliharaan')->assertForbidden();
    }

    public function test_role_restrictions(): void
    {
        $this->actingAs($this->user('penyambut@gktsinai.test'));
        $this->get('/admin/members')->assertForbidden();
        $this->get('/admin/peta-jemaat')->assertForbidden();
        $this->get('/absen')->assertOk();

        $this->flushSession();
        $this->actingAs($this->user('majelis@gktsinai.test'));
        $this->get('/admin/members')->assertOk();
        $this->get('/admin/import-jemaat')->assertForbidden();
        $this->get('/admin/pemeliharaan')->assertForbidden();
        $this->get('/pdf/rekap')->assertForbidden();

        // Catatan rahasia hanya terbaca pendeta & penulis.
        $v = Visit::whereHas('report', fn ($q) => $q->where('is_confidential', true))->first();
        $this->get("/admin/visits/{$v->id}")->assertOk()->assertDontSee($v->report->conversation);

        $this->flushSession();
        $this->actingAs($this->user('visitasi@gktsinai.test'));
        $this->get("/admin/visits/{$v->id}")->assertOk()->assertSee($v->report->conversation);
        $this->get('/admin/members')->assertOk();
    }

    public function test_public_guest_form_and_self_checkin(): void
    {
        $this->get('/tamu')->assertOk();
        $this->post('/tamu', ['name' => 'Tamu Uji', 'phone' => '0812 3456 7890', 'consent_contact' => 1])->assertRedirect();
        $g = Guest::where('name', 'Tamu Uji')->first();
        $this->assertSame('+6281234567890', $g->phone);
        $this->assertTrue($g->consent_contact);

        $s = ActivitySession::create(['activity_type_id' => ActivityType::where('is_general_worship', true)->value('id'), 'date' => today(), 'start_time' => now()->subMinutes(10)->format('H:i'), 'end_time' => now()->addHour()->format('H:i')]);
        $this->get("/c/{$s->qr_token}")->assertOk()->assertSee('Ketik nama Anda');
        $m = Member::aktif()->first();
        $found = $this->getJson("/c/{$s->qr_token}/cari?q=".urlencode(substr($m->name, 0, 5)))->assertOk()->json();
        $this->assertNotEmpty($found);
        $this->assertArrayNotHasKey('phone', $found[0]);
        $this->postJson("/c/{$s->qr_token}", ['member_id' => $m->id])->assertOk();
        $this->assertDatabaseHas('attendances', ['activity_session_id' => $s->id, 'member_id' => $m->id, 'method' => 'qr']);
    }

    public function test_staff_sync_and_member_code(): void
    {
        $this->actingAs($this->user('penyambut@gktsinai.test'));
        // Pilih tanggal + ibadah: sesi dibuat otomatis sekali saja.
        $type = ActivityType::where('is_general_worship', true)->value('id');
        $open = $this->postJson('/absen/buka', ['activity_type_id' => $type, 'date' => '2026-12-06'])->assertOk()->json();
        $this->assertSame($open['id'], $this->postJson('/absen/buka', ['activity_type_id' => $type, 'date' => '2026-12-06'])->json('id'));
        $this->getJson($open['data_url'])->assertOk()->assertJsonStructure(['members' => [['id', 'n', 'fh']], 'present']);
        $s = ActivitySession::find($open['id']);

        // Tamu salah catat bisa dihapus dari sesi.
        $tamu = Guest::create(['name' => 'Salah Catat']);
        $att = Attendance::create(['activity_session_id' => $s->id, 'guest_id' => $tamu->id, 'checked_in_at' => now()]);
        $this->getJson($open['data_url'])->assertJsonFragment(['id' => $att->id, 'name' => 'Salah Catat']);
        $this->deleteJson(str_replace('/0', '/'.$att->id, $open['guest_remove_url']))->assertOk()->assertJson(['guest_deleted' => true]);
        $this->assertDatabaseMissing('guests', ['id' => $tamu->id]);
        $ids = Member::aktif()->limit(3)->pluck('id');
        $this->postJson("/absen/{$s->id}/sync", ['items' => $ids->map(fn ($id) => ['member_id' => $id, 'at' => now()->toIso8601String()])->all()])
            ->assertOk()->assertJson(['ok' => true]);
        $this->postJson("/absen/{$s->id}/sync", ['items' => [['member_id' => $ids[0], 'undo' => true]]])->assertOk();
        $this->assertDatabaseMissing('attendances', ['activity_session_id' => $s->id, 'member_id' => $ids[0]]);

        $last = Member::orderByDesc('id')->first()->code;
        $new = Member::create(['name' => 'BUDI SANTOSO', 'membership_status' => 'simpatisan']);
        $this->assertSame('SIB'.str_pad((string) ((int) substr($last, 3) + 1), 4, '0', STR_PAD_LEFT), $new->code);
        $this->assertDatabaseHas('audit_logs', ['auditable_id' => $new->id, 'event' => 'dibuat']);
    }

    public function test_petugas_absensi_only_sees_attendance_screen(): void
    {
        $this->actingAs($this->user('absensi@gktsinai.test'));
        $this->get('/admin')->assertRedirect('/absen');
        $this->get('/admin/members')->assertRedirect('/absen');
        $this->get('/absen')->assertOk()->assertSee('absen-root', false)->assertDontSee('/admin"', false);

        // KU I, KU II, dan Gabungan pada tanggal yang sama adalah sesi terpisah; tanggal bebas dipilih.
        $type = ActivityType::where('is_general_worship', true)->value('id');
        $ku1 = $this->postJson('/absen/buka', ['activity_type_id' => $type, 'date' => '2026-12-13', 'kebaktian' => 'KU I'])->assertOk()->json('id');
        $ku2 = $this->postJson('/absen/buka', ['activity_type_id' => $type, 'date' => '2026-12-13', 'kebaktian' => 'KU II'])->assertOk()->json('id');
        $this->assertNotSame($ku1, $ku2);
        $this->assertSame($ku1, $this->postJson('/absen/buka', ['activity_type_id' => $type, 'date' => '2026-12-13', 'kebaktian' => 'KU I'])->json('id'));
        $this->assertSame('KU II', ActivitySession::find($ku2)->kebaktian);
        $this->postJson('/absen/buka', ['activity_type_id' => $type, 'date' => '2026-12-13', 'kebaktian' => 'KU X'])->assertUnprocessable();

        // Daftar hadir KU II ikut menampilkan KU I; jemaat yang sudah hadir di KU I tidak diabsen lagi di KU II.
        [$a, $b] = Member::where('membership_status', '!=', 'meninggal')->take(2)->pluck('id')->all();
        $this->postJson("/absen/{$ku1}/sync", ['items' => [['member_id' => $a]]])->assertOk();
        $this->postJson("/absen/{$ku2}/sync", ['items' => [['member_id' => $a], ['member_id' => $b]]])->assertOk();
        $this->assertSame([$b], ActivitySession::find($ku2)->attendances()->pluck('member_id')->all());
        $others = $this->getJson("/absen/{$ku2}/data")->assertOk()->json('others');
        $this->assertSame('KU I', $others[0]['kebaktian']);
        $this->assertArrayHasKey($a, $others[0]['present']);
        $tanpaKebaktian = $this->postJson('/absen/buka', ['activity_type_id' => $type, 'date' => '2026-12-20'])->json('id');
        $this->assertSame([], $this->getJson("/absen/{$tanpaKebaktian}/data")->json('others'));

        $this->post('/absen/keluar')->assertRedirect(route('filament.admin.auth.login'));
        $this->assertGuest();
    }
}
