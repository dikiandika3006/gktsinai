<x-filament-panels::page>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&display=swap" rel="stylesheet">
    <style>
        .kal { --bg:#14213d; --kartu:#1d2c4f; --garis:#2a3a62; --teks:#e8ecf5; --redup:#9aa6c2; --emas:#f2b84b; --teal:#4fc4b6;
               background:var(--bg); color:var(--teks); border-radius:1rem; padding:2rem 1.75rem; font-size:.9rem; }
        .kal h1, .kal h2 { font-family:'DM Serif Display', Georgia, serif; font-weight:400; margin:0; line-height:1.1; }
        .kal h1 { font-size:clamp(2rem, 5vw, 3.2rem); }
        .kal h2 { font-size:1.5rem; margin-bottom:.25rem; }
        .kal .kecil { color:var(--redup); font-size:.8rem; }
        .kal .eyebrow { color:var(--emas); font-size:.72rem; letter-spacing:.14em; text-transform:uppercase; font-weight:600; margin-bottom:.6rem; }
        .kal section { margin-top:2.25rem; }
        .kal .kartu4 { display:grid; grid-template-columns:repeat(auto-fit, minmax(9.5rem, 1fr)); gap:.8rem; margin-top:1.5rem; }
        .kal .kartu { background:var(--kartu); border-radius:.75rem; padding:1.1rem 1.2rem; }
        .kal .kartu b { display:block; font-family:'DM Serif Display', Georgia, serif; font-size:2.4rem; font-weight:400; line-height:1; margin-bottom:.5rem; }
        .kal .kartu.emas { background:var(--emas); color:#1d2433; }
        .kal .kartu.teal { background:var(--teal); color:#10302c; }
        .kal .kolom { display:flex; align-items:flex-end; gap:.45rem; height:11rem; margin-top:1rem; }
        .kal .kolom > div { flex:1; display:flex; flex-direction:column; justify-content:flex-end; align-items:center; height:100%; }
        .kal .kolom .bar { width:100%; background:var(--emas); border-radius:.25rem .25rem 0 0; min-height:2px; }
        .kal .kolom .angka { font-weight:700; margin-bottom:.3rem; }
        .kal .kolom .bln { color:var(--redup); font-size:.72rem; margin-top:.4rem; }
        .kal .baris { display:grid; grid-template-columns:minmax(7rem, 11rem) 1fr 2.2rem; align-items:center; gap:.75rem; margin:.45rem 0; }
        .kal .lintasan { background:var(--garis); border-radius:999px; height:.85rem; overflow:hidden; }
        .kal .lintasan > span { display:block; height:100%; border-radius:999px; }
        .kal .baris .n { text-align:right; font-weight:700; }
        .kal .tumpuk { display:flex; border-radius:.5rem; overflow:hidden; margin-top:.35rem; }
        .kal .tumpuk > div { padding:.45rem .3rem; text-align:center; line-height:1.1; }
        .kal .tumpuk b { display:block; font-size:1.15rem; }
        .kal .tumpuk small { font-size:.68rem; opacity:.85; }
        .kal .tabel td { padding:.35rem .6rem; border-bottom:1px solid var(--garis); }
        .kal .pilih { display:flex; flex-wrap:wrap; gap:.6rem; align-items:end; }
        .kal .pilih select { background:var(--kartu); color:var(--teks); border:1px solid var(--garis); border-radius:.5rem; padding:.4rem 2rem .4rem .6rem; font-size:.85rem; }
        @media (max-width: 640px) { .kal { padding:1.25rem 1rem; } .kal .baris { grid-template-columns:6.5rem 1fr 2rem; } }
    </style>

    @php
        $warna = ['a' => 'var(--emas)', 'b' => 'var(--teal)'];
        $nuansa = ['a' => ['#6f5a2a', '#a8873f', '#f2b84b', '#ffd98a'], 'b' => ['#2b5560', '#3a7c80', '#45a39b', '#4fc4b6']];
        $teksTumpuk = ['a' => ['#f5e7c4', '#1d2433', '#1d2433', '#1d2433'], 'b' => ['#d6efec', '#e8f7f5', '#10302c', '#10302c']];
    @endphp

    <div class="kal">
        <div class="pilih" style="justify-content:flex-end;margin-bottom:1rem">
            <label class="kecil">Tahun<br>
                <select wire:model.live="tahun">@foreach ($opsiTahun as $y)<option value="{{ $y }}">{{ $y }}</option>@endforeach</select></label>
            <label class="kecil">Peran 1<br>
                <select wire:model.live="peranA">@foreach ($opsiPeran as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></label>
            <label class="kecil">Peran 2<br>
                <select wire:model.live="peranB">@foreach ($opsiPeran as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></label>
        </div>

        <div class="eyebrow">Rekap Pelayanan K.U. · Jan–Des {{ $tahun }}</div>
        <h1>{{ $a['label'] }} &amp; {{ $b['label'] }}<br>sepanjang {{ $tahun }}</h1>
        <p class="kecil" style="font-size:.9rem;margin-top:.75rem;max-width:38rem">Siapa melayani, berapa kali, dan seberapa merata bebannya, dihitung dari jadwal {{ $ibadah }} ibadah yang sudah terisi.</p>

        <div class="kartu4">
            <div class="kartu"><b>{{ $ibadah }}</b>ibadah terisi</div>
            <div class="kartu emas"><b>{{ $a['tugas'] }}</b>penugasan {{ $a['label'] }} oleh {{ $a['orang'] }} orang</div>
            <div class="kartu teal"><b>{{ $b['tugas'] }}</b>penugasan {{ $b['label'] }} oleh {{ $b['orang'] }} orang</div>
            <div class="kartu"><b>{{ $duaPeran }}</b>orang melayani di dua peran</div>
        </div>

        <section>
            <h2>Ibadah per bulan</h2>
            <div class="kecil">{{ $catatanBulan ?: 'Jumlah ibadah (KU I dan KU II dihitung terpisah, ibadah gabungan dihitung satu).' }}</div>
            <div class="kolom">
                @foreach ($perBulan as $bln => $n)
                    <div>
                        <span class="angka">{{ $n ?: '' }}</span>
                        <span class="bar" style="height:{{ $n / $maksBulan * 80 }}%"></span>
                        <span class="bln">{{ $bulanLabel[$bln - 1] }}</span>
                    </div>
                @endforeach
            </div>
        </section>

        @foreach (['a' => $a, 'b' => $b] as $k => $r)
            <section>
                <h2 style="color:{{ $warna[$k] }}">{{ $r['label'] }}: paling sering bertugas</h2>
                <div class="kecil" style="margin-bottom:.6rem">{{ $r['ringkas'] }}</div>
                @forelse ($r['baris'] as $o)
                    <div class="baris">
                        <span style="{{ ($o['grup'] ?? false) ? 'color:var(--redup)' : '' }}">{{ $o['nama'] }}</span>
                        <span class="lintasan"><span style="width:{{ $o['n'] / $r['maks'] * 100 }}%;background:{{ $warna[$k] }}"></span></span>
                        <span class="n">{{ $o['n'] }}×</span>
                    </div>
                @empty
                    <div class="kecil">Belum ada penugasan.</div>
                @endforelse
                @if ($r['berikut'])
                    <div class="kecil" style="margin-top:.5rem">Peringkat berikutnya: {{ $r['berikut'] }}.</div>
                @endif
            </section>
        @endforeach

        <section>
            <h2>Sebaran beban per orang</h2>
            <div class="kecil">Jumlah orang menurut seberapa sering bertugas.</div>
            @foreach (['a' => $a, 'b' => $b] as $k => $r)
                @continue(! $r['sebaran'])
                <div style="margin-top:1rem;font-size:.72rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:{{ $warna[$k] }}">{{ $r['label'] }} · {{ $r['orang'] }} orang</div>
                <div class="tumpuk">
                    @foreach ($r['sebaran'] as $i => $s)
                        <div style="flex:{{ $s['n'] }};background:{{ $nuansa[$k][$i + 4 - count($r['sebaran'])] }};color:{{ $teksTumpuk[$k][$i + 4 - count($r['sebaran'])] }}">
                            <b>{{ $s['n'] }}</b><small>{{ $s['label'] }}</small>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </section>

        @if ($lain)
            <section>
                <h2>Peran lainnya</h2>
                <div class="kecil">Total {{ $semuaOrang }} orang/keluarga terlibat melayani sepanjang {{ $tahun }}.</div>
                <table class="tabel" style="margin-top:.6rem;border-collapse:collapse;width:100%;max-width:30rem">
                    @foreach ($lain as $r)
                        <tr><td>{{ $r['label'] }}</td><td style="text-align:right"><b>{{ $r['tugas'] }}</b> <span class="kecil">penugasan</span></td><td style="text-align:right"><b>{{ $r['orang'] }}</b> <span class="kecil">orang</span></td></tr>
                    @endforeach
                </table>
            </section>
        @endif
    </div>
</x-filament-panels::page>
