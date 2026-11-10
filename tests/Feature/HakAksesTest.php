<?php

namespace Tests\Feature;

use App\Filament\Pages\HakAksesPeran;
use App\Models\ActivitySession;
use App\Models\Family;
use App\Models\Member;
use App\Models\User;
use App\Support\HakAkses;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class HakAksesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([DatabaseSeeder::class, DemoSeeder::class]);
        HakAkses::lupakanCache();
    }

    private function user(string $username): User
    {
        return User::where('username', $username)->firstOrFail();
    }

    private function kode(string $url): int
    {
        $res = $this->get($url);

        return $res->status();
    }

    public function test_tim_ibadah_jemaat_ibadah_dan_pelayanan(): void
    {
        $this->flushSession();
        $this->actingAs($this->user('ibadah'));
        $m = Member::first();
        $f = Family::first();
        $s = ActivitySession::first();

        foreach ([
            '/admin', '/admin/jemaat', "/admin/jemaat/{$m->id}", '/admin/kk', "/admin/kk/{$f->id}",
            '/admin/sesi-kegiatan', '/admin/sesi-kegiatan/create', "/admin/sesi-kegiatan/{$s->id}/edit", '/admin/laporan-kehadiran',
            '/admin/jadwal-ibadah', '/admin/jadwal-bulanan', '/admin/nama-pelayan', '/admin/beban-pelayanan',
            '/admin/kaleidoskop-pelayanan', '/admin/struktur-organisasi', '/absen',
        ] as $url) {
            $this->assertSame(200, $this->kode($url), "Tim Ibadah harus bisa membuka {$url}");
        }

        foreach ([
            "/admin/jemaat/{$m->id}/edit", '/admin/jemaat/create', '/admin/tamu', '/admin/kunjungan',
            '/admin/pengguna', '/admin/pemeliharaan', '/admin/hak-akses', '/admin/peta-jemaat', '/admin/wilayah',
        ] as $url) {
            $this->assertContains($this->kode($url), [403, 404], "Tim Ibadah tidak boleh membuka {$url}");
        }

        // Nomor HP tidak tampil (bukan peran yang berhak melihat kontak).
        $this->assertFalse($this->user('ibadah')->lihatKontak());
        $this->assertTrue($this->user('ibadah')->kelolaJadwal());
        $this->assertTrue($this->user('ibadah')->kelolaKehadiran());
    }

    public function test_matriks_mengubah_akses_dan_peran_lama_tidak_berubah(): void
    {
        // Sebelum diatur: aturan lama tetap.
        $this->flushSession();
        $this->actingAs($this->user('penyambut'));
        $this->assertSame(200, $this->kode('/admin/tamu'));
        $this->assertContains($this->kode('/admin/jemaat'), [403, 404]);

        $this->flushSession();
        $this->actingAs($this->user('admin'));
        $this->assertSame(200, $this->kode('/admin/hak-akses'));
        Livewire::test(HakAksesPeran::class)
            ->assertSee('Tim Ibadah')
            ->set('matriks.tim_ibadah.tamu', 1)
            ->set('matriks.tim_ibadah.jemaat', 0)
            ->set('matriks.penyambut.jemaat', 1)
            ->set('matriks.penyambut.tamu', 0)
            ->call('simpan');

        $this->flushSession();
        $this->actingAs($this->user('ibadah'));
        $this->assertSame(200, $this->kode('/admin/tamu'));
        $this->assertContains($this->kode('/admin/jemaat'), [403, 404]);

        $this->flushSession();
        $this->actingAs($this->user('penyambut'));
        $this->assertSame(200, $this->kode('/admin/jemaat'));
        $this->assertContains($this->kode('/admin/tamu'), [403, 404]);

        // Koordinator wilayah tetap dibatasi wilayahnya walau matriks diubah.
        $this->assertSame(0, DB::table('role_menu_access')->where('role', 'koordinator_wilayah')->count());

        // Kembali ke bawaan.
        $this->flushSession();
        $this->actingAs($this->user('admin'));
        Livewire::test(HakAksesPeran::class)->call('kembalikan', 'penyambut');
        $this->flushSession();
        $this->actingAs($this->user('penyambut'));
        $this->assertSame(200, $this->kode('/admin/tamu'));
        $this->assertContains($this->kode('/admin/jemaat'), [403, 404]);
    }

    public function test_simpan_tanpa_perubahan_tidak_menyimpan_apa_pun(): void
    {
        $this->flushSession();
        $this->actingAs($this->user('admin'));
        Livewire::test(HakAksesPeran::class)->call('simpan');
        $this->assertSame(0, DB::table('role_menu_access')->count());
    }
}
