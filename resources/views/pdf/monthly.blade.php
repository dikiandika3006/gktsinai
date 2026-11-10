@php use App\Support\Opsi; @endphp
@extends('pdf.layout')
@section('title', 'Laporan Bulanan')
@section('subtitle', 'Laporan Bulanan Rapat Majelis - '.$bulan->translatedFormat('F Y'))
@section('content')
    <h2>1. Ringkasan jemaat (per {{ now()->format('d/m/Y') }})</h2>
    <table class="kv">
        <tr><td>Jemaat tercatat</td><td><b>{{ $totals['tercatat'] }}</b> orang dalam {{ $totals['kk'] }} KK</td></tr>
        <tr><td>Anggota resmi</td><td>{{ $totals['anggota'] }}</td></tr>
        <tr><td>Aktif beribadah</td><td>{{ $totals['aktif'] }}</td></tr>
        <tr><td>Daftar perhatian (absen 3x)</td><td>{{ $perhatian }} orang</td></tr>
    </table>
    <table style="margin-top:6px">
        <tr>@foreach ($summary as $k => $v)<th class="num">{{ $k }}</th>@endforeach</tr>
        <tr>@foreach ($summary as $v)<td class="num">{{ $v }}</td>@endforeach</tr>
    </table>

    <h2>2. Mutasi bulan ini</h2>
    <table>
        <tr><th>Tanggal</th><th>Jenis</th><th>Nama</th><th>Gereja / keterangan</th></tr>
        @forelse ($mutasi as $e)
            <tr><td>{{ $e->date?->format('d/m/Y') }}</td><td>{{ Opsi::label(Opsi::JENIS_EVENT, $e->type) }}</td><td>{{ $e->member?->name }}</td><td>{{ $e->church ?? $e->place }}</td></tr>
        @empty
            <tr><td colspan="4" class="muted">Tidak ada mutasi.</td></tr>
        @endforelse
    </table>

    <h2>3. Kehadiran</h2>
    <table>
        <tr><th>Tanggal</th><th>Kegiatan</th><th class="num">Hadir</th></tr>
        @forelse ($sesi as $s)
            <tr><td>{{ $s->date->format('d/m/Y') }}</td><td>{{ $s->activityType?->name }}</td><td class="num">{{ $s->manual_count ?? $s->attendances_count }}</td></tr>
        @empty
            <tr><td colspan="3" class="muted">Belum ada sesi tercatat.</td></tr>
        @endforelse
    </table>

    <h2>4. Tamu &amp; follow-up</h2>
    <table class="kv">
        <tr><td>Tamu baru</td><td>{{ $tamu->count() }}</td></tr>
        <tr><td>Sudah di-follow-up</td><td>{{ $tamu->where('fu_stage', '!=', 'baru')->count() }}</td></tr>
        <tr><td>Menjadi jemaat (bulan ini)</td><td>{{ $konversi }}</td></tr>
    </table>

    <h2>5. Visitasi</h2>
    <p>{{ $visit->count() }} kunjungan terlaksana.</p>
    @if ($visit->count())
        <table>
            <tr><th>Tanggal</th><th>KK</th><th>Jenis</th></tr>
            @foreach ($visit as $v)
                <tr><td>{{ $v->realized_date?->format('d/m/Y') }}</td><td>{{ $v->family?->display_name }}</td><td>{{ Opsi::label(Opsi::JENIS_VISITASI, $v->type) }}</td></tr>
            @endforeach
        </table>
    @endif

    <div class="page-break"></div>
    <h2>6. Jemaat per wilayah dan status</h2>
    @include('pdf.partials.matrix')
@endsection
