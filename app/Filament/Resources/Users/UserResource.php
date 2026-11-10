<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
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

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|\UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?string $modelLabel = 'Pengguna';

    protected static ?string $pluralModelLabel = 'Pengguna';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                TextInput::make('name')->label('Nama')->required(),
                TextInput::make('username')->label('Username')->required()->maxLength(50)
                    ->regex('/^[a-z0-9._-]+$/')->unique(ignoreRecord: true)
                    ->helperText('Huruf kecil, angka, titik, strip. Dipakai untuk login.')
                    ->dehydrateStateUsing(fn ($state) => mb_strtolower(trim((string) $state))),
                TextInput::make('email')->email()->unique(ignoreRecord: true)->helperText('Opsional.'),
                TextInput::make('phone')->label('No. HP')->tel(),
                TextInput::make('password')->password()->revealable()
                    ->required(fn (string $operation) => $operation === 'create')
                    ->dehydrated(fn ($state) => filled($state))->minLength(8)
                    ->helperText('Kosongkan bila tidak diubah.'),
                Select::make('roles')->label('Peran')->multiple()->relationship('roles', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => Opsi::PERAN[$record->name] ?? $record->name)->preload()->required(),
                Select::make('region_id')->label('Wilayah (koordinator wilayah)')->relationship('region', 'name'),
                Select::make('commission_id')->label('Komisi (koordinator komisi)')->relationship('commission', 'name'),
                Toggle::make('is_active')->label('Aktif')->default(true)->inline(false),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable(),
                TextColumn::make('username')->label('Username')->searchable(),
                TextColumn::make('email')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('roles.name')->label('Peran')->badge()->formatStateUsing(fn ($state) => Opsi::PERAN[$state] ?? $state),
                TextColumn::make('region.name')->label('Wilayah'),
                TextColumn::make('commission.name')->label('Komisi'),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
