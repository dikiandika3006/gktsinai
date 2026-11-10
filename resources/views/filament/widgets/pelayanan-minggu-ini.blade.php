@php
    use App\Support\Opsi;
    $urutanPeran = array_keys(Opsi::PERAN_PELAYANAN);
@endphp
<x-filament-widgets::widget>
    <x-filament::section icon="heroicon-o-megaphone"
        :heading="$jadwal ? 'Pelayanan '.$jadwal->date->translatedFormat('l, d F Y') : 'Pelayanan minggu ini'"
        :description="$jadwal?->tema ? 'Tema: '.$jadwal->tema : null">
        <x-slot name="afterHeader">
            <x-filament::link :href="$urlJadwal" size="sm">Jadwal bulanan</x-filament::link>
        </x-slot>

        @if (! $jadwal)
            <p style="opacity:.65">Belum ada jadwal pelayanan untuk Minggu mendatang.</p>
        @else
            @if ($peringatan)
                <p style="color:#b45309;font-size:.85rem;margin-bottom:.6rem">⚠ {{ collect($peringatan)->pluck('teks')->join(' ') }}</p>
            @endif
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1rem">
                @foreach ($kelompok as $kebaktian => $tugas)
                    <div>
                        <p style="font-weight:600;margin-bottom:.35rem">{{ $kebaktian }}</p>
                        @foreach ($tugas->groupBy('peran')->sortBy(fn ($v, $p) => array_search($p, $urutanPeran)) as $peran => $orang)
                            <div style="display:flex;gap:.6rem;padding:.25rem 0;border-bottom:1px solid rgba(127,127,127,.15);font-size:.875rem">
                                <span style="width:7.5rem;flex:none;opacity:.7">{{ Opsi::label(Opsi::PERAN_PELAYANAN, $peran) }}</span>
                                <span>{{ $orang->map(fn ($a) => $a->member?->name ?? $a->nama)->join(', ') }}</span>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
