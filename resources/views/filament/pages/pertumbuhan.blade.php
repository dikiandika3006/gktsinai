<x-filament-panels::page>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem">
        @foreach ($summary as $label => $n)
            <x-filament::section compact>
                <div style="font-size:.8rem;opacity:.7">{{ $label }}</div>
                <div style="font-size:1.6rem;font-weight:700">{{ number_format($n, 0, ',', '.') }}</div>
            </x-filament::section>
        @endforeach
        <x-filament::section compact>
            <div style="font-size:.8rem;opacity:.7">Target anggota baru {{ now()->year }}</div>
            @if ($target)
                @php($pct = $target->target_new_members ? min(100, round($realisasi / $target->target_new_members * 100)) : 0)
                <div style="font-size:1.6rem;font-weight:700">{{ $realisasi }} / {{ $target->target_new_members }}</div>
                <div style="height:6px;background:rgba(127,127,127,.2);border-radius:3px;margin-top:.4rem">
                    <div style="height:6px;width:{{ $pct }}%;background:#16a34a;border-radius:3px"></div>
                </div>
            @else
                <div style="font-size:1rem;opacity:.7">Belum ditetapkan</div>
                <a href="{{ \App\Filament\Resources\GrowthTargets\GrowthTargetResource::getUrl() }}" style="font-size:.8rem;text-decoration:underline">Atur target</a>
            @endif
        </x-filament::section>
    </div>

    <x-filament::section heading="Jemaat per wilayah dan status (PJ-02)">
        <div style="overflow-x:auto">
            <table style="width:100%;font-size:.875rem;border-collapse:collapse">
                <thead>
                    <tr style="text-align:left;border-bottom:2px solid rgba(127,127,127,.3)">
                        <th style="padding:.5rem">Wilayah</th>
                        @foreach ($columns as $c)
                            <th style="padding:.5rem;text-align:right">{{ $c }}</th>
                        @endforeach
                        <th style="padding:.5rem;text-align:right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($matrix as $row)
                        <tr style="border-bottom:1px solid rgba(127,127,127,.15)">
                            <td style="padding:.4rem .5rem;font-weight:500">{{ $row['wilayah'] }}</td>
                            @foreach ($columns as $c)
                                <td style="padding:.4rem .5rem;text-align:right">{{ $row[$c] ?: '' }}</td>
                            @endforeach
                            <td style="padding:.4rem .5rem;text-align:right;font-weight:600">{{ $row['Total'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr style="border-top:2px solid rgba(127,127,127,.3);font-weight:700">
                        <td style="padding:.5rem">Total</td>
                        @foreach ($columns as $c)
                            <td style="padding:.5rem;text-align:right">{{ $matrix->sum($c) }}</td>
                        @endforeach
                        <td style="padding:.5rem;text-align:right">{{ $matrix->sum('Total') }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </x-filament::section>

    <x-filament::section heading="Rincian mutasi 12 bulan terakhir (PJ-03)" collapsible collapsed>
        <table style="width:100%;font-size:.875rem;border-collapse:collapse">
            <thead><tr style="text-align:left;border-bottom:2px solid rgba(127,127,127,.3)">
                <th style="padding:.4rem">Bulan</th><th style="text-align:right">Masuk</th><th style="text-align:right">Keluar</th><th style="padding-left:1rem">Rincian</th>
            </tr></thead>
            <tbody>
            @foreach (array_reverse($series, true) as $bulan => $r)
                <tr style="border-bottom:1px solid rgba(127,127,127,.15)">
                    <td style="padding:.35rem">{{ \Illuminate\Support\Carbon::parse($bulan.'-01')->translatedFormat('F Y') }}</td>
                    <td style="text-align:right;color:#16a34a">{{ $r['masuk'] }}</td>
                    <td style="text-align:right;color:#dc2626">{{ $r['keluar'] }}</td>
                    <td style="padding-left:1rem;opacity:.75">{{ collect($r['detail'])->map(fn ($n, $k) => str_replace('_', ' ', $k).': '.$n)->join(', ') }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </x-filament::section>
</x-filament-panels::page>
