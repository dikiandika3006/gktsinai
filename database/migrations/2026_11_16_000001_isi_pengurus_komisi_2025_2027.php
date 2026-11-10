<?php

use App\Models\OrgPeriod;
use App\Models\OrgUnit;
use App\Services\PelayanMatcher;
use Illuminate\Database\Migrations\Migration;

/** Pengurus komisi & bidang periode 2025–2027 (data dari sekretariat). */
return new class extends Migration
{
    private const PENGURUS = [
        'Komisi Wanita' => [['ketua', 'Ibu Citra Kristiani'], ['anggota', 'Ibu Novita Linawati'], ['anggota', 'Ibu Fesianawati'],
            ['anggota', 'Ibu Rut Lindawati'], ['anggota', 'Ibu Widayati'], ['anggota', 'Ibu Supriasih']],
        'Komisi Pria' => [['ketua', 'Pak Jemiran'], ['anggota', 'Pak Slamet Santoso'], ['anggota', 'Pak Leo']],
        'Komisi Anak' => [['ketua', 'Sari'], ['anggota', 'Rahel'], ['anggota', 'Yunita Susanti'], ['anggota', 'Reza'], ['anggota', 'Yosua L']],
        'Misi dan PI' => [['ketua', 'Pnt. Anis Wahyuningsih', 'Ketua langsung oleh penatua'], ['anggota', 'Ibu Elsye']],
        'Komisi Usia Indah (Lansia)' => [['ketua', 'Pak Ali Basah Fadjar Ain'], ['anggota', 'Bu Diyah Wahyuning Ari'], ['anggota', 'Pak Teguh']],
        'Diakonia' => [['anggota', 'Ibu Septalisa'], ['anggota', 'Bpk. Dwi Wijanarko'], ['anggota', 'Ibu May Hwa'], ['anggota', 'Sari']],
        'JoshComm' => [['ketua', 'Ephafras'], ['anggota', 'Felix'], ['anggota', 'Gadis Sekar Amadis'], ['anggota', 'Daniel A'], ['anggota', 'Yesaya']],
    ];

    public function up(): void
    {
        $periode = OrgPeriod::where('name', '2025–2027')->first();
        if (! $periode) {
            return;
        }
        $matcher = app(PelayanMatcher::class);

        foreach (self::PENGURUS as $unitNama => $orang) {
            $unit = OrgUnit::where('org_period_id', $periode->id)->where('name', $unitNama)->first();
            if (! $unit) {
                continue;
            }
            $urut = (int) $unit->positions()->max('sort') + 1;
            foreach ($orang as $o) {
                [$role, $nama] = $o;
                if ($unit->positions()->where('role', $role)->where('name', $nama)->exists()) {
                    continue; // sudah ada (migrasi diulang)
                }
                $unit->positions()->create([
                    'role' => $role, 'name' => $nama, 'note' => $o[2] ?? null, 'sort' => $urut++,
                    'member_id' => $matcher->cocokkan($matcher->urai($nama))?->id,
                ]);
            }
        }
    }

    public function down(): void
    {
        $periode = OrgPeriod::where('name', '2025–2027')->first();
        foreach (self::PENGURUS as $unitNama => $orang) {
            $unit = $periode ? OrgUnit::where('org_period_id', $periode->id)->where('name', $unitNama)->first() : null;
            foreach ($orang as $o) {
                $unit?->positions()->where('role', $o[0])->where('name', $o[1])->delete();
            }
        }
    }
};
