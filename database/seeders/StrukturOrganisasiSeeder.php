<?php

namespace Database\Seeders;

use App\Models\Commission;
use App\Models\OrgPeriod;
use App\Models\OrgUnit;
use App\Services\PelayanMatcher;
use Illuminate\Database\Seeder;

/**
 * Struktur Pertanggungjawaban Pelayanan GKT Sinai Batu periode 2025–2027
 * (dari dokumen Program Pelayanan / Raker). Dilewati bila periode sudah ada.
 */
class StrukturOrganisasiSeeder extends Seeder
{
    private const DARWIS = 'Ev. Darwis D. Tasemak';

    public function run(): void
    {
        if (OrgPeriod::where('name', '2025–2027')->exists()) {
            return;
        }
        $periode = OrgPeriod::create(['name' => '2025–2027', 'start_date' => '2025-01-01', 'end_date' => '2027-12-31', 'is_active' => true]);
        $urut = 0;

        $unit = function (string $type, string $name, array $orang = [], array $sub = [], ?OrgUnit $parent = null, ?string $komisi = null) use ($periode, &$unit, &$urut) {
            $u = OrgUnit::create([
                'org_period_id' => $periode->id, 'parent_id' => $parent?->id, 'type' => $type, 'name' => $name, 'sort' => $urut++,
                'commission_id' => $komisi ? $this->komisi($komisi) : null,
            ]);
            foreach ($orang as $i => [$role, $nama, $note]) {
                $u->positions()->create(['role' => $role, 'name' => $nama, 'note' => $note, 'sort' => $i,
                    'member_id' => $this->jemaat($nama)]);
            }
            foreach ($sub as $s) {
                $unit('sub_bidang', $s, [], [], $u);
            }

            return $u;
        };

        $penatua = ['Pnt. Arie', 'Pnt. Hizkia Anugrah Dias', 'Pnt. Anis Wahyuningsih', 'Pnt. Dany Hendrawan', 'Pnt. Gatot Prawiro',
            'Pnt. Tek Lie', 'Pnt. Lilik Sumarni', 'Pnt. Debora Setiawati', 'Pnt. Diki Andika'];

        $unit('pimpinan', 'Rapat Majelis Jemaat', array_merge(
            [['ketua_majelis', 'Pnt. Dany Hendrawan', null]],
            array_map(fn ($n) => ['penatua', $n, null], $penatua),
        ));
        $unit('pimpinan', 'Gembala Sidang & Tim Hamba Tuhan', [
            ['gembala', self::DARWIS, null],
            ['hamba_tuhan', 'Ev. Daniel Manafe', null],
            ['hamba_tuhan', 'Bp. Dedy D. Reppy', null],
            ['hamba_tuhan', 'Bp. Ronius Lase', null],
        ]);
        $unit('sekretariat', 'Sekretaris MJ & Sekretariat', [['sekretaris_mj', 'Pnt. Arie', null], ['hamba_tuhan', self::DARWIS, null]],
            ['Kepala Kantor Gereja', 'TU Gereja (Tim Sekretariat)']);

        $unit('bidang', 'Ibadah', [
            ['penatua_pj', 'Pnt. Hizkia Anugrah Dias', null],
            ['hamba_tuhan', self::DARWIS, 'Teologi & pengajaran'],
            ['hamba_tuhan', 'Ev. Daniel Manafe', 'Musik'],
        ], ['Komisi Ibadah dan Musik Gereja', 'Paduan Suara', 'Tim Pemusik, Pemimpin Ibadah dan Pemuji',
            'Sound-system, Penyambut Tamu, Kolektor, Multimedia', 'Pelatih Paduan Suara, Tenaga Ahli Musik Gereja']);
        $unit('bidang', 'Misi dan PI', [
            ['penatua_pj', 'Pnt. Anis Wahyuningsih', null], ['penatua_pj', 'Pnt. Tek Lie', null], ['hamba_tuhan', self::DARWIS, null],
        ], ['Dana Misi', 'Tim Misi dan PI', 'Lokasi Misi Adopsi dan Proyek-proyek Misi', 'Pos PI']);
        $unit('bidang', 'Pembinaan dan Pemuridan', [['penatua_pj', 'Pnt. Dany Hendrawan', null]],
            ['Altar: Personal, Family & Komunitas', 'Kelompok Sel', 'Ceramah-ceramah, Pelatihan-pelatihan', 'Olahraga']);
        $unit('bidang', 'Sarana & Prasarana Gereja', [['penatua_pj', 'Pnt. Gatot Prawiro', null], ['hamba_tuhan', self::DARWIS, null]],
            ['Gedung-gedung di Gereja', 'Kebutuhan di Rumah Hamba Tuhan', 'Kendaraan Gereja (Mobil & Sepeda Motor)', 'Fasilitas-fasilitas Lain',
                'OB Gereja', 'Driver', 'Koster']);
        $unit('bidang', 'Pemerhati / Visitasi Jemaat', [['penatua_pj', 'Pnt. Tek Lie', null], ['hamba_tuhan', self::DARWIS, null]],
            ['Visitasi Umum', 'Visitasi Khusus', 'Urgensi Pelayanan']);
        $unit('bidang', 'Diakonia', [['penatua_pj', 'Pnt. Lilik Sumarni', 'Umum'], ['penatua_pj', 'Pnt. Tek Lie', 'Pemerhati'], ['hamba_tuhan', self::DARWIS, null]],
            ['Beasiswa Studi SD sampai Perguruan Tinggi', 'Kelahiran, Kedukaan, Janda/Duda, Yatim Piatu, Orang-orang Miskin']);
        $unit('bidang', 'Keuangan', [
            ['penatua_pj', 'Pnt. Debora Setiawati', null], ['penatua_pj', 'Pnt. Diki Andika', null], ['hamba_tuhan', self::DARWIS, null],
        ]);

        foreach ([
            ['Komisi Anak', 'Pnt. Anis Wahyuningsih', 'Bp. Dedy D. Reppy', ['sekolah minggu', 'anak']],
            ['JoshComm', 'Pnt. Diki Andika', 'Ev. Daniel Manafe', ['josh']],
            ['Komisi Wanita', 'Pnt. Lilik Sumarni', 'Bp. Ronius Lase', ['wanita']],
            ['Komisi Pria', 'Pnt. Gatot Prawiro', 'Bp. Ronius Lase', ['pria']],
            ['SYC', 'Pnt. Arie', self::DARWIS, ['syc']],
            ['Komisi Usia Indah (Lansia)', 'Pnt. Debora Setiawati', self::DARWIS, ['lansia', 'usia indah']],
        ] as [$nama, $mp, $ht, $kata]) {
            $unit('komisi', $nama, [['majelis_pendamping', $mp, null], ['hamba_tuhan', $ht, null]], [], null, implode('|', $kata));
        }
    }

    /** Hubungkan ke data jemaat bila namanya cocok tunggal (yang ambigu dibiarkan untuk dipilih manual). */
    private function jemaat(string $nama): ?int
    {
        $m = app(PelayanMatcher::class);

        return $m->cocokkan($m->urai($nama))?->id;
    }

    private function komisi(string $kata): ?int
    {
        foreach (explode('|', $kata) as $k) {
            if ($id = Commission::query()->where('name', 'like', "%{$k}%")->value('id')) {
                return $id;
            }
        }

        return null;
    }
}
