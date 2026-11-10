@php
    $peran = \App\Support\HakAkses::peranDiatur();
    $grup = collect(\App\Support\HakAkses::menu())->groupBy('grup', true);
    $warna = [0 => 'rgba(128,128,128,.55)', 1 => 'rgb(37,99,235)', 2 => 'rgb(22,163,74)'];
@endphp

<x-filament-panels::page>
    <x-filament::section>
        <div style="font-size:.875rem;line-height:1.6">
            Atur menu yang bisa dibuka tiap peran. <b>Lihat</b> = boleh membuka &amp; membaca,
            <b>Kelola</b> = boleh juga menambah &amp; mengubah. Menghapus data tetap khusus Super Admin/Sekretariat.
            Sel bertanda <span style="color:rgb(217,119,6)">●</span> sudah diubah dari aturan bawaan.
            <br>Super Admin selalu bisa semua; Petugas Absensi hanya membuka layar absensi.
            Data yang terlihat oleh Koordinator Wilayah/Komisi dan Tim Visitasi tetap dibatasi wilayah/komisi/KK tugasnya.
            Nomor HP &amp; alamat tetap hanya untuk peran yang berhak melihat kontak.
        </div>
    </x-filament::section>

    <div style="overflow-x:auto;border:1px solid rgba(128,128,128,.25);border-radius:12px">
        <table style="width:100%;border-collapse:collapse;font-size:.85rem">
            <thead>
                <tr style="background:rgba(128,128,128,.08)">
                    <th style="text-align:left;padding:10px 12px;min-width:200px">Menu</th>
                    @foreach ($peran as $kunci => $label)
                        <th style="padding:8px;text-align:center;min-width:110px;vertical-align:bottom">
                            <div>{{ $label }}</div>
                            <button type="button" wire:click="kembalikan('{{ $kunci }}')"
                                wire:confirm="Kembalikan hak akses {{ $label }} ke bawaan?"
                                style="font-size:.7rem;font-weight:400;opacity:.6;text-decoration:underline">bawaan</button>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($grup as $namaGrup => $menus)
                    <tr>
                        <td colspan="{{ count($peran) + 1 }}" style="padding:10px 12px 4px;font-weight:600;font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;opacity:.6">
                            {{ $namaGrup }}
                        </td>
                    </tr>
                    @foreach ($menus as $kunciMenu => $m)
                        <tr style="border-top:1px solid rgba(128,128,128,.15)">
                            <td style="padding:6px 12px">{{ $m['label'] }}</td>
                            @foreach ($peran as $kunci => $_)
                                @php $nilai = (int) ($matriks[$kunci][$kunciMenu] ?? 0); @endphp
                                <td style="padding:4px 6px;text-align:center;white-space:nowrap">
                                    <select wire:model.live="matriks.{{ $kunci }}.{{ $kunciMenu }}"
                                        style="font-size:.8rem;padding:3px 6px;border-radius:6px;border:1px solid rgba(128,128,128,.35);background:transparent;color:{{ $warna[$nilai] }};font-weight:{{ $nilai ? 600 : 400 }}">
                                        <option value="0">Tidak</option>
                                        <option value="1">Lihat</option>
                                        @if ($m['kelola'])
                                            <option value="2">Kelola</option>
                                        @endif
                                    </select>
                                    @if ($this->diubah($kunci, $kunciMenu))
                                        <span title="Diubah dari bawaan" style="color:rgb(217,119,6)">●</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </div>

    <div>
        <x-filament::button wire:click="simpan" icon="heroicon-o-check">Simpan</x-filament::button>
    </div>
</x-filament-panels::page>
