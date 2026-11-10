<?php

namespace App\Filament\Resources\Members\RelationManagers;

use App\Models\OrgPosition;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Jabatan jemaat di struktur organisasi (semua periode). */
class OrgPositionsRelationManager extends RelationManager
{
    protected static string $relationship = 'orgPositions';

    protected static ?string $title = 'Jabatan';

    protected static ?string $modelLabel = 'jabatan';

    public function isReadOnly(): bool
    {
        return true;
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $n = $ownerRecord->orgPositions()->whereHas('unit.period', fn ($q) => $q->where('is_active', true))->count();

        return $n ? (string) $n : null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('unit.period', 'unit.parent')
                ->join('org_units', 'org_units.id', '=', 'org_positions.org_unit_id')
                ->join('org_periods', 'org_periods.id', '=', 'org_units.org_period_id')
                ->select('org_positions.*')->orderByDesc('org_periods.start_date')->orderBy('org_units.sort'))
            ->emptyStateHeading('Belum memegang jabatan di struktur organisasi')
            ->columns([
                TextColumn::make('role_label')->label('Jabatan')->weight('bold')
                    ->description(fn (OrgPosition $r) => $r->note),
                TextColumn::make('unit.name')->label('Unit')
                    ->description(fn (OrgPosition $r) => $r->unit->parent ? 'di bawah '.$r->unit->parent->name : $r->unit->type_label),
                TextColumn::make('unit.period.name')->label('Periode'),
                IconColumn::make('aktif')->label('Aktif')->boolean()->state(fn (OrgPosition $r) => (bool) $r->unit->period->is_active),
            ]);
    }
}
