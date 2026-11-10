<x-filament-panels::page>
    @php($td = 'padding:.35rem .5rem;border-bottom:1px solid rgba(127,127,127,.15);vertical-align:top')
    <x-filament::section heading="1. Pilih file Excel" description="Gunakan Data Jemaat Lengkap.xlsx (sheet Data Jemaat Komprehensif) sebagai sumber migrasi utama. Data dengan kode yang sama akan diperbarui, bukan diduplikasi.">
        <input type="file" wire:model="file" accept=".xlsx,.xls,.csv" />
        <div wire:loading wire:target="file" style="font-size:.85rem;opacity:.7">Mengunggah & membaca file...</div>
        @error('file') <p style="color:#dc2626;font-size:.85rem">{{ $message }}</p> @enderror
        @if ($storedPath && count($sheets) > 1)
            <div style="margin-top:.75rem">
                <label style="font-size:.85rem">Sheet:
                    <x-filament::input.wrapper style="display:inline-block;min-width:200px">
                        <x-filament::input.select wire:model.live="sheet">
                            @foreach ($sheets as $s)
                                <option value="{{ $s }}">{{ $sheetNames[$s] ?? 'Sheet '.($s + 1) }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </label>
            </div>
        @endif
        @if ($storedPath)
            <p style="font-size:.85rem;margin-top:.5rem">{{ $rowCount }} baris data ditemukan (header di baris {{ $headerRow }}).</p>
        @endif
    </x-filament::section>

    @if ($storedPath)
        <x-filament::section heading="2. Pemetaan kolom" description="Pemetaan ditebak otomatis dari judul kolom. Periksa dan sesuaikan bila perlu.">
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:.75rem">
                @foreach ($fields as $key => [$label])
                    <label style="font-size:.8rem">{{ $label }}
                        <x-filament::input.wrapper>
                            <x-filament::input.select wire:model="mapping.{{ $key }}">
                                <option value="">(tidak dipakai)</option>
                                @foreach ($headings as $i => $h)
                                    @if ($h !== '')
                                        <option value="{{ $i }}">{{ $h }}</option>
                                    @endif
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </label>
                @endforeach
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:1.25rem;margin-top:1rem;font-size:.85rem">
                <label><x-filament::input.checkbox wire:model="createRegions" /> Buat wilayah baru bila tidak cocok dengan master (salah ketik dicocokkan otomatis)</label>
                <label><x-filament::input.checkbox wire:model="placeholderSacraments" /> Buat data baptis/sidi "belum diketahui" untuk anggota</label>
                <label><x-filament::input.checkbox wire:model="geocode" /> Jalankan geocoding alamat KK setelah import</label>
            </div>
            <div style="margin-top:1rem;display:flex;gap:.5rem">
                <x-filament::button wire:click="runPreview" icon="heroicon-o-eye" wire:loading.attr="disabled">Pratinjau</x-filament::button>
            </div>
        </x-filament::section>
    @endif

    @if ($preview)
        <x-filament::section heading="3. Pratinjau">
            <div style="display:flex;gap:1.5rem;flex-wrap:wrap;font-size:.9rem;margin-bottom:.75rem">
                <span>Total: <b>{{ $previewStats['total'] }}</b></span>
                <span style="color:#dc2626">Gagal: <b>{{ $previewStats['error'] }}</b></span>
                <span style="color:#d97706">Peringatan: <b>{{ $previewStats['warning'] }}</b></span>
                <span style="color:#7c3aed">Kemungkinan duplikat: <b>{{ $previewStats['duplikat'] }}</b></span>
            </div>
            <div style="max-height:420px;overflow:auto">
                <table style="width:100%;font-size:.8rem;border-collapse:collapse">
                    <tr style="text-align:left"><th style="{{ $td }}">Baris</th><th style="{{ $td }}">Kode</th><th style="{{ $td }}">Nama</th><th style="{{ $td }}">Tgl lahir</th><th style="{{ $td }}">Wilayah</th><th style="{{ $td }}">KK</th><th style="{{ $td }}">Status baru</th><th style="{{ $td }}">Catatan</th></tr>
                    @foreach ($preview as $r)
                        <tr style="{{ $r['errors'] ? 'background:rgba(220,38,38,.07)' : '' }}">
                            <td style="{{ $td }}">{{ $r['line'] }}</td><td style="{{ $td }}">{{ $r['code'] }}</td><td style="{{ $td }}">{{ $r['name'] }}</td>
                            <td style="{{ $td }}">{{ $r['birth_date'] ? \Illuminate\Support\Carbon::parse($r['birth_date'])->format('d/m/Y') : '' }}</td>
                            <td style="{{ $td }}">{{ $r['region'] }}</td><td style="{{ $td }}">{{ $r['family'] }}</td><td style="{{ $td }}">{{ $r['status'] }}</td>
                            <td style="{{ $td }}">
                                @foreach ($r['errors'] as $e)<div style="color:#dc2626">{{ $e }}</div>@endforeach
                                @foreach ($r['warnings'] as $w)<div style="color:#b45309">{{ $w }}</div>@endforeach
                            </td>
                        </tr>
                    @endforeach
                </table>
            </div>
            <p style="font-size:.8rem;opacity:.7;margin-top:.5rem">Menampilkan maksimal 100 baris, yang bermasalah lebih dulu. Baris dengan "Gagal" tidak akan diimpor.</p>
            <div style="margin-top:1rem">
                <x-filament::button wire:click="runImport" color="success" icon="heroicon-o-arrow-up-tray" wire:confirm="Jalankan import sekarang?" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="runImport">Jalankan import</span>
                    <span wire:loading wire:target="runImport">Mengimpor...</span>
                </x-filament::button>
            </div>
        </x-filament::section>
    @endif

    @if ($result)
        <x-filament::section heading="4. Hasil import">
            <p>Data baru: <b>{{ $result['created'] }}</b> &middot; Diperbarui: <b>{{ $result['updated'] }}</b> &middot; Gagal: <b>{{ count($result['failed']) }}</b>
                @isset($result['geocode_queued']) &middot; Geocoding diantrekan: <b>{{ $result['geocode_queued'] }}</b> KK @endisset</p>
            <p style="font-size:.85rem;opacity:.75;margin-top:.35rem">Langkah berikutnya: cocokkan total per status di menu Pertumbuhan dengan sheet Rekapitulasi Statistik, lalu periksa menu Duplikat.</p>
            @if (count($result['failed']))
                <div style="margin-top:.75rem"><x-filament::button wire:click="downloadFailed" color="danger" icon="heroicon-o-arrow-down-tray" size="sm">Unduh laporan baris gagal</x-filament::button></div>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
