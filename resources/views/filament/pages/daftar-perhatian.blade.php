<x-filament-panels::page>
    <x-filament::section heading="Tidak hadir {{ $threshold }} kali berturut-turut ({{ $perhatian->count() }})"
        description="Jemaat aktif yang tidak hadir Ibadah Umum {{ $threshold }} minggu berturut-turut, atau tidak hadir di komisinya {{ $threshold }} pertemuan berturut-turut.">
        @if ($perhatian->isEmpty())
            <p style="opacity:.7">Tidak ada. (Daftar terisi setelah minimal {{ $threshold }} sesi tercatat.)</p>
        @else
            <div style="overflow-x:auto">
            <table style="width:100%;font-size:.875rem;border-collapse:collapse">
                <thead><tr style="text-align:left;border-bottom:2px solid rgba(127,127,127,.3)">
                    <th style="padding:.5rem">Nama</th><th>Wilayah</th><th>Alasan</th><th>Terakhir hadir</th><th></th>
                </tr></thead>
                <tbody>
                @foreach ($perhatian as $r)
                    @php($m = $r['member'])
                    <tr style="border-bottom:1px solid rgba(127,127,127,.15)">
                        <td style="padding:.45rem .5rem">
                            <a href="{{ \App\Filament\Resources\Members\MemberResource::getUrl('view', ['record' => $m]) }}" style="font-weight:600">{{ $m->name }}</a>
                            <div style="font-size:.75rem;opacity:.6">{{ $m->code }} &middot; {{ $m->family?->code }}</div>
                        </td>
                        <td>{{ $m->family?->region?->name ?? '-' }}</td>
                        <td>{{ $r['reason'] }}</td>
                        <td>{{ $r['last_seen'] ? \Illuminate\Support\Carbon::parse($r['last_seen'])->format('d/m/Y') : 'Belum pernah' }}</td>
                        <td style="text-align:right">
                            @if ($bisaJadwal && $m->family_id)
                                <x-filament::link :href="\App\Filament\Resources\Visits\VisitResource::getUrl('create', ['family_id' => $m->family_id, 'type' => 'pasif', 'reason' => $m->name.': '.$r['reason']])" icon="heroicon-o-calendar" size="sm">
                                    Jadwalkan visitasi
                                </x-filament::link>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            </div>
        @endif
    </x-filament::section>

    <x-filament::section heading="Usulan status Pasif ({{ $pasif->count() }})"
        description="Jemaat berstatus Aktif yang tidak tercatat hadir di kegiatan mana pun selama {{ $bulan }} bulan. Perubahan status harus disetujui manual (KH-09).">
        @if ($pasif->isEmpty())
            <p style="opacity:.7">Tidak ada usulan. (Dihitung setelah sistem memiliki data kehadiran minimal {{ $bulan }} bulan.)</p>
        @else
            <div style="max-height:420px;overflow:auto">
            @foreach ($pasif as $m)
                <label style="display:flex;gap:.6rem;align-items:center;padding:.35rem 0;border-bottom:1px solid rgba(127,127,127,.15)">
                    @if ($bisaKelola)
                        <x-filament::input.checkbox wire:model="pasifIds" value="{{ $m->id }}" />
                    @endif
                    <span style="font-weight:500">{{ $m->name }}</span>
                    <span style="opacity:.6;font-size:.8rem">{{ $m->code }} &middot; {{ $m->family?->region?->name ?? '-' }}</span>
                </label>
            @endforeach
            </div>
            @if ($bisaKelola)
                <div style="margin-top:1rem">
                    <x-filament::button wire:click="setujuiPasif" wire:confirm="Ubah status jemaat yang dicentang menjadi Pasif?" color="warning">
                        Setujui jadi Pasif
                    </x-filament::button>
                </div>
            @endif
        @endif
    </x-filament::section>
</x-filament-panels::page>
