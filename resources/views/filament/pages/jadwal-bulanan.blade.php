<x-filament-panels::page>
    <div style="display:flex;flex-wrap:wrap;gap:.6rem;align-items:center">
        <x-filament::button color="gray" size="sm" icon="heroicon-m-chevron-left" wire:click="geser(-1)">Sebelumnya</x-filament::button>
        <x-filament::input.wrapper style="width:11rem">
            <x-filament::input type="month" wire:model.live="bulan" />
        </x-filament::input.wrapper>
        <x-filament::button color="gray" size="sm" icon="heroicon-m-chevron-right" icon-position="after" wire:click="geser(1)">Berikutnya</x-filament::button>
        <span style="flex:1"></span>
        <x-filament::button color="gray" size="sm" tag="a" :href="$urlDaftar">Daftar jadwal</x-filament::button>
        @if ($bisaKelola)
            <x-filament::button size="sm" tag="a" :href="$urlBaru" icon="heroicon-m-plus">Jadwal baru</x-filament::button>
        @endif
    </div>

    <x-filament::section :heading="'Bulan: '.$judul">
        @if ($jadwal->isEmpty())
            <p style="opacity:.7">Belum ada jadwal di bulan ini.</p>
        @else
            @include('filament.jadwal.grid', ['jadwal' => $jadwal])
            <div style="display:flex;flex-wrap:wrap;gap:.5rem;margin-top:.8rem;font-size:.8rem">
                @foreach ($jadwal as $j)
                    <a href="{{ \App\Filament\Resources\WorshipSchedules\WorshipScheduleResource::getUrl($bisaKelola ? 'edit' : 'view', ['record' => $j]) }}"
                       style="text-decoration:underline;opacity:.8">{{ $bisaKelola ? 'Ubah' : 'Lihat' }} {{ $j->date->format('d/m') }}</a>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
