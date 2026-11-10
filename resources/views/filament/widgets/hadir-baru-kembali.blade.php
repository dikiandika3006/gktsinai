<x-filament-widgets::widget>
    <x-filament::section icon="heroicon-o-sparkles" heading="Hadir pertama kali & kembali hadir"
        :description="$tanggal ? 'Ibadah Umum '.$tanggal->translatedFormat('d F Y') : null">
        @if (! $tanggal)
            <p style="opacity:.65">Belum ada Ibadah Umum yang tercatat.</p>
        @else
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1.25rem">
                @foreach (['pertama' => ['Pertama kali tercatat hadir', $pertama], 'kembali' => ['Kembali setelah ≥ 4 minggu absen', $kembali]] as $k => [$judul, $daftar])
                    <div>
                        <p style="font-weight:600;margin-bottom:.4rem">{{ $judul }} ({{ $daftar->count() }})</p>
                        @forelse ($daftar->take(15) as $x)
                            <div style="display:flex;justify-content:space-between;gap:.5rem;padding:.3rem 0;border-bottom:1px solid rgba(127,127,127,.15);font-size:.875rem">
                                <a href="{{ \App\Filament\Resources\Members\MemberResource::getUrl('view', ['record' => $x['member']]) }}" style="font-weight:500">{{ $x['member']->name }}</a>
                                <span style="opacity:.65">{{ $x['sejak'] ? 'terakhir '.$x['sejak']->format('d/m/Y') : ($x['member']->family?->head?->name ? 'Kel. '.$x['member']->family->head->name : '') }}</span>
                            </div>
                        @empty
                            <p style="opacity:.6;font-size:.875rem">Tidak ada.</p>
                        @endforelse
                        @if ($daftar->count() > 15)
                            <p style="opacity:.6;font-size:.8rem;margin-top:.3rem">dan {{ $daftar->count() - 15 }} lainnya</p>
                        @endif
                    </div>
                @endforeach
            </div>
            <p style="opacity:.6;font-size:.8rem;margin-top:.6rem">Sapa dan sambut mereka; yang kembali hadir bisa ditindaklanjuti dengan visitasi.</p>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
