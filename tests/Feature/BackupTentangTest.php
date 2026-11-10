<?php

namespace Tests\Feature;

use App\Filament\Pages\Tentang;
use App\Models\Member;
use App\Models\User;
use App\Services\DatabaseBackupService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\TestCase;

/** Restore menimpa tabel (DDL), jadi tidak bisa memakai RefreshDatabase/transaksi. */
class BackupTentangTest extends TestCase
{
    use DatabaseMigrations;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        config(['backup.pin' => '123456', 'backup.path' => 'backups-test']);
        $this->dir = app(DatabaseBackupService::class)->dir();
        File::deleteDirectory($this->dir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
        // DatabaseMigrations me-rollback semua tabel; paksa test RefreshDatabase berikutnya migrate ulang.
        \Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated = false;
    }

    private function admin(): User
    {
        return User::where('email', 'admin@gktsinai.test')->first();
    }

    public function test_backup_and_restore_round_trip(): void
    {
        $svc = app(DatabaseBackupService::class);
        Member::create(['name' => "Budi O'Neil", 'gender' => 'L', 'notes' => "Baris satu;\nBaris dua", 'legacy_address' => 'Jl. "Mawar"; No. 1']);

        $name = $svc->backup();
        $this->assertCount(1, $svc->list());
        $svc->validate($svc->path($name));

        Member::query()->delete();
        Member::create(['name' => 'Data setelah backup']);

        $jumlah = $svc->restore($svc->path($name));
        $this->assertGreaterThan(10, $jumlah);
        $this->assertSame(["Budi O'Neil"], Member::pluck('name')->all());
        $this->assertSame("Baris satu;\nBaris dua", Member::value('notes'));
        $this->assertSame('Jl. "Mawar"; No. 1', Member::value('legacy_address'));
    }

    public function test_validate_rejects_foreign_file(): void
    {
        $f = tempnam(sys_get_temp_dir(), 'sql');
        file_put_contents($f, "CREATE TABLE `lain` (id int);\n");
        $this->expectException(\RuntimeException::class);
        app(DatabaseBackupService::class)->validate($f);
    }

    public function test_tentang_page_and_secret_panel(): void
    {
        $this->actingAs($this->admin());
        $this->get('/admin/tentang')->assertOk()->assertSee('Aturan Sistem')->assertSee('Riwayat Perubahan')
            ->assertSee('Cari menu');

        Livewire::test(Tentang::class)
            ->call('openPanel')->assertSet('panelOpen', true)
            ->set('pin', 'salah')->call('unlock')->assertSet('message', 'PIN salah.')
            ->set('pin', '123456')->call('unlock')->assertSet('message', null)
            ->call('createBackup')
            ->assertSee('backup_')
            ->set('konfirmasi', 'restore')->call('restore')
            ->assertSet('message', 'Ketik RESTORE (huruf besar) untuk konfirmasi.');

        $this->assertCount(1, app(DatabaseBackupService::class)->list());
    }

    public function test_non_super_admin_cannot_open_backup(): void
    {
        $this->actingAs(User::where('email', 'sekretariat@gktsinai.test')->first() ?? $this->sekretariat());
        $this->get('/admin/tentang')->assertOk();
        Livewire::test(Tentang::class)->call('openPanel')->assertForbidden();
    }

    private function sekretariat(): User
    {
        return tap(User::create(['name' => 'Sek', 'email' => 'sek@test', 'password' => 'password', 'is_active' => true]))->assignRole('sekretariat');
    }
}
