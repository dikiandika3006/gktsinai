<?php

namespace App\Http\Controllers;

use App\Models\ActivitySession;
use App\Models\ActivityType;
use App\Models\Attendance;
use App\Models\Member;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

/** KH-03 / KH-05: absensi dari HP petugas. */
class AbsenController extends Controller
{
    private function authorizeStaff(): void
    {
        abort_unless(auth()->user()?->kelolaKehadiran(), 403, 'Anda tidak memiliki akses absensi.');
    }

    private function allowedTypes()
    {
        $u = auth()->user();

        return ActivityType::query()->where('is_active', true)
            ->when($u->hasRole('koordinator_komisi') && ! $u->peran('super_admin', 'sekretariat', 'penyambut'),
                fn ($q) => $q->whereIn('commission_id', $u->komisiDikoordinasi()))
            ->orderByDesc('is_general_worship')->orderBy('name')->get();
    }

    private function authorizeSession(ActivitySession $session): void
    {
        $this->authorizeStaff();
        abort_unless($this->allowedTypes()->contains('id', $session->activity_type_id), 403);
    }

    /** Layar absensi (React): pilih tanggal, pilih ibadah, lalu absen per nama. */
    public function index(Request $request, ?ActivitySession $preset = null)
    {
        $this->authorizeStaff();
        $types = $this->allowedTypes();
        abort_if($types->isEmpty(), 403, 'Belum ada jenis ibadah yang bisa Anda absen.');
        $user = $request->user();

        return view('absen.app', ['config' => [
            'types' => $types->map(fn ($t) => [
                'id' => $t->id, 'name' => $t->name, 'count_only' => (bool) $t->count_only, 'umum' => (bool) $t->is_general_worship,
            ])->values(),
            'kebaktian' => array_keys(config('sij.kebaktian', [])),
            'today' => today()->toDateString(),
            'preset' => $preset ? ['type' => $preset->activity_type_id, 'date' => $preset->date->toDateString(), 'kebaktian' => $preset->kebaktian] : null,
            'user' => $user->name,
            'adminUrl' => $user->hanyaAbsensi() ? null : url('/admin'),
            'openUrl' => route('absen.open'),
            'logoutUrl' => route('absen.logout'),
            'logo' => asset('logo-gkt.png'),
            'church' => config('sij.church_name'),
        ]]);
    }

    /** Buka (atau buat otomatis) sesi untuk ibadah + tanggal (+ kebaktian KU I/KU II/Gabungan). */
    public function open(Request $request)
    {
        $this->authorizeStaff();
        $data = $request->validate([
            'activity_type_id' => 'required|integer|exists:activity_types,id',
            'date' => 'required|date',
            'kebaktian' => ['nullable', 'string', Rule::in(array_keys(config('sij.kebaktian', [])))],
        ]);
        $type = $this->allowedTypes()->firstWhere('id', (int) $data['activity_type_id']);
        abort_unless($type, 403);
        $kebaktian = $type->is_general_worship ? ($data['kebaktian'] ?? null) : null;
        [$mulai, $selesai] = $kebaktian ? config("sij.kebaktian.{$kebaktian}") : ['07:00', '09:00'];

        $session = ActivitySession::query()->where('activity_type_id', $type->id)->whereDate('date', $data['date'])
            ->when($kebaktian, fn ($q) => $q->where('kebaktian', $kebaktian), fn ($q) => $q->whereNull('kebaktian'))
            ->orderBy('id')->first()
            ?? ActivitySession::create([
                'activity_type_id' => $type->id,
                'date' => $data['date'],
                'kebaktian' => $kebaktian,
                'start_time' => $mulai,
                'end_time' => $selesai,
                'location' => 'Gedung Gereja',
            ]);

        return response()->json([
            'id' => $session->id,
            'count_only' => $session->isCountOnly(),
            'manual_count' => $session->manual_count,
            'data_url' => route('absen.data', $session),
            'sync_url' => route('absen.sync', $session),
            'count_url' => route('absen.count', $session),
            'guest_remove_url' => route('absen.remove-guest', [$session, 0]),
            'qr_url' => route('sessions.qr', $session),
            'guest_url' => route('tamu.create', ['sesi' => $session->qr_token]),
        ]);
    }

    public function logout(Request $request)
    {
        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('filament.admin.auth.login');
    }

    public function show(Request $request, ActivitySession $session)
    {
        $this->authorizeSession($session);

        return $this->index($request, $session);
    }

