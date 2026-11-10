<?php

namespace App\Http\Controllers;

use App\Models\ActivitySession;
use App\Models\Family;
use App\Models\Guest;
use App\Models\Member;
use App\Models\MemberEvent;
use App\Models\Visit;
use App\Services\AttendanceService;
use App\Services\GrowthService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PdfController extends Controller
{
    /** DJ-16: buku induk per jemaat. */
    public function member(Member $member)
    {
        $this->authorize('view', $member);
        $member->load(['family.region', 'family.head', 'events', 'commissions']);

        return Pdf::loadView('pdf.member', ['members' => collect([$member]), 'kontak' => auth()->user()->lihatKontak()])
            ->stream('buku-induk-'.$member->code.'.pdf');
    }

    /** DJ-16: buku induk per KK. */
    public function family(Family $family)
    {
        $this->authorize('view', $family);
        $family->load(['region', 'head', 'members.events', 'members.commissions']);

        return Pdf::loadView('pdf.member', [
            'family' => $family,
            'members' => $family->members->sortBy('birth_date'),
            'kontak' => auth()->user()->lihatKontak(),
        ])->stream('buku-induk-'.$family->code.'.pdf');
    }

    /** PJ-02: rekap wilayah x status. */
    public function rekap(GrowthService $svc)
    {
        abort_unless(auth()->user()->peran('super_admin', 'sekretariat', 'pendeta'), 403);

        return Pdf::loadView('pdf.rekap', [
            'matrix' => $svc->regionMatrix(),
            'columns' => GrowthService::KOLOM_STATUS,
            'summary' => $svc->statusSummary(),
        ])->setPaper('a4', 'landscape')->stream('rekap-wilayah-status.pdf');
    }

    /** PJ-06: laporan bulanan rapat majelis. */
    public function monthly(Request $request, GrowthService $svc, AttendanceService $att)
    {
        abort_unless(auth()->user()->peran('super_admin', 'sekretariat', 'pendeta'), 403);
        $bulan = Carbon::createFromFormat('Y-m', $request->query('bulan', now()->subMonth()->format('Y-m')))->startOfMonth();
        $akhir = $bulan->copy()->endOfMonth();

        $mutasi = MemberEvent::with('member')->whereBetween('date', [$bulan, $akhir])
            ->whereIn('type', ['baptis', 'sidi', 'nikah', 'atestasi_masuk', 'atestasi_keluar', 'wafat'])->orderBy('date')->get();
        $sesi = ActivitySession::with('activityType')->withCount('attendances')->whereBetween('date', [$bulan, $akhir])->orderBy('date')->get();
        $tamu = Guest::whereBetween('first_visit_at', [$bulan, $akhir])->get();
        $konversi = Guest::whereBetween('converted_at', [$bulan, $akhir])->count();
        $visit = Visit::with('family.head')->where('status', 'selesai')->whereBetween('realized_date', [$bulan, $akhir])->get();

        return Pdf::loadView('pdf.monthly', [
            'bulan' => $bulan,
            'totals' => $svc->totals(),
            'summary' => $svc->statusSummary(),
            'matrix' => $svc->regionMatrix(),
            'columns' => GrowthService::KOLOM_STATUS,
            'mutasi' => $mutasi,
            'sesi' => $sesi,
            'tamu' => $tamu,
            'konversi' => $konversi,
            'visit' => $visit,
            'perhatian' => $att->attentionList()->count(),
        ])->stream('laporan-majelis-'.$bulan->format('Y-m').'.pdf');
    }
}
