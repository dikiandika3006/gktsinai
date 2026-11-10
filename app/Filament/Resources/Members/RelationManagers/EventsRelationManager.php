<?php

namespace App\Filament\Resources\Members\RelationManagers;

use App\Support\Opsi;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** DJ-07: riwayat sakramen & mutasi. */
class EventsRelationManager extends RelationManager
{
    protected static string $relationship = 'events';

    protected static ?string $title = 'Sakramen & Mutasi';

    protected static ?string $modelLabel = 'riwayat';

    public function isReadOnly(): bool
    {
        return ! auth()->user()->kelolaJemaat();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('type')->label('Jenis')->options(Opsi::JENIS_EVENT)->required(),
            Select::make('data_status')->label('Status data')->options(Opsi::STATUS_DATA_EVENT)->default('lengkap')->required(),
            DatePicker::make('date')->label('Tanggal'),
            TextInput::make('place')->label('Tempat'),
            TextInput::make('church')->label('Gereja (asal/tujuan/pelaksana)'),
            TextInput::make('minister')->label('Pendeta pelayan'),
            TextInput::make('letter_no')->label('Nomor surat'),
            FileUpload::make('attachment')->label('Lampiran')->directory('lampiran-mutasi')->disk('local')
                ->acceptedFileTypes(['application/pdf', 'image/*'])->maxSize(5120)->downloadable(),
            Textarea::make('notes')->label('Catatan')->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('type')
            ->defaultSort('date')
            ->columns([
                TextColumn::make('type')->label('Jenis')->badge()->formatStateUsing(fn ($state) => Opsi::label(Opsi::JENIS_EVENT, $state)),
                TextColumn::make('date')->label('Tanggal')->date('d/m/Y'),
                TextColumn::make('church')->label('Gereja'),
                TextColumn::make('place')->label('Tempat'),
                TextColumn::make('minister')->label('Pendeta'),
                TextColumn::make('letter_no')->label('No. surat'),
                TextColumn::make('data_status')->label('Data')->badge()
                    ->color(fn ($state) => $state === 'lengkap' ? 'success' : 'warning')
                    ->formatStateUsing(fn ($state) => Opsi::label(Opsi::STATUS_DATA_EVENT, $state)),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()->lihatSemuaJemaat() || auth()->user()->kelolaJemaat();
    }
}
