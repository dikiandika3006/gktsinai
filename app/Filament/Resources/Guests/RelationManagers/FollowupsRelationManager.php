<?php

namespace App\Filament\Resources\Guests\RelationManagers;

use App\Support\Opsi;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** TF-04: log setiap kontak. */
class FollowupsRelationManager extends RelationManager
{
    protected static string $relationship = 'followups';

    protected static ?string $title = 'Log Follow-Up';

    protected static ?string $modelLabel = 'kontak';

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            DatePicker::make('date')->label('Tanggal')->default(today())->required(),
            Select::make('method')->label('Cara')->options(Opsi::CARA_FU)->required(),
            TextInput::make('result')->label('Hasil')->required()->columnSpanFull(),
            Textarea::make('notes')->label('Catatan')->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('date')->label('Tanggal')->date('d/m/Y'),
                TextColumn::make('method')->label('Cara')->badge()->formatStateUsing(fn ($state) => Opsi::label(Opsi::CARA_FU, $state)),
                TextColumn::make('result')->label('Hasil')->wrap(),
                TextColumn::make('notes')->label('Catatan')->wrap()->limit(80),
                TextColumn::make('user.name')->label('Oleh'),
            ])
            ->headerActions([CreateAction::make()->label('Catat kontak')])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
