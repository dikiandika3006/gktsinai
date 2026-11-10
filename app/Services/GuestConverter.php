<?php

namespace App\Services;

use App\Models\Family;
use App\Models\Guest;
use App\Models\Member;
use App\Models\MemberEvent;
use Illuminate\Support\Facades\DB;

/**
 * TF-06: konversi tamu menjadi jemaat tanpa input ulang.
 */
class GuestConverter
{
    /**
     * @param  array{family_id?: ?int, new_family_address?: ?string, region_id?: ?int, family_relation?: ?string,
     *               gender?: ?string, birth_date?: ?string, membership_status?: string, atestasi_masuk?: bool}  $data
     */
    public function convert(Guest $guest, array $data): Member
    {
        return DB::transaction(function () use ($guest, $data) {
            $familyId = $data['family_id'] ?? null;
            $kepalaBaru = false;
            if (! $familyId) {
                $family = Family::create([
                    'address' => $data['new_family_address'] ?? $guest->city,
                    'region_id' => $data['region_id'] ?? null,
                    'phone' => $guest->phone,
                ]);
                $familyId = $family->id;
                $kepalaBaru = true;
            }

            $member = Member::create([
                'name' => $guest->name,
                'phone' => $guest->phone,
                'gender' => $data['gender'] ?? null,
                'birth_date' => $data['birth_date'] ?? null,
                'family_id' => $familyId,
                'family_relation' => $kepalaBaru ? 'kepala' : ($data['family_relation'] ?? 'lainnya'),
                'membership_status' => $data['membership_status'] ?? 'simpatisan',
                'activity_status' => 'aktif',
                'joined_at' => today(),
                'notes' => trim('Konversi dari tamu (kunjungan pertama '.$guest->first_visit_at?->format('d/m/Y').'). '
                    .($guest->origin_church ? 'Gereja asal: '.$guest->origin_church : '')),
            ]);

            if ($kepalaBaru) {
                Family::whereKey($familyId)->update(['head_member_id' => $member->id]);
            }

            if (! empty($data['atestasi_masuk'])) {
                MemberEvent::create([
                    'member_id' => $member->id,
                    'type' => 'atestasi_masuk',
                    'date' => today(),
                    'church' => $guest->origin_church,
                    'data_status' => 'lengkap',
                ]);
            }

            // Riwayat kehadiran tamu dipindah menjadi kehadiran jemaat.
            DB::table('attendances')->where('guest_id', $guest->id)->update(['member_id' => $member->id, 'guest_id' => null]);

            $guest->update([
                'fu_stage' => 'jemaat',
                'converted_member_id' => $member->id,
                'converted_at' => now(),
            ]);

            return $member;
        });
    }
}
