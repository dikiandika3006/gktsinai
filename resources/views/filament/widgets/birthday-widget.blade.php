<x-filament-widgets::widget>
    <x-filament::section heading="Ulang tahun minggu ini" icon="heroicon-o-cake">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1.5rem">
            <div>
                <p style="font-weight:600;margin-bottom:.5rem">Ulang tahun ({{ $birthdays->count() }})</p>
                @forelse ($birthdays as $b)
                    <div style="display:flex;justify-content:space-between;padding:.35rem 0;border-bottom:1px solid rgba(127,127,127,.15)">
                        <a href="{{ \App\Filament\Resources\Members\MemberResource::getUrl('view', ['record' => $b['member']]) }}" style="font-weight:500">{{ $b['member']->name }}</a>
                        <span style="opacity:.75">{{ $b['date']->translatedFormat('D, d M') }} &middot; {{ $b['years'] }} th</span>
                    </div>
                @empty
                    <p style="opacity:.6">Tidak ada.</p>
                @endforelse
            </div>
            <div>
                <p style="font-weight:600;margin-bottom:.5rem">Ulang tahun pernikahan ({{ $anniversaries->count() }})</p>
                @forelse ($anniversaries as $b)
                    <div style="display:flex;justify-content:space-between;padding:.35rem 0;border-bottom:1px solid rgba(127,127,127,.15)">
                        <span style="font-weight:500">{{ $b['member']->family?->display_name ?? $b['member']->name }}</span>
                        <span style="opacity:.75">{{ $b['date']->translatedFormat('D, d M') }} &middot; {{ $b['years'] }} th</span>
                    </div>
                @empty
                    <p style="opacity:.6">Tidak ada.</p>
                @endforelse
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
