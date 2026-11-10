<x-filament-panels::page>
    <x-filament::section description="Kriteria: nama mirip dan tanggal lahir berselisih paling banyak 3 hari. Gabung memindahkan riwayat sakramen, kehadiran, komisi, dan kunjungan ke data yang dipertahankan, lalu menghapus data lainnya.">
        @if ($pairs->isEmpty())
            <p style="opacity:.7">Tidak ditemukan kemungkinan duplikat.</p>
        @endif
        @foreach ($pairs as [$a, $b])
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;padding:1rem 0;border-bottom:1px solid rgba(127,127,127,.2)">
                @foreach ([[$a, $b], [$b, $a]] as [$x, $y])
                    <div style="border:1px solid rgba(127,127,127,.25);border-radius:.5rem;padding:.75rem">
                        <div style="font-weight:600"><a href="{{ \App\Filament\Resources\Members\MemberResource::getUrl('view', ['record' => $x]) }}">{{ $x->name }}</a></div>
                        <div style="font-size:.8rem;opacity:.75">{{ $x->code }} &middot; lahir {{ $x->birth_date?->format('d/m/Y') }} &middot; {{ $x->family?->code ?? 'tanpa KK' }} &middot; {{ $x->membership_label }}</div>
                        <div style="margin-top:.5rem">
                            <x-filament::button size="xs" color="gray" wire:click="merge({{ $x->id }}, {{ $y->id }})"
                                wire:confirm="Pertahankan {{ $x->code }} dan gabungkan {{ $y->code }} ke dalamnya? Tindakan ini tidak bisa dibatalkan.">
                                Pertahankan ini, gabungkan yang lain
                            </x-filament::button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endforeach
    </x-filament::section>
</x-filament-panels::page>
