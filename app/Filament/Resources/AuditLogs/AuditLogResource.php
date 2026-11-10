<?php

namespace App\Filament\Resources\AuditLogs;

use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Models\AuditLog;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|\UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?string $modelLabel = 'Log Audit';

    protected static ?string $pluralModelLabel = 'Log Audit';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        $fmt = fn ($state) => collect($state ?? [])->map(fn ($v) => is_scalar($v) || $v === null ? (string) $v : json_encode($v))->all();

        return $schema->columns(2)->components([
            TextEntry::make('created_at')->label('Waktu')->dateTime('d/m/Y H:i:s'),
            TextEntry::make('user.name')->label('Oleh')->placeholder('Sistem'),
            TextEntry::make('auditable_type')->label('Data')->formatStateUsing(fn ($state, $record) => class_basename($state).' #'.$record->auditable_id),
            TextEntry::make('event')->label('Aksi'),
            KeyValueEntry::make('old_values')->label('Nilai lama')->state(fn ($record) => $fmt($record->old_values)),
            KeyValueEntry::make('new_values')->label('Nilai baru')->state(fn ($record) => $fmt($record->new_values)),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($q) => $q->with('user'))
            ->columns([
                TextColumn::make('created_at')->label('Waktu')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('user.name')->label('Oleh')->placeholder('Sistem'),
                TextColumn::make('auditable_type')->label('Data')->formatStateUsing(fn ($state) => class_basename($state)),
                TextColumn::make('auditable_id')->label('ID'),
                TextColumn::make('event')->label('Aksi')->badge(),
                TextColumn::make('new_values')->label('Kolom berubah')
                    ->formatStateUsing(fn ($state, $record) => implode(', ', array_keys($record->new_values ?? $record->old_values ?? [])))->limit(60),
            ])
            ->filters([
                SelectFilter::make('auditable_type')->label('Data')->options([
                    'App\\Models\\Member' => 'Jemaat', 'App\\Models\\Family' => 'KK', 'App\\Models\\MemberEvent' => 'Sakramen & mutasi',
                ]),
                SelectFilter::make('event')->label('Aksi')->options(['dibuat' => 'Dibuat', 'diubah' => 'Diubah', 'dihapus' => 'Dihapus']),
                SelectFilter::make('user_id')->label('Oleh')->relationship('user', 'name'),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ListAuditLogs::route('/')];
    }
}
