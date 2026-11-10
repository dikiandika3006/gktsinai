<?php

namespace Database\Seeders;

use App\Models\ActivityType;
use App\Models\Commission;
use App\Models\Region;
use App\Models\User;
use App\Support\Opsi;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (array_keys(Opsi::PERAN) as $peran) {
            Role::findOrCreate($peran, 'web');
        }

        // Master Komisi (DJ-20). Batas umur bisa diubah admin kapan saja.
        $komisi = [
            ['name' => 'Komisi Wanita', 'min_age' => 18, 'max_age' => null, 'gender' => 'P', 'schedule' => 'Rabu, 17.00'],
            ['name' => 'Komisi Pria', 'min_age' => 18, 'max_age' => null, 'gender' => 'L', 'schedule' => 'Jumat, 19.00'],
            ['name' => 'Komisi Lansia', 'min_age' => 60, 'max_age' => null, 'gender' => null, 'schedule' => 'Kamis, 10.00'],
            ['name' => 'Joshcom', 'min_age' => 13, 'max_age' => 30, 'gender' => null, 'schedule' => 'Sabtu, 18.00'],
            ['name' => 'Komisi Sekolah Minggu', 'min_age' => 0, 'max_age' => 12, 'gender' => null, 'schedule' => 'Minggu, 08.00', 'count_only' => true],
        ];
        foreach ($komisi as $k) {
            $c = Commission::firstOrCreate(['name' => $k['name']], $k);
            ActivityType::firstOrCreate(
                ['commission_id' => $c->id],
                ['name' => 'Ibadah '.str_replace('Komisi ', '', $c->name), 'count_only' => (bool) $c->count_only]
            );
        }

        // KH-01
        ActivityType::firstOrCreate(['is_general_worship' => true], ['name' => 'Ibadah Umum']);

        // Wilayah awal (bisa disesuaikan setelah peta persebaran ditinjau).
        $warna = ['#2563eb', '#dc2626', '#16a34a', '#d97706', '#7c3aed', '#db2777', '#0891b2', '#65a30d'];
        foreach (['Pesanggrahan', 'Sisir', 'Ngaglik', 'Songgokerto', 'Junrejo', 'Wukir', 'Pujon', 'Malang'] as $i => $w) {
            Region::firstOrCreate(['name' => $w], ['map_color' => $warna[$i]]);
        }

        $admin = User::firstOrCreate(
            ['username' => env('SIJ_ADMIN_USERNAME', 'admin')],
            ['name' => 'Super Admin', 'email' => env('SIJ_ADMIN_EMAIL', 'admin@gktsinai.test'), 'password' => env('SIJ_ADMIN_PASSWORD', 'password')]
        );
        $admin->assignRole('super_admin');
    }
}
