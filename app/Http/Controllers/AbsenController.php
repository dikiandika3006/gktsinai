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

    /** Satu layar absensi: pilih tanggal & ibadah, cari nama, klik. */
    public function index(Request $request, ?ActivitySession $preset = null)
    {
        $this->authorizeStaff();
        $types = $this->allowedTypes();
        abort_if($types->isEmpty(), 403, 'Belum ada jenis ibadah yang bisa Anda absen.');

        // Default: Ibadah Umum hari ini (atau Minggu terdekat yang sudah lewat).
        $defaultDate = today()->isSunday() ? today() : today()->previous(\Carbon\Carbon::SUNDAY);

        return view('absen.index', [
            'types' => $types->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'count_only' => (bool) $t->count_only])->values(),
            'presetType' => $preset?->activity_type_id ?? $request->integer('type') ?: $types->first()->id,
            'presetDate' => $preset?->date?->toDateString() ?? $request->query('date', $defaultDate->toDateString()),
        ]);
    }

    /** Buka (atau buat otomatis) sesi untuk ibadah + tanggal terpilih. */
    public function open(Request $request)
    {
        $this->authorizeStaff();
        $data = $request->validate([
            'activity_type_id' => 'required|integer|exists:activity_types,id',
            'date' => 'required|date',
        ]);
        $type = $this->allowedTypes()->firstWhere('id', (int) $data['activity_type_id']);
        abort_unless($type, 403);

        $session = ActivitySession::query()->where('activity_type_id', $type->id)->whereDate('date', $data['date'])->orderBy('id')->first()
            ?? ActivitySession::create([
                'activity_type_id' => $type->id,
                'date' => $data['date'],
                'start_time' => '07:00',
                'end_time' => '09:00',
                'location' => 'Gedung Gereja',
            ]);

        return response()->json([
            'id' => $session->id,
            'count_only' => $session->isCountOnly(),
            'manual_count' => $session->manual_count,
            'data_url' => route('absen.data', $session),
            'sync_url' => route('absen.sync', $session),
            'count_url' => route('absen.count', $session),
            'qr_url' => route('sessions.qr', $session),
            'guest_url' => route('tamu.create', ['sesi' => $session->qr_token]),
        ]);
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
            'present' => $session->attendances()->whereNotNull('member_id')->pluck('member_id'),
            'guests' => $session->attendances()->whereNotNull('guest_id')->with('guest:id,name')->get()->pluck('guest.name'),
        ]);
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
        $n = 0;
        foreach ($items as $it) {
            if (! $valid->has($it['member_id'])) {
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
