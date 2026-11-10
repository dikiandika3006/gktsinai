<?php

namespace App\Filament\Resources\Members\RelationManagers;

use App\Models\ServiceAssignment;
use App\Support\Opsi;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Riwayat tugas pelayanan jemaat di jadwal ibadah. */
class ServiceAssignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'serviceAssignments';

    protected static ?string $title = 'Pelayanan';

    protected static ?string $modelLabel = 'tugas';

    public function isReadOnly(): bool
    {
        return true;
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $n = $ownerRecord->serviceAssignments()->count();

        return $n ? (string) $n : null;
    }

    public function table(Table $table): Table
    {
        $tahunIni = $this->getOwnerRecord()->serviceAssignments()
            ->whereHas('schedule', fn ($q) => $q->whereYear('date', now()->year))->count();
        $berikut = $this->getOwnerRecord()->serviceAssignments()->with('schedule')
            ->whereHas('schedule', fn ($q) => $q->whereDate('date', '>=', today()))
            ->get()->sortBy('schedule.date')->first();

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('schedule')
                ->join('worship_schedules', 'worship_schedules.id', '=', 'service_assignments.worship_schedule_id')
                ->select('service_assignments.*')->orderByDesc('worship_schedules.date'))
            ->description("Tahun ini: {$tahunIni} tugas".($berikut
                ? ' · tugas berikutnya: '.$berikut->schedule->date->format('d/m/Y').' ('.$berikut->peran_label.')' : ''))
            ->emptyStateHeading('Belum pernah tercatat bertugas')
            ->columns([
                TextColumn::make('schedule.date')->label('Tanggal')->date('D, d/m/Y'),
                TextColumn::make('kebaktian')->label('Kebaktian')->placeholder('Semua'),
                TextColumn::make('peran')->label('Peran')->badge()->formatStateUsing(fn ($state) => Opsi::label(Opsi::PERAN_PELAYANAN, $state)),
                TextColumn::make('nama')->label('Tertulis di jadwal')->color('gray'),
                TextColumn::make('schedule.tema')->label('Tema')->limit(40)->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('peran')->label('Peran')->options(Opsi::PERAN_PELAYANAN),
            ])
            ->paginated([10, 25, 50]);
    }
}
