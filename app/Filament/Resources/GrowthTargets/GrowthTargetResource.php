<?php

namespace App\Filament\Resources\GrowthTargets;

use App\Filament\Resources\GrowthTargets\Pages\ManageGrowthTargets;
use App\Models\GrowthTarget;
use App\Services\GrowthService;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** PJ-05: target pertumbuhan per tahun. */
class GrowthTargetResource extends Resource
{
    protected static ?string $model = GrowthTarget::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static string|\UnitEnum|null $navigationGroup = 'Laporan';

    protected static ?string $slug = 'target-pertumbuhan';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Target Pertumbuhan';

    protected static ?string $pluralModelLabel = 'Target Pertumbuhan';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('year')->label('Tahun')->numeric()->required()->default(now()->year)->unique(ignoreRecord: true),
            TextInput::make('target_new_members')->label('Target anggota baru')->numeric()->required()->minValue(0),
            Textarea::make('notes')->label('Catatan')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('year', 'desc')
            ->columns([
                TextColumn::make('year')->label('Tahun'),
                TextColumn::make('target_new_members')->label('Target'),
                TextColumn::make('realisasi')->label('Realisasi')
                    ->state(fn (GrowthTarget $t) => app(GrowthService::class)->newMembersInYear($t->year)),
                TextColumn::make('progres')->label('Progres')
                    ->state(fn (GrowthTarget $t) => $t->target_new_members
                        ? round(app(GrowthService::class)->newMembersInYear($t->year) / $t->target_new_members * 100).'%' : '-'),
                TextColumn::make('notes')->label('Catatan')->limit(50),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageGrowthTargets::route('/')];
    }
}
