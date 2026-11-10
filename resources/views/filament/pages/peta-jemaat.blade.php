<x-filament-panels::page>
    <x-filament::section compact>
        <div style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:end">
            <label style="font-size:.8rem;min-width:180px">Wilayah<br>
                <x-filament::input.wrapper>
                    <select wire:model.live="wilayah" multiple size="1" class="fi-select-input" style="min-height:2.25rem">
                        @foreach ($regions as $r)
                            <option value="{{ $r->id }}">{{ $r->name }}</option>
                        @endforeach
                    </select>
                </x-filament::input.wrapper>
            </label>
            <label style="font-size:.8rem">Keanggotaan<br>
                <x-filament::input.wrapper><x-filament::input.select wire:model.live="keanggotaan">
                    <option value="">Semua</option>
                    @foreach ($opsiKeanggotaan as $k => $v) @if (! in_array($k, ['keluar','meninggal'])) <option value="{{ $k }}">{{ $v }}</option> @endif @endforeach
                </x-filament::input.select></x-filament::input.wrapper></label>
            <label style="font-size:.8rem">Keaktifan<br>
                <x-filament::input.wrapper><x-filament::input.select wire:model.live="keaktifan">
                    <option value="">Semua</option>
                    @foreach ($opsiKeaktifan as $k => $v) <option value="{{ $k }}">{{ $v }}</option> @endforeach
                </x-filament::input.select></x-filament::input.wrapper></label>
            <label style="font-size:.8rem">Komisi<br>
                <x-filament::input.wrapper><x-filament::input.select wire:model.live="komisi">
                    <option value="">Semua</option>
                    @foreach ($komisiOpsi as $k => $v) <option value="{{ $k }}">{{ $v }}</option> @endforeach
                </x-filament::input.select></x-filament::input.wrapper></label>
            <label style="font-size:.8rem">Kelompok umur<br>
                <x-filament::input.wrapper><x-filament::input.select wire:model.live="umur">
                    <option value="">Semua</option>
                    @foreach ($opsiUmur as $k => $v) <option value="{{ $k }}">{{ $v }}</option> @endforeach
                </x-filament::input.select></x-filament::input.wrapper></label>
        </div>
        @if ($tanpaKoordinat)
            <p style="font-size:.8rem;color:#b45309;margin-top:.6rem">{{ $tanpaKoordinat }} KK belum punya koordinat:
                <a style="text-decoration:underline" href="{{ \App\Filament\Resources\Families\FamilyResource::getUrl('index', ['filters' => ['geocode_status' => ['value' => 'gagal']]]) }}">pin manual</a>.</p>
        @endif
    </x-filament::section>

    <script>
    window.petaJemaat = (initial, regions, center) => ({
        map: null, layers: {},
        init() {
            const map = this.map = L.map(this.$refs.map).setView(center, 13);
            const osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }).addTo(map);
            this.layers.pins = L.layerGroup().addTo(map);
            this.layers.cluster = L.markerClusterGroup();
            this.layers.heat = L.heatLayer([], { radius: 25, blur: 18 });
            this.layers.attention = L.layerGroup();
            this.layers.areas = L.layerGroup().addTo(map);
            regions.forEach(r => { if (r.area_geojson) L.geoJSON(r.area_geojson, { style: { color: r.map_color, weight: 2, fillOpacity: .07 } }).bindTooltip(r.name).addTo(this.layers.areas); });
            L.control.layers({ 'OpenStreetMap': osm }, {
                'Pin per KK': this.layers.pins, 'Cluster': this.layers.cluster, 'Heatmap kepadatan': this.layers.heat,
                'Perlu perhatian': this.layers.attention, 'Batas wilayah': this.layers.areas,
            }, { collapsed: false }).addTo(map);
            this.render(initial, true);
            window.addEventListener('peta-data', e => this.render(e.detail.data, false));
            setTimeout(() => map.invalidateSize(), 300);
        },
        esc(s) { const d = document.createElement('div'); d.textContent = String(s ?? ''); return d.innerHTML; },
        popup(p) {
            return `<div style='min-width:200px'><b>${this.esc(p.code)} &middot; ${this.esc(p.head)}</b><br><span style='opacity:.7'>${this.esc(p.region)}</span><br>`
                + (p.address ? `${this.esc(p.address)}<br>` : '') + (p.phone ? `HP: ${this.esc(p.phone)}<br>` : '')
                + `<div style='margin:.3rem 0'>${p.members.map(m => this.esc(m)).join('<br>')}</div>`
                + `Terakhir dikunjungi: ${p.last_visit ?? 'belum pernah'}<br>`
                + (p.attention ? `<span style='color:#dc2626'>${this.esc(p.attention_reason)}</span><br>` : '')
                + `<a href='${p.url}' style='color:#4f46e5;text-decoration:underline'>Buka profil KK</a></div>`;
        },
        render(points, fit) {
            ['pins', 'cluster', 'attention'].forEach(k => this.layers[k].clearLayers());
            const heat = [];
            points.forEach(p => {
                const mk = () => L.circleMarker([p.lat, p.lng], { radius: 7, color: '#fff', weight: 1.5, fillColor: p.color, fillOpacity: .95 }).bindPopup(this.popup(p));
                this.layers.pins.addLayer(mk());
                this.layers.cluster.addLayer(L.marker([p.lat, p.lng]).bindPopup(this.popup(p)));
                heat.push([p.lat, p.lng, Math.max(1, p.members.length)]);
                if (p.attention) this.layers.attention.addLayer(L.circleMarker([p.lat, p.lng], { radius: 11, color: '#dc2626', weight: 3, fill: false }).bindPopup(this.popup(p)));
            });
            this.layers.heat.setLatLngs(heat);
            this.$refs.count.textContent = points.length;
            if (fit && points.length) this.map.fitBounds(L.latLngBounds(points.map(p => [p.lat, p.lng])).pad(0.1));
        }
    });
    </script>
    <div wire:ignore x-data="petaJemaat(@js($initial), @js($regions), @js($center))">
        <div x-ref="map" style="height: calc(100vh - 290px); min-height: 460px; border-radius: .75rem; z-index: 0;"></div>
        <p style="font-size:.8rem;opacity:.7;margin-top:.5rem"><span x-ref="count"></span> KK ditampilkan. Warna pin = wilayah. Gunakan kontrol lapisan di kanan atas untuk cluster, heatmap, dan lapisan "perlu perhatian".</p>
    </div>

    <x-filament::section compact heading="Legenda wilayah">
        <div style="display:flex;flex-wrap:wrap;gap:.9rem;font-size:.8rem">
            @foreach ($regions as $r)
                <span style="display:inline-flex;align-items:center;gap:.35rem"><span style="width:.8rem;height:.8rem;border-radius:50%;background:{{ $r->map_color }}"></span>{{ $r->name }}</span>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-panels::page>
