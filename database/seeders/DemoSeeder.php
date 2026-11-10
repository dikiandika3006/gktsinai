<?php

namespace Database\Seeders;

use App\Models\ActivitySession;
use App\Models\ActivityType;
use App\Models\Attendance;
use App\Models\Commission;
use App\Models\Family;
use App\Models\Guest;
use App\Models\Member;
use App\Models\MemberEvent;
use App\Models\Region;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitReport;
use Faker\Factory;
use Illuminate\Database\Seeder;

/**
 * Data contoh untuk mencoba sistem (BUKAN data jemaat asli).
 * Jalankan: php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        config(['sij.geocode_on_save' => false]);
        $fk = Factory::create('id_ID');
        $fk->seed(2026);
        $regions = Region::all();

        $staff = [
            ['Sekretariat Gereja', 'sekretariat@gktsinai.test', 'sekretariat'],
            ['Pdt. Contoh', 'pendeta@gktsinai.test', 'pendeta'],
            ['Majelis Contoh', 'majelis@gktsinai.test', 'majelis'],
            ['Tim Visitasi 1', 'visitasi@gktsinai.test', 'tim_visitasi'],
            ['Penyambut 1', 'penyambut@gktsinai.test', 'penyambut'],
        ];
        foreach ($staff as [$n, $e, $r]) {
            User::firstOrCreate(['email' => $e], ['name' => $n, 'password' => 'password'])->assignRole($r);
        }

        $center = config('sij.map_center');
        for ($k = 0; $k < 40; $k++) {
            $region = $regions->random();
            $family = Family::create([
                'address' => 'Jl. '.$fk->streetName().' No. '.$fk->buildingNumber(),
                'region_id' => $k % 9 === 0 ? null : $region->id,
                'phone' => $fk->boolean(60) ? '08'.$fk->numerify('##########') : null,
                'latitude' => $center[0] + $fk->randomFloat(5, -0.03, 0.03),
                'longitude' => $center[1] + $fk->randomFloat(5, -0.04, 0.04),
                'geocode_status' => $fk->randomElement(['tepat', 'tepat', 'perkiraan', 'manual']),
                'geocoded_at' => now(),
            ]);
            $married = $fk->dateTimeBetween('-40 years', '-3 years');
            $husband = Member::create([
                'name' => $fk->firstNameMale().' '.$fk->lastName(), 'gender' => 'L', 'family_id' => $family->id, 'family_relation' => 'kepala',
                'birth_date' => $fk->dateTimeBetween('-80 years', '-28 years'), 'membership_status' => 'sidi',
                'activity_status' => $fk->boolean(85) ? 'aktif' : 'pasif', 'marriage_date' => $married, 'phone' => $fk->boolean(40) ? '08'.$fk->numerify('##########') : null,
            ]);
            $family->update(['head_member_id' => $husband->id]);
            Member::create([
                'name' => $fk->firstNameFemale().' '.$fk->lastName(), 'gender' => 'P', 'family_id' => $family->id, 'family_relation' => 'istri',
                'birth_date' => $fk->dateTimeBetween('-78 years', '-26 years'), 'membership_status' => $fk->randomElement(['sidi', 'anggota']),
                'activity_status' => 'aktif', 'marriage_date' => $married,
            ]);
            foreach (range(1, $fk->numberBetween(0, 3)) as $i) {
                $g = $fk->randomElement(['L', 'P']);
                Member::create([
                    'name' => ($g === 'L' ? $fk->firstNameMale() : $fk->firstNameFemale()).' '.$fk->lastName(), 'gender' => $g,
                    'family_id' => $family->id, 'family_relation' => 'anak',
                    'birth_date' => $fk->dateTimeBetween('-25 years', '-2 years'), 'membership_status' => 'simpatisan', 'activity_status' => 'aktif',
                ]);
            }
        }

        // Riwayat mutasi
        Member::inRandomOrder()->limit(25)->get()->each(fn ($m) => MemberEvent::create([
            'member_id' => $m->id, 'type' => $fk->randomElement(['baptis', 'sidi', 'atestasi_masuk']),
            'date' => $fk->dateTimeBetween('-14 months', 'now'), 'church' => 'GKT Jemaat Sinai Batu', 'data_status' => 'lengkap',
        ]));
        Member::where('membership_status', 'sidi')->inRandomOrder()->limit(3)->get()
            ->each(fn ($m) => MemberEvent::create(['member_id' => $m->id, 'type' => 'atestasi_keluar', 'date' => $fk->dateTimeBetween('-10 months', 'now'), 'church' => 'GKT Malang']));

        // Komisi dari usulan
        Commission::all()->each(function (Commission $c) {
            $ids = Member::tercatat()->get()->filter(fn ($m) => $c->matches($m))->pluck('id');
            $c->members()->syncWithoutDetaching($ids->mapWithKeys(fn ($id) => [$id => ['joined_at' => today(), 'is_active' => true]])->all());
        });

        // Kehadiran 8 minggu terakhir
        $umum = ActivityType::where('is_general_worship', true)->first();
        $aktif = Member::aktif()->pluck('id');
        $jarang = $aktif->random(8);
        for ($w = 7; $w >= 0; $w--) {
            $s = ActivitySession::create(['activity_type_id' => $umum->id, 'date' => today()->subWeeks($w)->startOfWeek()->addDays(6)->min(today()),
                'start_time' => '07:00', 'end_time' => '09:00', 'location' => 'Gedung Gereja']);
            $aktif->diff($w < 3 ? $jarang : [])->random((int) ($aktif->count() * 0.7))
                ->each(fn ($id) => Attendance::create(['activity_session_id' => $s->id, 'member_id' => $id, 'checked_in_at' => $s->date->copy()->setTime(7, rand(0, 30)), 'method' => rand(0, 3) ? 'petugas' : 'qr']));
        }
        $sm = ActivityType::whereHas('commission', fn ($q) => $q->where('count_only', true))->first();
        if ($sm) {
            for ($w = 3; $w >= 0; $w--) {
                ActivitySession::create(['activity_type_id' => $sm->id, 'date' => today()->subWeeks($w)->startOfWeek()->addDays(6)->min(today()), 'start_time' => '08:00', 'manual_count' => rand(18, 30)]);
            }
        }

        // Tamu
        $sek = User::where('email', 'sekretariat@gktsinai.test')->first();
        foreach (range(1, 8) as $i) {
            Guest::create([
                'name' => $fk->name(), 'phone' => '08'.$fk->numerify('##########'), 'city' => $fk->city(), 'came_with' => $fk->randomElement(['Teman', 'Keluarga', 'Sendiri']),
                'source' => $fk->randomElement(['keluarga', 'media_sosial', 'lewat', 'undangan']), 'consent_contact' => $fk->boolean(80),
                'first_visit_at' => today()->subDays(rand(1, 60)), 'fu_due_at' => now()->subDays(rand(-2, 50)),
                'fu_stage' => $fk->randomElement(['baru', 'baru', 'dihubungi', 'datang_lagi', 'rutin']), 'pic_id' => $i % 2 ? $sek?->id : null,
            ]);
        }

        // Visitasi
        $tim = User::where('email', 'visitasi@gktsinai.test')->first();
        Family::inRandomOrder()->limit(12)->get()->each(function ($f, $i) use ($tim, $fk) {
            $done = $i < 8;
            $v = Visit::create(['family_id' => $f->id, 'type' => $fk->randomElement(['rutin', 'sakit', 'ulang_tahun']),
                'planned_date' => $done ? today()->subDays(rand(5, 200)) : today()->addDays(rand(1, 20)), 'status' => $done ? 'selesai' : 'rencana']);
            $v->team()->attach($tim);
            if ($done) {
                $v->update(['realized_date' => $v->planned_date]);
                VisitReport::create(['visit_id' => $v->id, 'attendees' => 'Seluruh keluarga', 'conversation' => 'Bercerita tentang pekerjaan dan kesehatan keluarga.',
                    'prayer_points' => 'Kesehatan dan pekerjaan.', 'follow_up' => '-', 'is_confidential' => $i === 0, 'user_id' => $tim->id]);
            }
        });
    }
}
