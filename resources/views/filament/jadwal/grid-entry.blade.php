@include('filament.jadwal.grid', ['jadwal' => collect([$getRecord()->loadMissing(['assignments.member', 'assignments.alias'])])])
