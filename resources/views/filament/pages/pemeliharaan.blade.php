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

    @if ($output)
        <x-filament::section heading="Hasil perintah terakhir">
            <pre style="white-space:pre-wrap;font-size:.8rem;max-height:28rem;overflow:auto">{{ $output }}</pre>
        </x-filament::section>
    @endif
</x-filament-panels::page>
