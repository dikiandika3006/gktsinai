@php
    $prefix = \Illuminate\Support\Str::beforeLast($getStatePath(), '.');
    $center = config('sij.map_center');
@endphp
<div
    wire:ignore
    x-data="{
        map: null, marker: null,
        init() {
            const lat = $wire.get('{{ $prefix }}.latitude');
            const lng = $wire.get('{{ $prefix }}.longitude');
            const has = lat && lng;
            this.map = L.map(this.$refs.map).setView(has ? [lat, lng] : @js($center), has ? 17 : 13);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }).addTo(this.map);
            if (has) this.place(lat, lng);
            @if ($editable)
            this.map.on('click', (e) => { this.place(e.latlng.lat, e.latlng.lng); this.sync(); });
            @endif
            $wire.$watch('{{ $prefix }}.latitude', () => this.fromState());
            $wire.$watch('{{ $prefix }}.longitude', () => this.fromState());
            setTimeout(() => this.map.invalidateSize(), 300);
        },
        fromState() {
            const lat = parseFloat($wire.get('{{ $prefix }}.latitude')), lng = parseFloat($wire.get('{{ $prefix }}.longitude'));
            if (!isNaN(lat) && !isNaN(lng)) { this.place(lat, lng); this.map.setView([lat, lng]); }
        },
        place(lat, lng) {
            if (!this.marker) {
                this.marker = L.marker([lat, lng], { draggable: @js($editable) }).addTo(this.map);
                this.marker.on('dragend', () => this.sync());
            } else { this.marker.setLatLng([lat, lng]); }
        },
        sync() {
            const p = this.marker.getLatLng();
            $wire.set('{{ $prefix }}.latitude', Number(p.lat.toFixed(7)), false);
            $wire.set('{{ $prefix }}.longitude', Number(p.lng.toFixed(7)), false);
            $wire.set('{{ $prefix }}.geocode_status', 'manual', false);
        }
    }"
>
    <div x-ref="map" style="height: 360px; border-radius: .5rem; z-index: 0;"></div>
    @if ($editable)
        <p style="font-size:.8rem;opacity:.7;margin-top:.4rem">Klik peta atau geser pin untuk menetapkan lokasi rumah (status menjadi "Pin manual").</p>
    @endif
</div>
