<?php

namespace App\Http\Controllers;

use App\Models\ActivitySession;
use App\Models\Attendance;
use App\Models\Member;
use Illuminate\Http\Request;

/**
 * KH-04: jemaat memindai QR sesi, cari nama, konfirmasi.
 * Halaman publik: tidak menampilkan HP/alamat.
 */
class SelfCheckinController extends Controller
{
    private function session(string $token): ActivitySession
    {
        return ActivitySession::with('activityType')->where('qr_token', $token)->firstOrFail();
    }

    public function show(string $token)
    {
        $session = $this->session($token);

        return view('checkin.self', ['session' => $session, 'open' => $session->isCheckinOpen() && ! $session->isCountOnly()]);
    }

    public function search(Request $request, string $token)
    {
        $session = $this->session($token);
        abort_unless($session->isCheckinOpen(), 403, 'Check-in sudah ditutup.');
        $q = trim((string) $request->query('q'));
        if (mb_strlen($q) < 3) {
            return response()->json([]);
        }
        $hadir = $session->attendances()->pluck('member_id')->flip();

        return response()->json($session->expectedMembersQuery()
            ->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('alias', 'like', "%{$q}%"))
            ->with('family.region:id,name')->orderBy('name')->limit(10)->get()
            ->map(fn (Member $m) => [
                'id' => $m->id,
                'name' => $m->name,
                'info' => trim(($m->family?->region?->name ?? '').($m->birth_date ? ' · lahir '.$m->birth_date->format('Y') : '')),
                'done' => $hadir->has($m->id),
            ]));
    }

    public function store(Request $request, string $token)
    {
        $session = $this->session($token);
        abort_unless($session->isCheckinOpen(), 403, 'Check-in sudah ditutup.');
        $data = $request->validate(['member_id' => 'required|integer']);
        $member = $session->expectedMembersQuery()->whereKey($data['member_id'])->firstOrFail();

        Attendance::firstOrCreate(
            ['activity_session_id' => $session->id, 'member_id' => $member->id],
            ['checked_in_at' => now(), 'method' => 'qr']
        );

        return response()->json(['ok' => true, 'name' => $member->name]);
    }
}
