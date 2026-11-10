@php
    $prefix = \Illuminate\Support\Str::beforeLast($getStatePath(), '.');
    $others = \App\Models\Region::query()->whereNotNull('area_geojson')
        ->when($getRecord(), fn ($q, $r) => $q->whereKeyNot($r->id))
        ->get(['name', 'map_color', 'area_geojson'])
        ->map(fn ($r) => ['name' => $r->name, 'color' => $r->map_color, 'geo' => $r->area_geojson]);
    $pins = \App\Models\Family::query()->whereNotNull('latitude')
        ->with('region:id,name,map_color')->get(['id', 'code', 'latitude', 'longitude', 'region_id'])
        ->map(fn ($f) => [$f->latitude, $f->longitude, $f->region?->map_color ?? '#6b7280', $f->code.' ('.($f->region?->name ?? 'tanpa wilayah').')']);
@endphp
<div wire:ignore x-data="{
    init() {
        const map = L.map(this.$refs.map).setView(@js(config('sij.map_center')), 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }).addTo(map);
        @js($others).forEach(o => L.geoJSON(o.geo, { style: { color: o.color, weight: 1, fillOpacity: .08 } }).bindTooltip(o.name).addTo(map));
        @js($pins).forEach(p => L.circleMarker([p[0], p[1]], { radius: 4, color: p[2], fillOpacity: .9 }).bindTooltip(p[3]).addTo(map));
        const drawn = new L.FeatureGroup().addTo(map);
        let geo = $wire.get('{{ $prefix }}.area_geojson');
        if (typeof geo === 'string' && geo) { try { geo = JSON.parse(geo); } catch (e) { geo = null; } }
        if (geo) {
            L.geoJSON(geo).eachLayer(l => drawn.addLayer(l));
            if (drawn.getLayers().length) map.fitBounds(drawn.getBounds());
        }
        map.addControl(new L.Control.Draw({
            draw: { polygon: { allowIntersection: false, showArea: false }, rectangle: true, polyline: false, circle: false, marker: false, circlemarker: false },
            edit: { featureGroup: drawn }
        }));
        const save = () => {
            const fc = drawn.toGeoJSON();
            const polys = fc.features.map(f => f.geometry).filter(g => g.type === 'Polygon');
            const value = polys.length === 0 ? null : (polys.length === 1 ? polys[0] : { type: 'MultiPolygon', coordinates: polys.map(p => p.coordinates) });
            $wire.set('{{ $prefix }}.area_geojson', value, false);
        };
        map.on(L.Draw.Event.CREATED, e => { drawn.addLayer(e.layer); save(); });
        map.on(L.Draw.Event.EDITED, save);
        map.on(L.Draw.Event.DELETED, save);
        setTimeout(() => map.invalidateSize(), 300);
    }
}">
    <div x-ref="map" style="height: 460px; border-radius: .5rem; z-index: 0;"></div>
    <p style="font-size:.8rem;opacity:.7;margin-top:.4rem">Titik kecil = lokasi KK (warna = wilayah saat ini). Gunakan alat poligon di kiri atas; simpan formulir setelah selesai.</p>
</div>
