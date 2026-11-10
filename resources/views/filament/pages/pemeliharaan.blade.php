<x-filament-panels::page>
    @php
        $tabs = ['database' => 'Database', 'data' => 'Data Jemaat', 'peta' => 'Peta', 'paket' => 'Paket Data'];
        $tab = array_key_exists($tab, $tabs) ? $tab : 'database';
    @endphp

    {{-- Proses pin tetap berjalan walau pindah tab. --}}
    @if ($pinJalan)
        <div wire:poll.1500ms="prosesPin" style="display:none"></div>
    @endif

    <x-filament::tabs>
        @foreach ($tabs as $kunci => $label)
            <x-filament::tabs.item :active="$tab === $kunci" wire:click="$set('tab', '{{ $kunci }}')"
                :badge="$kunci === 'database' && count($pending) ? count($pending) : ($kunci === 'peta' && $pinJalan ? '…' : null)"
                :badge-color="$kunci === 'database' ? 'warning' : 'primary'">
                {{ $label }}
            </x-filament::tabs.item>
        @endforeach
    </x-filament::tabs>

    @if ($tab === 'database')
        <x-filament::section heading="Status database" description="Jalankan Update database setiap kali kode baru di-deploy ke hosting.">
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
            <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:1rem">
                {{ $this->migrateAction }}
                {{ $this->bersihkanCacheAction }}
                {{ $this->storageAction }}
            </div>
            <p style="font-size:.8rem;opacity:.65;margin-top:.75rem">
                Backup & restore database ada di menu <b>Tentang</b> → tekan <b>Alt+F7</b>.
            </p>
        </x-filament::section>
    @endif

    @if ($tab === 'data')
        <x-filament::section heading="Impor data jemaat dari Excel"
            description="Memakai sheet pertama (mis. Data Jemaat Komprehensif). Semua kolom dipetakan otomatis; kolom asli Excel ikut disimpan di bagian Data asli Excel tiap jemaat.">
            {{ $this->importAction }}
        </x-filament::section>
    @endif

    @if ($tab === 'peta')
        @php
            $stat = $this->statPin();
        @endphp
        <x-filament::section heading="Pin alamat (OpenStreetMap)">
            <div style="display:flex;gap:1.25rem;flex-wrap:wrap;font-size:.875rem;margin-bottom:1rem">
                @foreach (\App\Support\Opsi::GEOCODE as $kunci => $label)
                    <span>{{ $label }}: <b>{{ $stat[$kunci] ?? 0 }}</b></span>
                @endforeach
            </div>
            {{ $this->geocodeAction }}

            @if ($pinJalan || $pinLog)
                @php
                    $selesai = $pinTotal - count($pinAntrean);
                    $persen = $pinTotal ? round($selesai / $pinTotal * 100) : 100;
                @endphp
                <div style="display:flex;flex-direction:column;gap:10px;margin-top:1rem">
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
                </div>
            @endif
            <p style="font-size:.8rem;opacity:.65;margin-top:.75rem">
                Hasil "Perkiraan" atau "Gagal" bisa dirapikan manual: buka KK lalu geser pin di peta. Filter di daftar KK: <i>Status lokasi</i>.
            </p>
        </x-filament::section>
    @endif

    @if ($tab === 'paket')
        <x-filament::section heading="Paket data antar server"
            description="Kirim pin peta, batas wilayah, dan jadwal pelayanan dari satu server ke server lain (mis. lokal → hosting) tanpa menimpa data jemaat.">
            <div style="display:flex;gap:.5rem;flex-wrap:wrap">
                {{ $this->paketEksporAction }}
                {{ $this->paketImporAction }}
            </div>
        </x-filament::section>
    @endif

    @if ($output)
        <x-filament::section heading="Hasil perintah terakhir">
            <pre style="white-space:pre-wrap;font-size:.8rem;max-height:28rem;overflow:auto">{{ $output }}</pre>
        </x-filament::section>
    @endif
</x-filament-panels::page>
