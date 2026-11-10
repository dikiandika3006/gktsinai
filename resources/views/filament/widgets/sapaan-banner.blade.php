<x-filament-widgets::widget>
    <div class="sinai-banner">
        <div class="sinai-logo"><img src="{{ asset('logo-gkt.png') }}" alt="Logo GKT Sinai Batu"></div>
        <div class="sinai-banner-isi">
            <div style="font-size:.8rem;letter-spacing:.08em;text-transform:uppercase;opacity:.8;font-weight:600">{{ $tanggal }}</div>
            <h2 style="margin-top:.2rem">{{ $salam }}, {{ $nama }} 👋</h2>
            <div class="sinai-sub">Tuhan memberkati pelayanan kita hari ini.</div>

            <div style="display:flex;flex-wrap:wrap;gap:.5rem">
                @if ($minggu)
                    <span class="sinai-pill">
                        <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedMegaphone" style="width:1rem;height:1rem" />
                        {{ $minggu->date->isToday() ? 'Hari ini' : 'Minggu, '.$minggu->date->translatedFormat('j F') }}
                        @if ($minggu->tema) · <b>{{ $minggu->tema }}</b> @endif
                        @if ($pembicara) · {{ $pembicara }} @endif
                    </span>
                @endif
                @if ($ulangTahun)
                    <span class="sinai-pill">
                        <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedCake" style="width:1rem;height:1rem" />
                        {{ $ulangTahun }} jemaat berulang tahun minggu ini
                    </span>
                @endif
            </div>

            @if ($aksi)
                <div class="sinai-aksi">
                    @foreach ($aksi as [$label, $url, $icon, $emas, $tabBaru])
                        <a href="{{ $url }}" @class(['sinai-emas' => $emas]) @if ($tabBaru) target="_blank" rel="noopener" @endif>
                            <x-filament::icon :icon="$icon" />
                            {{ $label }}
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-filament-widgets::widget>
