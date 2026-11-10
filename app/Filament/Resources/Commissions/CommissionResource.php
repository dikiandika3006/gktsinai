<?php

namespace App\Filament\Resources\Commissions;

use App\Filament\Resources\Commissions\Pages\CreateCommission;
use App\Filament\Resources\Commissions\Pages\EditCommission;
use App\Filament\Resources\Commissions\Pages\ListCommissions;
use App\Filament\Resources\Commissions\RelationManagers\MembersRelationManager;
use App\Models\Commission;
use App\Models\User;
use App\Support\Opsi;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** DJ-20: Master Komisi. */
class CommissionResource extends Resource
{
    protected static ?string $model = Commission::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|\UnitEnum|null $navigationGroup = 'Data Jemaat';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Komisi';

    protected static ?string $pluralModelLabel = 'Komisi';

    public static function getEloquentQuery(): Builder
    {
        $u = auth()->user();
        $q = parent::getEloquentQuery();

        return $u->lihatSemuaJemaat() ? $q : $q->whereIn('id', $u->komisiDikoordinasi());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(3)->schema([
                TextInput::make('name')->label('Nama komisi')->required()->unique(ignoreRecord: true),
                Select::make('coordinator_id')->label('Koordinator')
                    ->options(fn () => User::query()->where('is_active', true)->pluck('name', 'id'))->searchable(),
                TextInput::make('schedule')->label('Jadwal ibadah rutin')->placeholder('mis. Rabu, 17.00'),
                TextInput::make('min_age')->label('Umur minimum')->numeric()->minValue(0),
                TextInput::make('max_age')->label('Umur maksimum')->numeric()->minValue(0),
                Select::make('gender')->label('Kelamin')->options(Opsi::KELAMIN)->placeholder('Semua'),
                Toggle::make('count_only')->label('Kehadiran dicatat jumlah total saja')
                    ->helperText('Untuk Sekolah Minggu (data anak belum per nama).'),
                Toggle::make('is_active')->label('Aktif')->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Komisi')->searchable(),
                TextColumn::make('aturan')->label('Aturan usulan')->state(fn (Commission $c) => trim(
                    ($c->min_age !== null || $c->max_age !== null ? 'Umur '.($c->min_age ?? 0).'-'.($c->max_age ?? '...').' ' : '')
                    .($c->gender ? Opsi::KELAMIN[$c->gender] : 'Semua kelamin'))),
                TextColumn::make('active_members_count')->label('Anggota')->counts('activeMembers'),
                TextColumn::make('coordinator.name')->label('Koordinator'),
                TextColumn::make('schedule')->label('Jadwal'),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getRelations(): array
    {
        return [MembersRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCommissions::route('/'),
            'create' => CreateCommission::route('/create'),
            'edit' => EditCommission::route('/{record}/edit'),
        ];
    }
}
