<x-filament-panels::page>
    <x-filament::section icon="heroicon-o-information-circle" heading="Tentang Aplikasi">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:1rem;font-size:.875rem">
            <div>
                <div style="opacity:.6;font-size:.75rem;font-weight:600">Aplikasi</div>
                <div style="font-weight:700">{{ config('app.name') }}</div>
            </div>
            <div>
                <div style="opacity:.6;font-size:.75rem;font-weight:600">Gereja</div>
                <div style="font-weight:700">{{ config('sij.church_name') }}, {{ config('sij.church_city') }}</div>
            </div>
            <div>
                <div style="opacity:.6;font-size:.75rem;font-weight:600">Pembaruan Terakhir</div>
                <div style="font-weight:700">{{ $riwayat[0]['tanggal'] }}</div>
            </div>
            <div>
                <div style="opacity:.6;font-size:.75rem;font-weight:600">Data</div>
                <div style="font-weight:700">{{ number_format($stat['jemaat'], 0, ',', '.') }} jemaat · {{ number_format($stat['kk'], 0, ',', '.') }} KK</div>
            </div>
            <div>
                <div style="opacity:.6;font-size:.75rem;font-weight:600">Platform</div>
                <div style="font-weight:700">Laravel {{ app()->version() }} · PHP {{ PHP_VERSION }}</div>
            </div>
        </div>
    </x-filament::section>

    <x-filament::section icon="heroicon-o-scale" heading="Aturan Sistem" description="Aturan yang berlaku di aplikasi. Klik judul untuk membuka detail.">
        @foreach ($aturan as $grup)
            <details style="border:1px solid rgba(127,127,127,.25);border-radius:.5rem;margin-bottom:.6rem;padding:0 .9rem">
                <summary style="cursor:pointer;padding:.75rem 0;font-weight:700;font-size:.875rem;display:flex;align-items:center;gap:.5rem">
                    <x-filament::icon :icon="$grup['icon']" class="h-5 w-5 text-primary-600" />
                    {{ $grup['judul'] }}
                    <span style="margin-left:auto;font-size:.75rem;font-weight:600;opacity:.6">{{ count($grup['aturan']) }} aturan</span>
                </summary>
                <ul style="margin:0 0 .9rem;padding-left:1.25rem;font-size:.85rem;line-height:1.6;list-style:disc">
                    @foreach ($grup['aturan'] as $a)
                        <li style="margin-bottom:.35rem">{{ $a }}</li>
                    @endforeach
                </ul>
            </details>
        @endforeach
    </x-filament::section>

    <x-filament::section icon="heroicon-o-clock" heading="Riwayat Perubahan">
        @foreach ($riwayat as $rilis)
            <div style="border-left:3px solid rgb(var(--primary-500, 99 102 241));padding-left:.9rem;margin-bottom:1.25rem">
                <div style="font-weight:700;font-size:.875rem;margin-bottom:.35rem">{{ $rilis['tanggal'] }}</div>
                <ul style="margin:0;padding-left:1.1rem;font-size:.85rem;line-height:1.6;list-style:disc">
                    @foreach ($rilis['perubahan'] as $p)
                        <li style="margin-bottom:.25rem">{{ $p }}</li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </x-filament::section>

    @if ($bolehBackup)
        {{-- Form rahasia Backup & Restore: dibuka dengan Alt+F7, hanya dirender untuk super admin. --}}
        <div x-data x-on:keydown.window="if ($event.altKey && $event.key === 'F7') { $event.preventDefault(); $wire.openPanel() }"></div>

        @if ($panelOpen)
            <x-filament::section icon="heroicon-o-lock-closed" heading="Backup & Restore Database">
                <x-slot name="afterHeader">
                    <x-filament::icon-button icon="heroicon-o-x-mark" color="gray" label="Tutup" wire:click="$set('panelOpen', false)" />
                </x-slot>

                @if ($message)
                    <div style="margin-bottom:1rem;padding:.6rem .8rem;border-radius:.5rem;background:rgba(220,38,38,.1);color:#dc2626;font-size:.85rem">{{ $message }}</div>
                @endif

                @if (! $unlocked)
                    <form wire:submit="unlock" style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
                        <x-filament::input.wrapper style="max-width:14rem">
                            <x-filament::input type="password" wire:model="pin" placeholder="PIN backup" autocomplete="off" autofocus />
                        </x-filament::input.wrapper>
                        <x-filament::button type="submit">Buka</x-filament::button>
                    </form>
                @else
                    <div style="display:flex;gap:1rem;align-items:center;flex-wrap:wrap;margin-bottom:1rem;font-size:.85rem">
                        <span>Ukuran database: <b>{{ number_format($databaseSize / 1048576, 2, ',', '.') }} MB</b></span>
                        <x-filament::button icon="heroicon-o-arrow-down-tray" wire:click="createBackup" wire:loading.attr="disabled">
                            Buat backup sekarang
                        </x-filament::button>
                    </div>

                    <div style="font-weight:700;font-size:.875rem;margin-bottom:.4rem">Riwayat backup</div>
                    @if (empty($backups))
                        <p style="opacity:.7;font-size:.85rem">Belum ada file backup.</p>
                    @else
                        <div style="overflow-x:auto;margin-bottom:1.25rem">
                            <table style="width:100%;font-size:.85rem;border-collapse:collapse">
                                <thead><tr style="text-align:left;opacity:.7"><th style="padding:.35rem">File</th><th>Ukuran</th><th>Waktu</th><th></th></tr></thead>
                                <tbody>
                                    @foreach ($backups as $b)
                                        <tr wire:key="bk-{{ $b['name'] }}" style="border-top:1px solid rgba(127,127,127,.2)">
                                            <td style="padding:.35rem;font-family:monospace">{{ $b['name'] }}</td>
                                            <td>{{ number_format($b['size'] / 1024, 0, ',', '.') }} KB</td>
                                            <td>{{ date('d/m/Y H:i', $b['time']) }}</td>
                                            <td style="text-align:right;white-space:nowrap">
                                                <x-filament::link tag="button" wire:click="download('{{ $b['name'] }}')" icon="heroicon-o-arrow-down-tray">Download</x-filament::link>
                                                <x-filament::link tag="button" color="danger" icon="heroicon-o-trash" style="margin-left:.75rem"
                                                    wire:click="deleteBackup('{{ $b['name'] }}')" wire:confirm="Hapus file {{ $b['name'] }}?">Hapus</x-filament::link>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    <div style="font-weight:700;font-size:.875rem;margin-bottom:.4rem;color:#dc2626">Restore database</div>
                    <p style="font-size:.8rem;opacity:.75;margin-bottom:.6rem">
                        Seluruh database akan ditimpa dengan isi file. Sistem otomatis membuat backup "_pre-restore" lebih dulu.
                        Bisa memakai file dari riwayat, atau upload .sql / .sql.gz (hasil backup ini, phpMyAdmin, atau mysqldump).
                    </p>
                    <form wire:submit="restore" style="display:grid;gap:.6rem;max-width:32rem">
                        <x-filament::input.wrapper>
                            <x-filament::input.select wire:model="restoreName">
                                <option value="">— pilih dari riwayat —</option>
                                @foreach ($backups as $b)
                                    <option value="{{ $b['name'] }}">{{ $b['name'] }}</option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                        <div style="font-size:.8rem;opacity:.7">atau upload file:</div>
                        <input type="file" wire:model="restoreFile" accept=".sql,.gz" style="font-size:.85rem">
                        <div wire:loading wire:target="restoreFile" style="font-size:.8rem">Mengunggah...</div>
                        <x-filament::input.wrapper>
                            <x-filament::input type="text" wire:model="konfirmasi" placeholder="Ketik RESTORE untuk konfirmasi" autocomplete="off" />
                        </x-filament::input.wrapper>
                        <div>
                            <x-filament::button type="submit" color="danger" icon="heroicon-o-arrow-path" wire:loading.attr="disabled" wire:target="restore,restoreFile">
                                Restore sekarang
                            </x-filament::button>
                            <span wire:loading wire:target="restore" style="font-size:.8rem;margin-left:.5rem">Memproses, jangan tutup halaman...</span>
                        </div>
                    </form>
                @endif
            </x-filament::section>
        @endif
    @endif
</x-filament-panels::page>
