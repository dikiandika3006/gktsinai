{{-- Grid jadwal seperti format Excel gereja. $jadwal: koleksi WorshipSchedule (assignments.member & alias sudah dimuat). --}}
@php
    use App\Models\ServantAlias;
    use App\Support\Opsi;
    $ku = ['KU I' => 'Ibadah Umum I', 'KU II' => 'Ibadah Umum II'];
    $warna = ['success' => '#16a34a', 'warning' => '#d97706', 'danger' => '#dc2626', 'gray' => '#6b7280'];
    $info = ['tema' => 'Tema', 'ayat_referensi' => 'Ayat Referensi', 'introitus' => 'Introitus', 'phb' => 'PHB'];
    $peranDipakai = collect(Opsi::PERAN_PELAYANAN)->filter(fn ($l, $p) => $p === "pembicara" || $jadwal->contains(fn ($j) => $j->assignments->contains('peran', $p)));
    $chip = function ($a) use ($warna) {
        $label = ServantAlias::statusLabel($a->member, $a->alias?->category);
        $c = $warna[ServantAlias::statusColor($a->member, $a->alias?->category)];
        $nama = e($a->nama);
        $isi = "<span title='".e($label.($a->member ? ' · '.$a->member->name : ''))."' style='display:inline-flex;align-items:center;gap:.3rem;white-space:nowrap'><span style='width:.55rem;height:.55rem;border-radius:50%;background:{$c};flex:none'></span>{$nama}</span>";
        if ($a->member && auth()->user()->can('view', $a->member)) {
            $isi = "<a href='".\App\Filament\Resources\Members\MemberResource::getUrl('view', ['record' => $a->member])."' style='text-decoration:none'>{$isi}</a>";
        }

        return $isi;
    };
    $th = 'padding:.35rem .6rem;border:1px solid rgba(127,127,127,.25);text-align:center;font-weight:600';
    $td = 'padding:.35rem .6rem;border:1px solid rgba(127,127,127,.25);text-align:center;vertical-align:top';
    $lbl = 'padding:.35rem .6rem;border:1px solid rgba(127,127,127,.25);font-weight:600;white-space:nowrap;position:sticky;left:0;background:var(--jadwal-bg)';
@endphp
<style>.jadwal-grid{--jadwal-bg:#fff}.dark .jadwal-grid{--jadwal-bg:#18181b}</style>
<div class="jadwal-grid" style="overflow-x:auto">
    <table style="border-collapse:collapse;font-size:.85rem;min-width:100%">
        <thead>
            <tr style="background:rgba(245,158,11,.18)">
                <th style="{{ $lbl }};background:rgba(245,158,11,.18)" rowspan="2">Tanggal</th>
                @foreach ($jadwal as $j)
                    <th style="{{ $th }}" colspan="2">{{ $j->date->translatedFormat('d F Y') }}</th>
                @endforeach
            </tr>
            <tr style="background:rgba(245,158,11,.1)">
                @foreach ($jadwal as $j)
                    @foreach ($ku as $l)<th style="{{ $th }}">{{ $l }}</th>@endforeach
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($peranDipakai as $peran => $label)
                <tr @if ($loop->odd) style="background:rgba(250,204,21,.08)" @endif>
                    <td style="{{ $lbl }}">{{ $label }}</td>
                    @foreach ($jadwal as $j)
                        @php $semua = $j->assignments->where('peran', $peran); $bersama = $semua->whereNull('kebaktian'); @endphp
                        @if ($semua->isNotEmpty() && $bersama->count() === $semua->count())
                            <td style="{{ $td }}" colspan="2">{!! $bersama->map($chip)->join('<br>') !!}</td>
                        @else
                            @foreach (array_keys($ku) as $k)
                                <td style="{{ $td }}">{!! $semua->filter(fn ($a) => $a->kebaktian === $k || $a->kebaktian === null)->map($chip)->join('<br>') ?: '<span style="opacity:.35">-</span>' !!}</td>
                            @endforeach
                        @endif
                    @endforeach
                </tr>
                @if ($peran === 'pembicara')
                    @foreach ($info as $f => $l)
                        @continue($jadwal->every(fn ($j) => blank($j->{$f})))
                        <tr><td style="{{ $lbl }}">{{ $l }}</td>
                            @foreach ($jadwal as $j)<td style="{{ $td }}" colspan="2">{{ $j->{$f} }}</td>@endforeach
                        </tr>
                    @endforeach
                @endif
            @endforeach
            @unless ($jadwal->every(fn ($j) => blank($j->keterangan)))
                <tr><td style="{{ $lbl }}">Keterangan</td>
                    @foreach ($jadwal as $j)<td style="{{ $td }};font-weight:600" colspan="2">{{ $j->keterangan }}</td>@endforeach
                </tr>
            @endunless
        </tbody>
    </table>
</div>
<div style="display:flex;flex-wrap:wrap;gap:1rem;font-size:.75rem;margin-top:.6rem;opacity:.85">
    @foreach (['success' => 'Anggota', 'warning' => 'Simpatisan', 'danger' => 'Belum terdaftar', 'gray' => 'Pekerja / pelayan tamu'] as $c => $l)
        <span style="display:inline-flex;align-items:center;gap:.3rem"><span style="width:.6rem;height:.6rem;border-radius:50%;background:{{ $warna[$c] }}"></span>{{ $l }}</span>
    @endforeach
</div>
