<?php

namespace App\Http\Controllers;

use App\Models\ActivitySession;
use App\Models\Attendance;
use App\Models\Guest;
use App\Models\User;
use App\Notifications\NewGuestNotification;
use App\Support\Opsi;
use App\Support\Telepon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

/** TF-01 / KH-06: form tamu ramah HP (dari petugas penyambut atau halaman check-in). */
class GuestFormController extends Controller
{
    public function create(Request $request)
    {
        $session = $request->query('sesi') ? ActivitySession::with('activityType')->where('qr_token', $request->query('sesi'))->first() : null;

        return view('tamu.create', ['sumber' => Opsi::SUMBER_INFO, 'session' => $session]);
    }

    public function store(Request $request)
    {
        // Honeypot anti-spam.
        if ($request->filled('website')) {
            return redirect()->route('tamu.thanks');
        }
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'city' => 'nullable|string|max:255',
            'came_with' => 'nullable|string|max:255',
            'source' => 'nullable|in:'.implode(',', array_keys(Opsi::SUMBER_INFO)),
            'origin_church' => 'nullable|string|max:255',
            'consent_contact' => 'nullable|boolean',
            'sesi' => 'nullable|string|max:64',
        ]);

        // Tamu yang sama (nomor HP sama) datang lagi: catat sebagai kunjungan berikutnya.
        $hp = Telepon::e164($data['phone'] ?? null);
        $guest = $hp ? Guest::where('phone', $hp)->whereNull('converted_member_id')->first() : null;
        $baru = ! $guest;
        if ($baru) {
            $guest = Guest::create(collect($data)->except('sesi')->all() + ['consent_contact' => (bool) ($data['consent_contact'] ?? false)]);
        }

        $session = ! empty($data['sesi']) ? ActivitySession::where('qr_token', $data['sesi'])->first()
            : ActivitySession::whereDate('date', today())->whereHas('activityType', fn ($q) => $q->where('is_general_worship', true))->first();
        if ($session) {
            Attendance::firstOrCreate(['activity_session_id' => $session->id, 'guest_id' => $guest->id],
                ['checked_in_at' => now(), 'method' => auth()->check() ? 'petugas' : 'qr', 'user_id' => auth()->id()]);
        }

        if ($baru) {
            $penerima = User::role(['sekretariat', 'pendeta'])->where('is_active', true)->get();
            Notification::send($penerima, new NewGuestNotification($guest));
        }

        return redirect()->route('tamu.thanks', ['nama' => $guest->name, 'lagi' => $baru ? null : 1, 'sesi' => $session?->qr_token]);
    }

    public function thanks(Request $request)
    {
        $session = $request->query('sesi') ? ActivitySession::where('qr_token', $request->query('sesi'))->first() : null;

        return view('tamu.thanks', ['nama' => $request->query('nama'), 'lagi' => $request->boolean('lagi'), 'session' => $session]);
    }
}
