@php($r = $getRecord())
@if ($r->latitude && $r->longitude)
    <div wire:ignore x-data x-init="
        const m = L.map($refs.map).setView([{{ $r->latitude }}, {{ $r->longitude }}], 17);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }).addTo(m);
        L.marker([{{ $r->latitude }}, {{ $r->longitude }}]).addTo(m);
        setTimeout(() => m.invalidateSize(), 300);
    ">
        <div x-ref="map" style="height: 300px; border-radius: .5rem; z-index: 0;"></div>
    </div>
@else
    <p style="opacity:.7">Belum ada koordinat. Edit KK untuk memasang pin manual.</p>
@endif
