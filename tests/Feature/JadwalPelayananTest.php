<?php

namespace Tests\Feature;

use App\Filament\Pages\BebanPelayanan;
use App\Models\Member;
use App\Models\ServantAlias;
use App\Models\ServiceAssignment;
use App\Models\User;
use App\Models\WorshipSchedule;
use App\Services\JadwalImporter;
use App\Services\PelayanMatcher;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class JadwalPelayananTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([DatabaseSeeder::class, DemoSeeder::class]);
        Member::create(['name' => 'ANIS WIJAYANTI', 'gender' => 'P', 'membership_status' => 'sidi']);
        Member::create(['name' => 'ANIS SETIAWAN', 'gender' => 'L', 'membership_status' => 'sidi']);
        Member::create(['name' => 'FERA KURNIAWATI', 'gender' => 'P', 'membership_status' => 'simpatisan']);
        $kepala = Member::create(['name' => 'DONI KALEB', 'gender' => 'L', 'membership_status' => 'sidi', 'family_relation' => 'kepala']);
        Member::create(['name' => 'DONI KALEB JUNIOR', 'gender' => 'L', 'membership_status' => 'baptis', 'family_relation' => 'anak']);
        $this->kepala = $kepala;
    }

    private Member $kepala;

    private function excel(): string
    {
        $book = new Spreadsheet;
        $s = $book->getActiveSheet();
        $s->setCellValue('A1', 'Bulan : November 2026');
        $s->setCellValue('A3', 'Tanggal');
        $s->mergeCells('A3:A4');
        $s->setCellValue('B3', '01 November 2026');
        $s->mergeCells('B3:C3');
        $s->setCellValue('D3', '08 November 2026');
        $s->mergeCells('D3:E3');
        $s->fromArray(['Ibadah Umum I', 'Ibadah Umum 2', 'Ibadah Umum I', 'Ibadah Umum 2'], null, 'B4');
        $baris = [
            5 => ['Pembicara', 'Ev. Darwis D. Tasemak', null, 'Pdt. Silvya Suherman', null, true],
            6 => ['Tema', 'Kasihilah TUHAN Allahmu', null, 'Mengenali Hasrat', null, true],
            7 => ['Liturgis', 'Pnt. Anis W.', 'Ibu Fera', 'Bpk. Ronius', 'dr. Nadia', false],
            8 => ['Singer', 'Sdr. Ephafras', 'Pdt. Dody', 'Ibu Fera', '', false],
            9 => ['', 'Ls. Falia', 'Ibu Elsye', 'Sdr. Gamaliel', 'Ibu Diyah', false],
            10 => ['Penyambut &', 'Kel. Bp. Doni Kaleb', 'Ibu Wiwit & Sdra. Henry', 'Kel. Bp. Doni Kaleb', 'Ibu Fera', false],
            11 => ['Keterangan', 'Stola Hijau', null, 'Stola Hijau', null, true],
        ];
        foreach ($baris as $r => $v) {
            $s->fromArray(array_slice($v, 0, 5), null, "A{$r}");
            if ($v[5]) {
                $s->mergeCells("B{$r}:C{$r}");
                $s->mergeCells("D{$r}:E{$r}");
            }
        }
        $path = tempnam(sys_get_temp_dir(), 'jdw').'.xlsx';
        (new Xlsx($book))->save($path);

        return $path;
    }

    public function test_import_excel_dan_pencocokan_nama(): void
    {
        $importer = app(JadwalImporter::class);
        $jadwal = $importer->baca($this->excel());
        $this->assertSame(['2026-11-01', '2026-11-08'], array_keys($jadwal));
        $this->assertSame('Kasihilah TUHAN Allahmu', $jadwal['2026-11-01']['info']['tema']);
        $this->assertSame('Stola Hijau', $jadwal['2026-11-08']['info']['keterangan']);

        $hasil = $importer->simpan($jadwal);
        $this->assertSame(2, $hasil['tanggal']);

        $s1 = WorshipSchedule::whereDate('date', '2026-11-01')->first();
        // Sel merge = berlaku di kedua kebaktian.
        $this->assertDatabaseHas('service_assignments', ['worship_schedule_id' => $s1->id, 'peran' => 'pembicara', 'kebaktian' => null, 'nama' => 'Ev. Darwis D. Tasemak']);
        // Dua baris singer, "&" dipecah menjadi dua orang.
        $this->assertSame(4, $s1->assignments()->where('peran', 'singer')->count());
        $this->assertSame(['Ibu Wiwit', 'Sdra. Henry'], $s1->assignments()->where('peran', 'penyambut')->where('kebaktian', 'KU II')->pluck('nama')->all());

        // "Pnt. Anis W." -> inisial W cocok ANIS WIJAYANTI saja; "Ibu Fera" -> FERA; "Kel. Bp. Doni Kaleb" -> kepala keluarga.
        $anis = Member::where('name', 'ANIS WIJAYANTI')->value('id');
        $this->assertSame($anis, ServiceAssignment::where('nama', 'Pnt. Anis W.')->value('member_id'));
        $this->assertSame(Member::where('name', 'FERA KURNIAWATI')->value('id'), ServiceAssignment::where('nama', 'Ibu Fera')->value('member_id'));
        $this->assertSame($this->kepala->id, ServiceAssignment::where('nama', 'Kel. Bp. Doni Kaleb')->value('member_id'));
        $this->assertTrue(ServiceAssignment::where('nama', 'Kel. Bp. Doni Kaleb')->value('is_keluarga'));

        // Hamba Tuhan yang tidak ada di data jemaat diusulkan sebagai pekerja; lainnya belum terdaftar.
        $this->assertSame('pekerja', ServantAlias::where('key', 'silvya suherman')->value('category'));
        $this->assertNull(ServantAlias::where('key', 'henry')->value('member_id'));

        // Hubungkan manual, lalu import ulang: hubungan tetap dipakai dan tidak dobel.
        $henry = Member::create(['name' => 'HENRY GUNAWAN', 'membership_status' => 'simpatisan']);
        ServantAlias::where('key', 'henry')->first()->update(['member_id' => $henry->id, 'is_manual' => true]);
        $this->assertSame($henry->id, ServiceAssignment::where('nama', 'Sdra. Henry')->value('member_id'));
        $jumlah = ServiceAssignment::count();
        $importer->simpan($importer->baca($this->excel()));
        $this->assertSame($jumlah, ServiceAssignment::count());
        $this->assertSame($henry->id, ServiceAssignment::where('nama', 'Sdra. Henry')->value('member_id'));
    }

    public function test_nama_ambigu_tidak_dicocokkan(): void
    {
        $m = app(PelayanMatcher::class);
        $this->assertNull($m->cocokkan($m->urai('Pnt. Anis')));            // dua ANIS, tanpa petunjuk
        $this->assertSame('ANIS WIJAYANTI', $m->cocokkan($m->urai('Ibu Anis'))?->name); // sapaan Ibu -> perempuan
        $this->assertSame(['Ibu Lismiatin', 'Ibu Rut'], $m->pecah('Ibu Lismiatin & Ibu Rut'));
    }

    public function test_halaman_pelayanan_dan_hak_akses(): void
    {
        app(JadwalImporter::class)->simpan(app(JadwalImporter::class)->baca($this->excel()));
        $s = WorshipSchedule::first();

        $this->actingAs(User::where('email', 'sekretariat@gktsinai.test')->firstOrFail());
        foreach (['/admin/jadwal-bulanan?bulan=2026-11', '/admin/jadwal-ibadah', '/admin/jadwal-ibadah/create',
            "/admin/jadwal-ibadah/{$s->id}", "/admin/jadwal-ibadah/{$s->id}/edit", '/admin/beban-pelayanan', '/admin/nama-pelayan',
            '/admin/kaleidoskop-pelayanan'] as $u) {
            $res = $this->get($u);
            $this->assertSame(200, $res->status(), "GET {$u} -> {$res->status()} ".($res->exception?->getMessage() ?? ''));
        }
        $this->get("/admin/jadwal-ibadah/{$s->id}")->assertSee('Kasihilah TUHAN Allahmu')->assertSee('Pnt. Anis W.');

        Livewire::test(BebanPelayanan::class)
            ->set('dari', '2026-11-01')->set('sampai', '2026-11-30')
            ->assertSee('ANIS WIJAYANTI')->assertSee('DONI KALEB');
        $this->assertSame(2, Livewire::test(BebanPelayanan::class)
            ->set('dari', '2026-11-01')->set('sampai', '2026-11-30')->instance()->rekap()->firstWhere('nama', 'DONI KALEB')['total']);

        $this->flushSession();
        $this->actingAs(User::where('email', 'majelis@gktsinai.test')->firstOrFail());
        $this->get('/admin/jadwal-bulanan')->assertOk();
        $this->get('/admin/jadwal-ibadah/create')->assertForbidden();

        $this->flushSession();
        $this->actingAs(User::where('email', 'penyambut@gktsinai.test')->firstOrFail());
        $this->get('/admin/beban-pelayanan')->assertForbidden();
    }

    public function test_import_format_rekap_tahunan(): void
    {
        $book = new Spreadsheet;
        $s = $book->getActiveSheet()->setTitle('Sept');
        $s->setCellValue('A1', 'Bulan : September 2026');
        $s->setCellValue('A3', 'Tanggal');
        $s->mergeCells('A3:A5');
        // "6 September" tanpa tahun; 27 Sep ibadah gabungan; kolom "30 Agustus" sisa salinan sheet lain.
        foreach (['B' => '6 September', 'D' => '27 September', 'F' => '30 Agustus'] as $c => $v) {
            $s->setCellValue("{$c}3", $v);
            $c2 = chr(ord($c) + 1);
            $s->mergeCells("{$c}3:{$c2}3");
        }
        $s->setCellValue('B4', "Ibadah Umum I\npukul 07.00 WIB");
        $s->setCellValue('C4', "Ibadah Umum 2\npukul 09.00 WIB");
        $s->setCellValue('D4', 'Ibadah Gabungan (Paska)');
        $s->mergeCells('D4:E5');
        $s->fromArray(['Pembicara', 'Pdt. Candrawati, S.E., M. Th', null, 'Dr. Kettler (U.T. Apeldoom)', null, 'Salah', null], null, 'A6');
        $s->mergeCells('B6:C6');
        $s->mergeCells('D6:E6');
        $s->fromArray(['Penyambut &  Kolektan', 'Jay + Papa Mama', 'Ibu Fera', 'Bpk. Fajar', 'pnt. Nurkartika dan Ev. Daniel'], null, 'A7');
        $s->setCellValue('A9', str_repeat('Bila ada kendala mengenai jadwal di atas hubungi sekretariat. ', 3));
        $s->mergeCells('A9:G12');
        $path = tempnam(sys_get_temp_dir(), 'jdw').'.xlsx';
        (new Xlsx($book))->save($path);

        $j = app(JadwalImporter::class)->baca($path);
        $this->assertSame(['2026-09-06', '2026-09-27'], array_keys($j));
        $this->assertSame('Ibadah Gabungan (Paska)', $j['2026-09-27']['info']['keterangan']);
        $this->assertSame(['Gabungan'], array_values(array_unique(array_column($j['2026-09-27']['petugas'], 'kebaktian'))));
        $this->assertContains('Jay + Papa Mama', array_column($j['2026-09-06']['petugas'], 'nama'));

        $m = app(PelayanMatcher::class);
        $this->assertSame('candrawati', $m->urai('Pdt. Candrawati, S.E., M. Th')['kunci']);
        $this->assertSame('kettler', $m->urai('Dr. Kettler (U.T. Apeldoom)')['kunci']);
        Member::create(['name' => 'NURKARTIKAWATI', 'gender' => 'P', 'membership_status' => 'sidi']);
        $this->assertSame('NURKARTIKAWATI', $m->cocokkan($m->urai('pnt. Nurkartika'))?->name);
    }
}
