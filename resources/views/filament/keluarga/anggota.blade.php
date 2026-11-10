@php
    use App\Filament\Resources\Members\MemberResource;
    use App\Support\Opsi;

    /** @var \App\Models\Family $family */
    $anggota = $family->members()->orderByRaw("CASE WHEN id = ? THEN 0 ELSE 1 END", [$family->head_member_id ?? 0])
        ->orderByRaw('birth_date IS NULL')->orderBy('birth_date')->get();
    $kontak = auth()->user()?->lihatKontak();
    $masalah = [];
    if (! $family->head_member_id) {
        $masalah[] = 'KK ini belum punya kepala keluarga.';
    } elseif (! $anggota->contains('id', $family->head_member_id)) {
        $masalah[] = 'Kepala keluarga tercatat di KK lain.';
    }
    if ($anggota->where('family_relation', 'kepala')->count() > 1) {
        $masalah[] = 'Ada lebih dari satu anggota berhubungan "Kepala Keluarga".';
    }
@endphp

<div style="display:flex;flex-direction:column;gap:12px">
    @if ($kontak && $family->address)
        <div style="font-size:.875rem;opacity:.75">{{ $family->address }}</div>
    @endif

    @foreach ($masalah as $m)
        <div style="border-radius:8px;padding:8px 12px;font-size:.875rem;background:rgba(245,158,11,.12);color:rgb(180,83,9)">⚠ {{ $m }}</div>
    @endforeach

    <div style="border:1px solid rgba(128,128,128,.25);border-radius:12px;overflow:hidden">
        @forelse ($anggota as $a)
            @php
                $kepala = $a->id === $family->head_member_id;
                $ini = isset($current) && $current->id === $a->id;
                $keluar = in_array($a->membership_status, ['keluar', 'meninggal'], true);
            @endphp
            <a href="{{ MemberResource::getUrl('view', ['record' => $a]) }}"
               style="display:flex;align-items:center;gap:12px;padding:10px 14px;text-decoration:none;color:inherit;{{ $loop->first ? '' : 'border-top:1px solid rgba(128,128,128,.18);' }}{{ $ini ? 'background:rgba(99,102,241,.10);' : '' }}{{ $keluar ? 'opacity:.55;' : '' }}">
                <span style="width:20px;text-align:center;color:rgb(234,179,8)" title="{{ $kepala ? 'Kepala keluarga' : '' }}">{{ $kepala ? '★' : '' }}</span>
                <span style="flex:1;min-width:0">
                    <span style="display:block;font-weight:{{ $kepala || $ini ? 600 : 400 }}">
                        {{ $a->name }}@if ($a->alias) <span style="opacity:.6;font-weight:400">({{ $a->alias }})</span>@endif
                    </span>
                    <span style="display:block;font-size:.8rem;opacity:.65">
                        {{ Opsi::label(Opsi::HUBUNGAN, $a->family_relation) ?? 'Hubungan belum diisi' }}
                        · {{ $a->gender ?? '-' }}
                        @if ($a->age !== null) · {{ $a->age }} th @endif
                        · {{ Opsi::label(Opsi::KEANGGOTAAN, $a->membership_status) }}
                    </span>
                </span>
                <span style="font-size:.75rem;opacity:.5">{{ $a->code }}</span>
            </a>
        @empty
            <div style="padding:14px;opacity:.6">KK ini tidak punya anggota.</div>
        @endforelse
    </div>
</div>
