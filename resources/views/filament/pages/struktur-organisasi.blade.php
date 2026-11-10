<x-filament-panels::page>
    <style>
        .org { --garis:#5b8fd6; --kotak:#fff; --teks:#1f2937; --redup:#6b7280; --lembut:#eef4fd; font-size:.85rem; color:var(--teks); }
        .dark .org { --kotak:#18181b; --teks:#e5e7eb; --redup:#9ca3af; --lembut:#1e2a3d; }
        .org .judul { text-align:center; margin-bottom:1.25rem; }
        .org .judul h2 { font-size:1.35rem; font-weight:800; letter-spacing:.04em; color:#1e40af; text-transform:uppercase; }
        .dark .org .judul h2 { color:#93b4f5; }
        .org .kotak { background:var(--kotak); border:1.5px solid var(--garis); border-radius:.35rem; padding:.55rem .7rem; text-align:center; }
        .org .kotak .nama { font-weight:700; font-size:.95rem; line-height:1.2; }
        .org .kotak .org-orang { margin-top:.3rem; font-size:.78rem; line-height:1.35; }
        .org .kotak .org-orang span { display:block; }
        .org .kotak .org-orang i { font-style:normal; color:var(--redup); }
        .org .belum { border-bottom:1px dotted #d97706; }
        .org .atas { display:grid; grid-template-columns:1fr 1.2fr 1fr; gap:1.5rem; align-items:start; }
        .org .penghubung { display:flex; align-items:center; justify-content:center; gap:.4rem; color:var(--redup); font-size:.72rem; }
        .org .kolom { display:grid; grid-template-columns:repeat(auto-fit, minmax(10.5rem, 1fr)); gap:.8rem; align-items:start; }
        .org .bidang-wrap { border-top:2px solid var(--garis); padding-top:1rem; margin-top:1rem; display:grid; grid-template-columns:1fr minmax(13rem, 15rem); gap:1.25rem; }
        .org .sub { margin:.45rem 0 0 .9rem; padding-left:.7rem; border-left:1.5px solid var(--garis); display:flex; flex-direction:column; gap:.4rem; }
        .org .sub .kotak { padding:.4rem .5rem; font-size:.76rem; text-align:left; background:var(--lembut); }
        .org .sub .kotak .nama { font-size:.78rem; font-weight:600; }
        .org .komisi { display:flex; flex-direction:column; gap:.55rem; }
        .org .label-kolom { font-weight:700; text-align:center; margin-bottom:.5rem; }
        .org table.ringkas { width:100%; border-collapse:collapse; font-size:.82rem; }
        .org table.ringkas td, .org table.ringkas th { padding:.4rem .5rem; border-bottom:1px solid rgba(127,127,127,.2); text-align:left; vertical-align:top; }
        @media (max-width: 900px) { .org .atas, .org .bidang-wrap { grid-template-columns:1fr; } }
        @media print {
            .fi-sidebar, .fi-topbar, .fi-header-actions, .no-print { display:none !important; }
            .fi-main { padding:0 !important; } .org { font-size:9pt; }
        }
    </style>

    @php
        // Pengurus per unit; anggota digabung satu baris. $singkat (kotak komisi): MP = Majelis Pendamping, HT = Hamba Tuhan.
        $orang = function ($unit, bool $singkat = false) {
            $nama = function ($p) {
                $n = e($p->display_name);
                $n = $p->member_id ? $n : "<u class='belum' title='Belum dihubungkan ke data jemaat'>{$n}</u>";

                return $n.($p->note ? ' <i>('.e($p->note).')</i>' : '');
            };
            $baris = $unit->positions->where('role', '!=', 'anggota')->map(function ($p) use ($nama, $singkat) {
                $label = match (true) {
                    $singkat && $p->role === 'majelis_pendamping' => 'MP',
                    $singkat && $p->role === 'hamba_tuhan' => 'HT',
                    ! $singkat && in_array($p->role, ['penatua_pj', 'majelis_pendamping', 'penatua'], true) => null,
                    default => $p->role_label,
                };

                return '<span>'.($label ? '<i>'.e($label).':</i> ' : '').$nama($p).'</span>';
            });
            $anggota = $unit->positions->where('role', 'anggota');
            if ($anggota->isNotEmpty()) {
                $baris->push('<span style="margin-top:.2rem"><i>Anggota:</i> '.$anggota->map($nama)->join(', ').'</span>');
            }

            return $baris->join('');
        };
    @endphp

    <div class="no-print" style="display:flex;flex-wrap:wrap;gap:.6rem;align-items:center">
        <x-filament::input.wrapper style="min-width:12rem">
            <x-filament::input.select wire:model.live="periode">
                @foreach ($periodeOpsi as $p)
                    <option value="{{ $p->id }}">Periode {{ $p->name }}{{ $p->is_active ? ' (aktif)' : '' }}</option>
                @endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>
        <span style="font-size:.78rem;opacity:.7">Nama bergaris titik oranye = belum dihubungkan ke data jemaat{{ $bisaKelola ? ' (hubungkan lewat Kelola struktur)' : '' }}.</span>
    </div>

    @if (! $periodeAktif)
        <x-filament::section><p style="opacity:.7">Belum ada periode. {{ $bisaKelola ? 'Klik "Periode baru" untuk memulai.' : '' }}</p></x-filament::section>
    @else
        <x-filament::section>
            <div class="org">
                <div class="judul">
                    <h2>Struktur Pertanggungjawaban Pelayanan</h2>
                    <div style="font-weight:600">{{ config('sij.church_name') }} · Periode {{ $periodeAktif->name }}</div>
                </div>

                <div class="atas">
                    <div style="display:flex;flex-direction:column;gap:.5rem">
                        @foreach ($sekretariat as $s)
                            <div class="kotak"><div class="nama">{{ $s->name }}</div><div class="org-orang">{!! $orang($s) !!}</div></div>
                            @if ($s->children->isNotEmpty())
                                <div class="sub">@foreach ($s->children as $c)<div class="kotak"><div class="nama">{{ $c->name }}</div>@if ($c->positions->isNotEmpty())<div class="org-orang">{!! $orang($c) !!}</div>@endif</div>@endforeach</div>
                            @endif
                        @endforeach
                    </div>
                    <div>
                        @if ($majelis)
                            <div class="kotak" style="border-width:2px">
                                <div class="nama" style="font-size:1.1rem">{{ $majelis->name }}</div>
                                <div class="org-orang">
                                    @foreach ($majelis->positions->where('role', 'ketua_majelis') as $p)
                                        <span><i>Ketua:</i> {{ $p->display_name }}</span>
                                    @endforeach
                                    <span style="margin-top:.25rem"><i>{{ $majelis->positions->where('role', 'penatua')->count() }} Penatua</i></span>
                                </div>
                            </div>
                        @endif
                        <div class="penghubung" style="margin-top:.5rem">— garis pertanggungjawaban ke bidang & komisi —</div>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:.5rem">
                        @foreach ($pimpinanLain as $u)
                            <div class="kotak"><div class="nama">{{ $u->name }}</div><div class="org-orang">{!! $orang($u) !!}</div></div>
                        @endforeach
                        @if ($pimpinanLain->isNotEmpty())<div class="penghubung">⇠ konsultatif ⇢ Rapat Majelis</div>@endif
                    </div>
                </div>

                <div class="bidang-wrap">
                    <div>
                        <div class="label-kolom">Bidang Pelayanan</div>
                        <div class="kolom">
                            @foreach ($bidang as $b)
                                <div>
                                    <div class="kotak"><div class="nama">{{ $b->name }}</div><div class="org-orang">{!! $orang($b) !!}</div></div>
                                    @if ($b->children->isNotEmpty())
                                        <div class="sub">
                                            @foreach ($b->children as $c)
                                                <div class="kotak"><div class="nama">{{ $c->name }}</div>@if ($c->positions->isNotEmpty())<div class="org-orang">{!! $orang($c) !!}</div>@endif</div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div>
                        <div class="label-kolom">Komisi-komisi Kategorial</div>
                        <div class="komisi">
                            @foreach ($komisi as $k)
                                <div class="kotak">
                                    <div class="nama">{{ $k->name }}</div>
                                    <div class="org-orang">
                                        {!! $orang($k, true) !!}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </x-filament::section>

        @if ($penatua->isNotEmpty())
            <x-filament::section heading="Penatua dan tanggung jawabnya" description="Bidang yang dipegang sebagai penanggung jawab dan komisi yang didampingi (MP = Majelis Pendamping).">
                <div class="org">
                    <table class="ringkas">
                        <thead><tr><th>Penatua</th><th style="width:3rem;text-align:center">Tugas</th><th>Tanggung jawab</th></tr></thead>
                        <tbody>
                            @foreach ($penatua as $r)
                                <tr>
                                    <td style="font-weight:600;white-space:nowrap">
                                        @if ($r['posisi']->member)
                                            <a href="{{ \App\Filament\Resources\Members\MemberResource::getUrl('view', ['record' => $r['posisi']->member]) }}" style="color:#4f46e5">{{ $r['posisi']->display_name }}</a>
                                        @else
                                            <u class="belum" title="Belum dihubungkan ke data jemaat">{{ $r['posisi']->display_name }}</u>
                                        @endif
                                    </td>
                                    <td style="text-align:center;font-weight:700">{{ count($r['tugas']) }}</td>
                                    <td>{{ implode(' · ', $r['tugas']) ?: '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif
    @endif
</x-filament-panels::page>
