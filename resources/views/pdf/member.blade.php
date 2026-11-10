@php use App\Support\Opsi; @endphp
@extends('pdf.layout')
@section('title', 'Buku Induk Jemaat')
@section('subtitle', 'Buku Induk Jemaat Digital'.(isset($family) ? ' - '.$family->code : ''))
@section('content')
    @isset($family)
        <h2>Kepala Keluarga {{ $family->code }}</h2>
        <table class="kv">
            <tr><td>Kepala keluarga</td><td>{{ $family->head?->name ?? '-' }}</td></tr>
            <tr><td>Wilayah</td><td>{{ $family->region?->name ?? '-' }}</td></tr>
            @if ($kontak)
                <tr><td>Alamat</td><td>{{ $family->address ?? '-' }}</td></tr>
                <tr><td>No. HP keluarga</td><td>{{ $family->phone ?? '-' }}</td></tr>
            @endif
        </table>
        <h2>Anggota keluarga</h2>
        <table>
            <tr><th>Kode</th><th>Nama</th><th>Hubungan</th><th>L/P</th><th>Tgl lahir</th><th>Keanggotaan</th><th>Keaktifan</th></tr>
            @foreach ($members as $m)
                <tr><td>{{ $m->code }}</td><td>{{ $m->name }}</td><td>{{ Opsi::label(Opsi::HUBUNGAN, $m->family_relation) }}</td><td>{{ $m->gender }}</td>
                    <td>{{ $m->birth_date?->format('d/m/Y') }}</td><td>{{ Opsi::label(Opsi::KEANGGOTAAN, $m->membership_status) }}</td><td>{{ Opsi::label(Opsi::KEAKTIFAN, $m->activity_status) }}</td></tr>
            @endforeach
        </table>
        <div class="page-break"></div>
    @endisset

    @foreach ($members as $m)
        <h2>{{ $m->code }} &middot; {{ $m->name }}</h2>
        <table class="kv">
            <tr><td>No Induk lama</td><td>{{ $m->old_registration_no ?? '-' }}</td></tr>
            <tr><td>Nama / alias</td><td>{{ $m->name }}{{ $m->alias ? ' ('.$m->alias.')' : '' }}</td></tr>
            <tr><td>Jenis kelamin</td><td>{{ Opsi::label(Opsi::KELAMIN, $m->gender) ?? '-' }}</td></tr>
            <tr><td>Tempat, tanggal lahir</td><td>{{ $m->birth_place ? $m->birth_place.', ' : '' }}{{ $m->birth_date?->format('d/m/Y') ?? '-' }}{{ $m->age !== null ? ' ('.$m->age.' th)' : '' }}</td></tr>
            <tr><td>KK / hubungan</td><td>{{ $m->family?->code ?? '-' }} / {{ Opsi::label(Opsi::HUBUNGAN, $m->family_relation) ?? '-' }}</td></tr>
            <tr><td>Wilayah</td><td>{{ $m->family?->region?->name ?? '-' }}</td></tr>
            @if ($kontak)
                <tr><td>Alamat</td><td>{{ $m->family?->address ?? '-' }}</td></tr>
                <tr><td>No. HP</td><td>{{ $m->phone ?? '-' }}</td></tr>
            @endif
            <tr><td>Status keanggotaan</td><td>{{ Opsi::label(Opsi::KEANGGOTAAN, $m->membership_status) }}</td></tr>
            <tr><td>Status keaktifan</td><td>{{ Opsi::label(Opsi::KEAKTIFAN, $m->activity_status) ?? '-' }}</td></tr>
            <tr><td>Komisi</td><td>{{ $m->commissions->pluck('name')->join(', ') ?: '-' }}</td></tr>
        </table>
        <p style="margin:8px 0 4px;font-weight:bold">Sakramen &amp; mutasi</p>
        <table>
            <tr><th>Jenis</th><th>Tanggal</th><th>Gereja / tempat</th><th>Pendeta</th><th>No. surat</th><th>Data</th></tr>
            @forelse ($m->events as $e)
                <tr><td>{{ Opsi::label(Opsi::JENIS_EVENT, $e->type) }}</td><td>{{ $e->date?->format('d/m/Y') ?? '-' }}</td>
                    <td>{{ collect([$e->church, $e->place])->filter()->join(', ') ?: '-' }}</td><td>{{ $e->minister ?? '-' }}</td>
                    <td>{{ $e->letter_no ?? '-' }}</td><td>{{ Opsi::label(Opsi::STATUS_DATA_EVENT, $e->data_status) }}</td></tr>
            @empty
                <tr><td colspan="6" class="muted">Belum ada catatan.</td></tr>
            @endforelse
        </table>
        @if (! $loop->last) <div style="height:12px"></div> @endif
    @endforeach
@endsection
