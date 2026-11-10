<?php

namespace Tests\Feature;

use App\Exports\ArrayExport;
use App\Filament\Resources\Guests\Pages\ListGuests;
use App\Filament\Resources\Members\Pages\ListMembers;
use App\Jobs\GeocodeFamily;
use App\Models\Family;
use App\Models\Guest;
use App\Models\Member;
use App\Models\MemberEvent;
use App\Models\Region;
use App\Models\User;
use App\Services\DuplicateFinder;
use App\Services\GrowthService;
use App\Services\GuestConverter;
use App\Services\MemberImporter;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class ServicesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    /** Simulasi file Data_Jemaat_Lengkap.xlsx sesuai temuan bagian 2 SRS. */
    public function test_import_applies_migration_rules(): void
    {
        $head = ['Display No', 'No Induk', 'Nama', 'Alias', 'Kelamin', 'Tgl Lahir', 'Umur', 'No. Handphone', 'Alamat', 'Wilayah', 'ID KK',
            'Hubungan', 'Status', 'Baptis di GKT', 'Sidi di GKT', 'Tgl Pernikahan', 'Atestasi Masuk', 'Atestasi Keluar', 'Wafat'];
        $rows = [
            ['SIB0001', '123', 'BUDI SANTOSO', 'Budi', 'L', '15/08/1958', 68, '0812-3456-7890', "Jl. Anggrek\t 5  ", 'Pesangrgahan ', 'KK-001', 'Kepala Keluarga', 'Jemaat Aktif', 0, 0, '1985-06-01', '-', '-', '-'],
            ['SIB0002', null, 'Sari Santoso', null, 'P', '1960-02-02', 66, '-', 'Jl. Anggrek 5', 'PESANGGRAHAN', 'KK-001', 'Istri', 'Jemaat Non-Aktif', 0, 0, '1985-06-01', '-', '-', '-'],
            ['SIB0003', null, 'Anak Santoso', null, 'L', '2015-05-05', 11, null, 'Jl. Anggrek 5', 'Pesanggrahan', 'KK-001', 'Anak', 'Non-Jemaat Aktif', 0, 0, '-', '-', '-', '-'],
            ['SIB0004', null, 'Opa Lama', null, 'L', '1930-01-01', 90, null, 'Belum Ada Alamat', '[PUJON', 'KK-002', 'Kepala', 'Meninggal', 0, 0, '1900-01-01', '-', '-', '2024-03-03'],
            ['SIB0386', null, 'Pindah Keluar', null, 'P', '1990-01-01', 36, null, 'Malang', 'Belum Didefinisikan', 'KK-003', 'Kepala', 'Atestasi Keluar', 0, 0, '-', '-', '2025-01-10', '-'],
            ['', null, '', null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, 'x'],
        ];
        $path = storage_path('app/test-import.xlsx');
        file_put_contents($path, Excel::raw(new ArrayExport($head, $rows), \Maatwebsite\Excel\Excel::XLSX));

        $imp = app(MemberImporter::class);
        $data = $imp->read($path);
        $map = $imp->guessMapping($data['headings']);
        $this->assertSame(0, $map['code']);
        $this->assertSame(2, $map['name']);
        $this->assertSame(12, $map['status']);

        $res = $imp->import($data['rows'], $map);
        $this->assertSame(5, $res['created']);
        $this->assertCount(1, $res['failed']);

        $budi = Member::where('code', 'SIB0001')->first();
        $this->assertSame('Budi Santoso', $budi->name);              // Title Case
        $this->assertSame('+6281234567890', $budi->phone);          // E.164
        $this->assertSame(['anggota', 'aktif'], [$budi->membership_status, $budi->activity_status]);
        $this->assertSame('Pesanggrahan', $budi->family->region->name); // salah ketik dicocokkan
        $this->assertSame('Jl. Anggrek 5', $budi->family->address);    // trim spasi/tab
        $this->assertSame($budi->id, $budi->family->head_member_id);
        $this->assertSame(2, $budi->family->members()->where('membership_status', '!=', 'simpatisan')->count() + 0);

        $this->assertSame('pasif', Member::where('code', 'SIB0002')->value('activity_status'));
        $this->assertSame('simpatisan', Member::where('code', 'SIB0003')->value('membership_status'));
        $opa = Member::where('code', 'SIB0004')->first();
        $this->assertSame('meninggal', $opa->membership_status);
        $this->assertNull($opa->marriage_date);                     // 1900-01-01 dibuang
        $this->assertTrue($opa->events()->where('type', 'wafat')->exists());
        $this->assertSame('Pujon', $opa->family->region->name);
        $this->assertNull(Member::where('code', 'SIB0386')->first()->family->region_id);
        $this->assertSame('keluar', Member::where('code', 'SIB0386')->value('membership_status'));

        // Kolom asli Excel tetap tersimpan.
        $this->assertSame('Jemaat Aktif', $budi->legacy_status);
        $this->assertSame('Pesangrgahan', $budi->legacy_region);
        $this->assertSame('KK-001', $budi->legacy_family_code);
        $this->assertSame('Jl. Anggrek 5', $budi->legacy_address);
        $this->assertFalse($budi->baptized_at_gkt);
        $this->assertSame('Belum Didefinisikan', Member::where('code', 'SIB0386')->value('legacy_region'));
        $this->assertSame(2, MemberEvent::where('member_id', $budi->id)->where('data_status', 'belum_diketahui')->count());

        // DJ-02: kode berikutnya melanjutkan nomor terakhir.
        $this->assertSame('SIB0387', Member::nextCode());

        // Import ulang tidak menggandakan data.
        $res2 = $imp->import($data['rows'], $map);
        $this->assertSame(5, $res2['updated']);
        $this->assertSame(5, Member::count());

        // Rekap status sesuai pemetaan.
        $s = app(GrowthService::class)->statusSummary();
        $this->assertSame(1, $s['Anggota Aktif']);
        $this->assertSame(1, $s['Meninggal']);
        @unlink($path);
    }

    public function test_duplicate_detection_and_merge(): void
    {
        $a = Member::create(['name' => 'Chandra Kurniawan', 'birth_date' => '1958-08-15']);
        $b = Member::create(['name' => 'Candra Kurniawan', 'birth_date' => '1958-08-16', 'phone' => '0811111111']);
        $f = app(DuplicateFinder::class);
        $this->assertCount(1, $f->allPairs());
        $this->assertCount(1, $f->candidatesFor('CHANDRA KURNIAWAN', '1958-08-18', $a->id));
        $f->merge($a, $b);
        $this->assertSame(1, Member::count());
        $this->assertSame('+62811111111', $a->fresh()->phone);
    }

    public function test_guest_conversion(): void
    {
        $g = Guest::create(['name' => 'Tamu Baru', 'phone' => '081299990000', 'city' => 'Batu', 'origin_church' => 'GKJW', 'consent_contact' => true]);
        $this->assertNotNull($g->fu_due_at);
        $m = app(GuestConverter::class)->convert($g, ['family_id' => null, 'new_family_address' => 'Jl. Baru 1', 'atestasi_masuk' => true]);
        $this->assertSame('jemaat', $g->fresh()->fu_stage);
        $this->assertSame($m->id, $m->family->head_member_id);
        $this->assertTrue($m->events()->where('type', 'atestasi_masuk')->exists());
        $this->assertSame(1, app(GrowthService::class)->growthSeries()[now()->format('Y-m')]['detail']['tamu_jadi_anggota'] ?? 0);
    }

    public function test_region_suggestion_and_geocoding(): void
    {
        $r = Region::create(['name' => 'Uji', 'area_geojson' => ['type' => 'Polygon', 'coordinates' => [[[112.5, -7.9], [112.6, -7.9], [112.6, -7.8], [112.5, -7.8], [112.5, -7.9]]]]]);
        $this->assertTrue($r->contains(-7.85, 112.55));
        $this->assertFalse($r->contains(-7.7, 112.55));

        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([['lat' => '-7.85', 'lon' => '112.55', 'place_rank' => 30]])]);
        $f = Family::create(['address' => 'Jl. Anggrek 5']);
        (new GeocodeFamily($f->id))->handle();
        $f->refresh();
        $this->assertSame('tepat', $f->geocode_status);
        $this->assertSame($r->id, $f->suggested_region_id);

        // Geser pin manual -> status manual.
        $f->update(['latitude' => -7.86, 'longitude' => 112.56]);
        $this->assertSame('manual', $f->fresh()->geocode_status);
    }

    public function test_filament_tables_with_filters(): void
    {
        $this->seed(DemoSeeder::class);
        $this->actingAs(User::where('email', 'sekretariat@gktsinai.test')->first());
        Livewire::test(ListMembers::class)
            ->filterTable('age_group', 'lansia')->assertSuccessful()
            ->filterTable('kelengkapan', 'tanpa_sakramen')->assertSuccessful()
            ->filterTable('region', [Region::first()->id, 'none'])->assertSuccessful()
            ->searchTable('a')->assertSuccessful()
            ->callTableAction('export')->assertSuccessful();
        Livewire::test(ListGuests::class)->assertSuccessful()->filterTable('terlambat')->assertSuccessful();
    }
}