    /** Daftar jemaat untuk dicentang (disimpan juga di HP agar bisa offline). */
    public function data(ActivitySession $session)
    {
        $this->authorizeSession($session);
        $members = $session->expectedMembersQuery()
            ->with(['family:id,code,head_member_id,region_id', 'family.region:id,name', 'family.head:id,name'])
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'alias', 'family_id', 'activity_status'])
            ->map(fn (Member $m) => [
                'id' => $m->id, 'c' => $m->code, 'n' => $m->name, 'a' => $m->alias,
                'f' => $m->family_id, 'fk' => $m->family?->code, 'fh' => $m->family?->head?->name,
                'r' => $m->family?->region?->name, 'p' => $m->activity_status === 'pasif',
            ]);

        return response()->json([
            'members' => $members,
            // {member_id: waktu check-in}, agar daftar hadir bisa diurutkan dari yang terbaru.
            'present' => $session->attendances()->whereNotNull('member_id')->get(['member_id', 'checked_in_at'])
                ->mapWithKeys(fn (Attendance $a) => [$a->member_id => $a->checked_in_at?->toIso8601String()]),
            'guests' => $session->attendances()->whereNotNull('guest_id')->with('guest:id,name')->get()
                ->map(fn (Attendance $a) => ['id' => $a->id, 'name' => $a->guest?->name, 'at' => $a->checked_in_at?->toIso8601String()])->values(),
            'others' => $this->otherKebaktian($session),
        ]);
    }

    /**
     * Kehadiran kebaktian lain (KU I / KU II / Gabungan) pada ibadah & tanggal yang sama:
     * ditampilkan di daftar hadir, dan jemaat yang sudah hadir di sana tidak diabsen dua kali.
     */
    private function otherKebaktian(ActivitySession $session): array
    {
        if (! $session->kebaktian) {
            return [];
        }
        $urutan = array_flip(array_keys(config('sij.kebaktian', [])));

        return ActivitySession::query()
            ->where('activity_type_id', $session->activity_type_id)
            ->whereDate('date', $session->date)
            ->whereNotNull('kebaktian')
            ->whereKeyNot($session->id)
            ->with(['attendances' => fn ($q) => $q->with('guest:id,name')])
            ->get()
            ->sortBy(fn (ActivitySession $s) => $urutan[$s->kebaktian] ?? 99)
            ->map(fn (ActivitySession $s) => [
                'kebaktian' => $s->kebaktian,
                'present' => $s->attendances->whereNotNull('member_id')
                    ->mapWithKeys(fn (Attendance $a) => [$a->member_id => $a->checked_in_at?->toIso8601String()]),
                'guests' => $s->attendances->whereNotNull('guest_id')
                    ->map(fn (Attendance $a) => ['name' => $a->guest?->name, 'at' => $a->checked_in_at?->toIso8601String()])->values(),
            ])->values()->all();
    }

    /** Sinkronisasi antrean centang dari HP: [{member_id, at, undo}] */
    public function sync(Request $request, ActivitySession $session)
    {
        $this->authorizeSession($session);
        $items = $request->validate([
            'items' => 'required|array|max:3000',
            'items.*.member_id' => 'required|integer',
            'items.*.at' => 'nullable|date',
            'items.*.undo' => 'nullable|boolean',
        ])['items'];

        $valid = Member::whereIn('id', collect($items)->pluck('member_id'))->pluck('id')->flip();
        // Jemaat yang sudah hadir di kebaktian lain (KU I/KU II/Gabungan) hari itu cukup diabsen sekali.
        $sudahDiLain = $session->kebaktian
            ? Attendance::whereNotNull('member_id')->whereHas('session', fn ($q) => $q
                ->where('activity_type_id', $session->activity_type_id)->whereDate('date', $session->date)
                ->whereNotNull('kebaktian')->whereKeyNot($session->id))->pluck('member_id')->flip()
            : collect();
        $n = 0;
        foreach ($items as $it) {
            if (! $valid->has($it['member_id'])) {
                continue;
            }
            if (empty($it['undo']) && $sudahDiLain->has($it['member_id'])) {
                $n++;

                continue;
            }
            if (! empty($it['undo'])) {
                Attendance::where('activity_session_id', $session->id)->where('member_id', $it['member_id'])->delete();
            } else {
                Attendance::firstOrCreate(
                    ['activity_session_id' => $session->id, 'member_id' => $it['member_id']],
                    ['checked_in_at' => $it['at'] ?? now(), 'method' => 'petugas', 'user_id' => auth()->id()]
                );
            }
            $n++;
        }
        Cache::forget('sij.attention_count');

        return response()->json(['ok' => true, 'synced' => $n, 'total' => $session->attendances()->count()]);
    }

    /**
     * Hapus tamu yang salah catat dari sesi. Data tamu ikut dihapus bila belum punya
     * riwayat lain (kunjungan lain, follow-up, atau sudah jadi jemaat).
     */
    public function removeGuest(ActivitySession $session, Attendance $attendance)
    {
        $this->authorizeSession($session);
        abort_unless($attendance->activity_session_id === $session->id && $attendance->guest_id, 404);

        $guest = $attendance->guest;
        $attendance->delete();
        $guestDeleted = false;
        if ($guest && ! $guest->converted_member_id && ! $guest->attendances()->exists() && ! $guest->followups()->exists()) {
            $guest->delete();
            $guestDeleted = true;
        }

        return response()->json(['ok' => true, 'guest_deleted' => $guestDeleted]);
    }

    /** KH-05: jumlah total hadir. */
    public function count(Request $request, ActivitySession $session)
    {
        $this->authorizeSession($session);
        $data = $request->validate(['manual_count' => 'required|integer|min:0|max:100000']);
        $session->update($data);

        return $request->expectsJson() ? response()->json(['ok' => true]) : back()->with('status', 'Jumlah hadir disimpan.');
    }

    /** QR check-in mandiri untuk ditampilkan di pintu/proyektor. */
    public function qr(ActivitySession $session)
    {
        abort_unless(auth()->user()->kelolaKehadiran() || auth()->user()->lihatSemuaJemaat(), 403);
        $url = route('checkin.self', $session->qr_token);
        $svg = (new QRCode(new QROptions(['outputBase64' => true, 'scale' => 10])))->render($url);
        [$mulai, $selesai] = $session->checkinWindow();

        return view('absen.qr', compact('session', 'url', 'svg', 'mulai', 'selesai'));
    }
}
