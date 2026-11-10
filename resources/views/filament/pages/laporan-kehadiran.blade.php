<x-filament-panels::page>
    @php($th = 'padding:.45rem .5rem;text-align:left;border-bottom:2px solid rgba(127,127,127,.3)')
    @php($td = 'padding:.4rem .5rem;border-bottom:1px solid rgba(127,127,127,.15)')
    <x-filament::section>
        <div style="display:flex;flex-wrap:wrap;gap:1rem;align-items:end">
            <label style="font-size:.85rem">Dari<br>
                <x-filament::input.wrapper><x-filament::input type="date" wire:model.live="dari" /></x-filament::input.wrapper></label>
            <label style="font-size:.85rem">Sampai<br>
                <x-filament::input.wrapper><x-filament::input type="date" wire:model.live="sampai" /></x-filament::input.wrapper></label>
            <label style="font-size:.85rem">Kegiatan<br>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="jenis">
                        <option value="">Semua kegiatan</option>
                        @foreach ($jenisOpsi as $id => $nama)
                            <option value="{{ $id }}">{{ $nama }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper></label>
        </div>
    </x-filament::section>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:1.5rem">
        <x-filament::section heading="Per sesi ({{ $sesi->count() }})">
            <div style="max-height:360px;overflow:auto">
            <table style="width:100%;font-size:.85rem;border-collapse:collapse">
                <tr><th style="{{ $th }}">Tanggal</th><th style="{{ $th }}">Kegiatan</th><th style="{{ $th }};text-align:right">Hadir</th></tr>
                @foreach ($sesi as $s)
                    <tr><td style="{{ $td }}">{{ $s->date->format('d/m/Y') }}</td><td style="{{ $td }}">{{ $s->activityType?->name }}</td>
                        <td style="{{ $td }};text-align:right">{{ $s->manual_count ?? $s->attendances_count }}</td></tr>
                @endforeach
            </table>
            </div>
            @if ($sesi->count())
                <p style="margin-top:.5rem;font-size:.85rem">Rata-rata: <b>{{ round($sesi->avg(fn ($s) => $s->manual_count ?? $s->attendances_count), 1) }}</b> per sesi</p>
            @endif
        </x-filament::section>

        <x-filament::section heading="Per wilayah">
            <table style="width:100%;font-size:.85rem;border-collapse:collapse">
                <tr><th style="{{ $th }}">Wilayah</th><th style="{{ $th }};text-align:right">Rata-rata/sesi</th><th style="{{ $th }};text-align:right">Jemaat unik</th><th style="{{ $th }};text-align:right">Aktif</th></tr>
                @foreach ($perWilayah as $w)
                    <tr><td style="{{ $td }}">{{ $w->wilayah }}</td>
                        <td style="{{ $td }};text-align:right">{{ $sesi->count() ? round($w->total / $sesi->count(), 1) : 0 }}</td>
                        <td style="{{ $td }};text-align:right">{{ $w->unik }}</td>
                        <td style="{{ $td }};text-align:right">{{ $aktifPerWilayah[$w->wilayah] ?? 0 }}</td></tr>
                @endforeach
            </table>
        </x-filament::section>

        <x-filament::section heading="Per komisi">
            <table style="width:100%;font-size:.85rem;border-collapse:collapse">
                <tr><th style="{{ $th }}">Komisi</th><th style="{{ $th }};text-align:right">Sesi</th><th style="{{ $th }};text-align:right">Rata-rata hadir</th><th style="{{ $th }};text-align:right">Anggota</th></tr>
                @foreach ($perKomisi as $k)
                    <tr><td style="{{ $td }}">{{ $k['komisi'] }}</td><td style="{{ $td }};text-align:right">{{ $k['sesi'] }}</td>
                        <td style="{{ $td }};text-align:right">{{ $k['rata'] }}</td><td style="{{ $td }};text-align:right">{{ $k['anggota'] }}</td></tr>
                @endforeach
            </table>
        </x-filament::section>
    </div>

    <x-filament::section heading="Per jemaat ({{ $nSesi }} sesi dalam periode)" description="Diurutkan dari kehadiran terendah. Hanya jemaat berstatus aktif.">
        <x-slot name="afterHeader">
            <x-filament::input.wrapper><x-filament::input type="search" placeholder="Cari nama..." wire:model.live.debounce.400ms="cari" /></x-filament::input.wrapper>
        </x-slot>
        <div style="max-height:480px;overflow:auto">
        <table style="width:100%;font-size:.85rem;border-collapse:collapse">
            <tr><th style="{{ $th }}">Nama</th><th style="{{ $th }}">Wilayah</th><th style="{{ $th }};text-align:right">Hadir</th><th style="{{ $th }};width:30%">%</th></tr>
            @foreach ($perJemaat as $r)
                <tr><td style="{{ $td }}">{{ $r['member']->name }} <span style="opacity:.5">{{ $r['member']->code }}</span></td>
                    <td style="{{ $td }}">{{ $r['member']->family?->region?->name ?? '-' }}</td>
                    <td style="{{ $td }};text-align:right">{{ $r['hadir'] }}/{{ $nSesi }}</td>
                    <td style="{{ $td }}">
                        <div style="display:flex;gap:.5rem;align-items:center">
                            <div style="flex:1;height:6px;background:rgba(127,127,127,.2);border-radius:3px">
                                <div style="height:6px;width:{{ $r['pct'] }}%;background:{{ $r['pct'] < 40 ? '#dc2626' : ($r['pct'] < 70 ? '#d97706' : '#16a34a') }};border-radius:3px"></div>
                            </div>{{ $r['pct'] }}%
                        </div>
                    </td></tr>
            @endforeach
        </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
