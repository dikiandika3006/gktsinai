@if ($getRecord()->family)
    @include('filament.keluarga.anggota', ['family' => $getRecord()->family, 'current' => $getRecord()])
@else
    <div style="opacity:.6">Belum masuk KK. Gunakan tombol <b>Keluarga → Pindah KK</b> untuk memasukkan ke KK yang ada atau membuat KK baru.</div>
@endif
