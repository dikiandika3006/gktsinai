<x-filament-panels::page>
    <x-filament::section heading="Status database">
        @if (empty($pending))
            <p style="color:#16a34a">Database sudah versi terbaru.</p>
        @else
            <p style="color:#b45309">Ada {{ count($pending) }} migrasi yang belum dijalankan. Klik <b>Update database</b>.</p>
            <ul style="margin-top:.5rem;font-family:monospace;font-size:.8rem">
                @foreach ($pending as $p)
                    <li>{{ $p }}</li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>

    @if ($pinJalan || $pinLog)
        @php
            $selesai = $pinTotal - count($pinAntrean);
            $persen = $pinTotal ? round($selesai / $pinTotal * 100) : 100;
        @endphp
        <x-filament::section heading="Pin alamat (OpenStreetMap)">
            <div @if ($pinJalan) wire:poll.1500ms="prosesPin" @endif style="display:flex;flex-direction:column;gap:10px">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
                    <span>
                        <b>{{ $selesai }} / {{ $pinTotal }}</b> KK
                        @if ($pinJalan)
                            · sedang diproses, biarkan halaman ini terbuka
                        @else
                            · {{ count($pinAntrean) ? 'dihentikan' : 'selesai' }}
                        @endif
                    </span>
                    @if ($pinJalan)
                        <x-filament::button color="danger" size="sm" wire:click="hentikanPin">Hentikan</x-filament::button>
                    @endif
                </div>
                <div style="height:10px;border-radius:999px;background:rgba(128,128,128,.2);overflow:hidden">
                    <div style="height:100%;width:{{ $persen }}%;background:rgb(79,70,229);transition:width .4s"></div>
                </div>
                @if ($pinHasil)
                    <div style="font-size:.875rem">
                        @foreach ($pinHasil as $status => $n)
                            <span style="margin-right:14px">{{ \App\Support\Opsi::label(\App\Support\Opsi::GEOCODE, $status) ?? $status }}: <b>{{ $n }}</b></span>
                        @endforeach
                    </div>
                @endif
                <pre style="white-space:pre-wrap;font-size:.75rem;opacity:.8;margin:0">{{ implode("\n", $pinLog) }}</pre>
                <p style="font-size:.8rem;opacity:.65;margin:0">
                    Hasil "Perkiraan" atau "Gagal" bisa dirapikan manual: buka KK lalu geser pin di peta. Filter di daftar KK: <i>Status lokasi</i>.
                </p>
            </div>
        </x-filament::section>
    @endif

    @if ($output)
        <x-filament::section heading="Hasil perintah terakhir">
            <pre style="white-space:pre-wrap;font-size:.8rem;max-height:28rem;overflow:auto">{{ $output }}</pre>
        </x-filament::section>
    @endif
</x-filament-panels::page>
