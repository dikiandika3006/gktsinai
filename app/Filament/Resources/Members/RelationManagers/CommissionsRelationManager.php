<?php

namespace App\Filament\Resources\Members\RelationManagers;

use Filament\Actions\Action;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** DJ-12: keanggotaan komisi kategorial. */
class CommissionsRelationManager extends RelationManager
{
    protected static string $relationship = 'commissions';

    protected static ?string $title = 'Komisi';

    public function isReadOnly(): bool
    {
        return ! auth()->user()->kelolaJemaat();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('joined_at')->label('Tanggal bergabung'),
            Toggle::make('is_active')->label('Aktif')->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')->label('Komisi'),
                TextColumn::make('joined_at')->label('Bergabung')->date('d/m/Y'),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->headerActions([
                AttachAction::make()->label('Tambah komisi')->preloadRecordSelect()
                    ->schema(fn (AttachAction $action) => [
                        $action->getRecordSelect(),
                        DatePicker::make('joined_at')->label('Tanggal bergabung')->default(today()),
                        Toggle::make('is_active')->label('Aktif')->default(true),
                    ]),
                Action::make('usulan')->label('Terapkan usulan')->icon(Heroicon::OutlinedSparkles)
                    ->requiresConfirmation()
                    ->modalDescription(fn () => 'Usulan dari umur & kelamin: '
                        .($this->getOwnerRecord()->suggestedCommissions()->pluck('name')->join(', ') ?: 'tidak ada'))
                    ->action(function () {
                        $m = $this->getOwnerRecord();
                        $ids = $m->suggestedCommissions()->pluck('id');
                        $m->commissions()->syncWithoutDetaching($ids->mapWithKeys(fn ($id) => [$id => ['joined_at' => today(), 'is_active' => true]])->all());
                        Notification::make()->success()->title('Usulan komisi diterapkan')->send();
                    }),
            ])
            ->recordActions([EditAction::make(), DetachAction::make()->label('Keluarkan')]);
    }
}
