<x-filament-panels::page>
    <x-filament::section compact>
        <div style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:end">
            <label style="font-size:.8rem">Dari<br>
                <x-filament::input.wrapper><x-filament::input type="date" wire:model.live="dari" /></x-filament::input.wrapper></label>
            <label style="font-size:.8rem">Sampai<br>
                <x-filament::input.wrapper><x-filament::input type="date" wire:model.live="sampai" /></x-filament::input.wrapper></label>
            <label style="font-size:.8rem">Peran<br>
                <x-filament::input.wrapper><x-filament::input.select wire:model.live="peran">
                    <option value="">Semua</option>
                    @foreach ($opsiPeran as $k => $v) <option value="{{ $k }}">{{ $v }}</option> @endforeach
                </x-filament::input.select></x-filament::input.wrapper></label>
            <label style="font-size:.8rem">Status<br>
                <x-filament::input.wrapper><x-filament::input.select wire:model.live="status">
                    <option value="">Semua</option>
                    <option value="terdaftar">Terdaftar di data jemaat</option>
                    <option value="belum">Belum terdaftar</option>
                </x-filament::input.select></x-filament::input.wrapper></label>
            <label style="font-size:.8rem;flex:1;min-width:12rem">Cari nama<br>
                <x-filament::input.wrapper><x-filament::input type="search" wire:model.live.debounce.400ms="cari" placeholder="Nama..." /></x-filament::input.wrapper></label>
        </div>
    </x-filament::section>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(10rem,1fr));gap:.75rem">
        @foreach ($ringkas as $l => $v)
            <x-filament::section compact>
                <div style="font-size:.75rem;opacity:.7">{{ $l }}</div>
                <div style="font-size:1.5rem;font-weight:700">{{ $v }}</div>
            </x-filament::section>
        @endforeach
    </div>

    <x-filament::section compact>
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;font-size:.85rem">
                <thead>
                    <tr style="text-align:left;border-bottom:1px solid rgba(127,127,127,.3)">
                        <th style="padding:.45rem">Nama</th>
                        <th style="padding:.45rem">Status</th>
                        @foreach ($peranTampil as $l) <th style="padding:.45rem;text-align:center">{{ $l }}</th> @endforeach
                        <th style="padding:.45rem;text-align:center">Total</th>
                        <th style="padding:.45rem;text-align:center" title="Jumlah hari Minggu melayani">Minggu</th>
                        <th style="padding:.45rem;text-align:center" title="Hari Minggu dengan lebih dari satu tugas">Rangkap</th>
                        <th style="padding:.45rem">Terakhir</th>
                        <th style="padding:.45rem">Berikutnya</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rekap as $r)
                        <tr style="border-bottom:1px solid rgba(127,127,127,.15)">
                            <td style="padding:.45rem">
                                @if ($r['member'])
                                    <a href="{{ \App\Filament\Resources\Members\MemberResource::getUrl('view', ['record' => $r['member']]) }}" style="font-weight:600;text-decoration:underline">{{ $r['nama'] }}</a>
                                @else
                                    <span style="font-weight:600">{{ $r['nama'] }}</span>
                                @endif
                                @if (mb_strtolower($r['ditulis']) !== mb_strtolower($r['nama']))
                                    <div style="font-size:.75rem;opacity:.6">di jadwal: {{ $r['ditulis'] }}</div>
                                @endif
                            </td>
                            <td style="padding:.45rem;white-space:nowrap">
                                <span style="display:inline-flex;align-items:center;gap:.3rem"><span style="width:.55rem;height:.55rem;border-radius:50%;background:{{ $warna[$r['warna']] }}"></span>{{ $r['status'] }}</span>
                            </td>
                            @foreach ($peranTampil as $p => $l)
                                <td style="padding:.45rem;text-align:center">{{ $r['peran'][$p] ?? '' }}</td>
                            @endforeach
                            <td style="padding:.45rem;text-align:center;font-weight:700;{{ $r['total'] >= $batasBerat ? 'color:#dc2626' : '' }}">{{ $r['total'] }}</td>
                            <td style="padding:.45rem;text-align:center">{{ $r['minggu'] }}</td>
                            <td style="padding:.45rem;text-align:center;{{ $r['rangkap'] ? 'color:#d97706;font-weight:600' : '' }}">{{ $r['rangkap'] ?: '' }}</td>
                            <td style="padding:.45rem;white-space:nowrap">{{ $r['terakhir'] ? \Carbon\Carbon::parse($r['terakhir'])->format('d/m/Y') : '-' }}</td>
                            <td style="padding:.45rem;white-space:nowrap">{{ $r['berikut'] ? \Carbon\Carbon::parse($r['berikut'])->format('d/m/Y') : '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="20" style="padding:1rem;text-align:center;opacity:.7">Belum ada jadwal pelayanan pada rentang ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p style="font-size:.75rem;opacity:.65;margin-top:.6rem">Total merah = melayani {{ $batasBerat }}× atau lebih pada rentang ini (sekitar separuh hari Minggu atau lebih). "Rangkap" = lebih dari satu tugas di hari Minggu yang sama. Tugas "Kel. Bp. ..." dihitung ke kepala keluarga.</p>
    </x-filament::section>
</x-filament-panels::page>
