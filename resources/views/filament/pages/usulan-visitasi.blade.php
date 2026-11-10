<x-filament-panels::page>
    <x-filament::section heading="Usulan visitasi ({{ $usulan->count() }})"
        description="Dari: jemaat tidak hadir 3x berturut-turut, jemaat baru, lansia {{ config('sij.elderly_age') }}+, dan KK yang belum dikunjungi > {{ config('sij.visit_overdue_months') }} bulan. KK yang sudah dijadwalkan tidak ditampilkan.">
        @if ($usulan->isEmpty())
            <p style="opacity:.7">Tidak ada usulan.</p>
        @else
            <div style="overflow-x:auto;max-height:560px">
            <table style="width:100%;font-size:.875rem;border-collapse:collapse">
                <thead><tr style="text-align:left;border-bottom:2px solid rgba(127,127,127,.3)">
                    <th style="padding:.5rem">KK</th><th>Wilayah</th><th>Alasan</th><th></th>
                </tr></thead>
                <tbody>
                @foreach ($usulan as $u)
                    <tr style="border-bottom:1px solid rgba(127,127,127,.15)">
                        <td style="padding:.45rem .5rem">
                            <a href="{{ \App\Filament\Resources\Families\FamilyResource::getUrl('view', ['record' => $u['family']]) }}" style="font-weight:600">{{ $u['family']->display_name }}</a>
                        </td>
                        <td>{{ $u['family']->region?->name ?? '-' }}</td>
                        <td>{{ $u['reason'] }}</td>
                        <td style="text-align:right;white-space:nowrap">
                            @if ($bisaJadwal)
                                <x-filament::link :href="\App\Filament\Resources\Visits\VisitResource::getUrl('create', ['family_id' => $u['family']->id, 'type' => $u['type'], 'reason' => \Illuminate\Support\Str::limit($u['reason'], 200)])" icon="heroicon-o-calendar" size="sm">
                                    Jadwalkan
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

    <x-filament::section heading="Cakupan kunjungan per wilayah (VS-07)">
        <x-slot name="afterHeader">
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="tahun">
                    @foreach ($tahunOpsi as $t)
                        <option value="{{ $t }}">{{ $t }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </x-slot>
        <table style="width:100%;font-size:.875rem;border-collapse:collapse">
            <thead><tr style="text-align:left;border-bottom:2px solid rgba(127,127,127,.3)">
                <th style="padding:.5rem">Wilayah</th><th style="text-align:right">KK</th><th style="text-align:right">Dikunjungi</th><th style="width:40%;padding-left:1rem">Cakupan</th>
            </tr></thead>
            <tbody>
            @foreach ($cakupan as $c)
                <tr style="border-bottom:1px solid rgba(127,127,127,.15)">
                    <td style="padding:.45rem .5rem">{{ $c['wilayah'] }}</td>
                    <td style="text-align:right">{{ $c['total'] }}</td>
                    <td style="text-align:right">{{ $c['dikunjungi'] }}</td>
                    <td style="padding-left:1rem">
                        <div style="display:flex;align-items:center;gap:.5rem">
                            <div style="flex:1;height:8px;background:rgba(127,127,127,.2);border-radius:4px">
                                <div style="height:8px;width:{{ $c['pct'] }}%;background:#4f46e5;border-radius:4px"></div>
                            </div>
                            <span style="width:3rem;text-align:right">{{ $c['pct'] }}%</span>
                        </div>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </x-filament::section>
</x-filament-panels::page>
