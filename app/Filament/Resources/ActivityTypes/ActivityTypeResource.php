<?php

namespace App\Filament\Resources\ActivityTypes;

use App\Filament\Resources\ActivityTypes\Pages\ManageActivityTypes;
use App\Models\ActivityType;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** KH-01: master jenis kegiatan. */
class ActivityTypeResource extends Resource
{
    protected static ?string $model = ActivityType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|\UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?string $modelLabel = 'Jenis Kegiatan';

    protected static ?string $pluralModelLabel = 'Jenis Kegiatan';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label('Nama kegiatan')->required(),
            Select::make('commission_id')->label('Komisi')->relationship('commission', 'name')
                ->placeholder('Bukan ibadah komisi'),
            Toggle::make('is_general_worship')->label('Ibadah Umum')
                ->helperText('Dipakai untuk peringatan tidak hadir 3 minggu (KH-07).'),
            Toggle::make('count_only')->label('Hanya jumlah total hadir (KH-05)'),
            Toggle::make('is_active')->label('Aktif')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Kegiatan'),
                TextColumn::make('commission.name')->label('Komisi'),
                IconColumn::make('is_general_worship')->label('Ibadah Umum')->boolean(),
                IconColumn::make('count_only')->label('Jumlah saja')->boolean(),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageActivityTypes::route('/')];
    }
}
