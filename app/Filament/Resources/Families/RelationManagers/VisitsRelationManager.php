<?php

namespace App\Filament\Resources\Families\RelationManagers;

use App\Filament\Resources\Visits\VisitResource;
use App\Models\Visit;
use App\Support\Opsi;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** VS-05: riwayat kunjungan per KK. */
class VisitsRelationManager extends RelationManager
{
    protected static string $relationship = 'visits';

    protected static ?string $title = 'Riwayat Kunjungan';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('planned_date', 'desc')
            ->columns([
                TextColumn::make('planned_date')->label('Rencana')->date('d/m/Y'),
                TextColumn::make('realized_date')->label('Realisasi')->date('d/m/Y'),
                TextColumn::make('type')->label('Jenis')->formatStateUsing(fn ($state) => Opsi::label(Opsi::JENIS_VISITASI, $state)),
                TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => Opsi::label(Opsi::STATUS_VISITASI, $state))
                    ->color(fn ($state) => match ($state) {
                        'selesai' => 'success', 'batal' => 'gray', default => 'warning'
                    }),
                TextColumn::make('team.name')->label('Tim')->badge(),
            ])
            ->headerActions([
                Action::make('jadwal')->label('Jadwalkan kunjungan')->icon(Heroicon::OutlinedCalendar)
                    ->visible(fn () => auth()->user()->can('create', Visit::class))
                    ->url(fn () => VisitResource::getUrl('create', ['family_id' => $this->getOwnerRecord()->id])),
            ])
            ->recordActions([
                Action::make('lihat')->label('Lihat')->icon(Heroicon::OutlinedEye)
                    ->url(fn ($record) => VisitResource::getUrl('view', ['record' => $record])),
            ]);
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()->can('viewAny', Visit::class);
    }
}
