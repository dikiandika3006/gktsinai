<?php

namespace App\Filament\Resources\Families\RelationManagers;

use App\Filament\Actions\KeluargaActions;
use App\Filament\Resources\Members\MemberResource;
use App\Support\Opsi;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MembersRelationManager extends RelationManager
{
    protected static string $relationship = 'members';

    protected static ?string $title = 'Anggota Keluarga';

    protected static ?string $modelLabel = 'anggota';

    public function isReadOnly(): bool
    {
        return ! auth()->user()->kelolaJemaat();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label('Nama')->required(),
            TextInput::make('alias')->label('Alias'),
            Select::make('gender')->label('Kelamin')->options(Opsi::KELAMIN),
            DatePicker::make('birth_date')->label('Tanggal lahir'),
            Select::make('family_relation')->label('Hubungan')->options(Opsi::HUBUNGAN)->required(),
            TextInput::make('phone')->label('No. HP')->tel(),
            Select::make('membership_status')->label('Keanggotaan')->options(Opsi::KEANGGOTAAN)->default('simpatisan')->required(),
            Select::make('activity_status')->label('Keaktifan')->options(Opsi::KEAKTIFAN)->default('aktif'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('birth_date')
            ->columns([
                TextColumn::make('code')->label('Kode'),
                TextColumn::make('name')->label('Nama')->description(fn ($record) => $record?->alias)
                    ->weight(fn ($record) => $record?->id === $this->getOwnerRecord()->head_member_id ? 'bold' : null)
                    ->icon(fn ($record) => $record?->id === $this->getOwnerRecord()->head_member_id ? Heroicon::Star : null)
                    ->iconColor('warning'),
                TextColumn::make('family_relation')->label('Hubungan')->formatStateUsing(fn ($state) => Opsi::label(Opsi::HUBUNGAN, $state)),
                TextColumn::make('gender')->label('L/P'),
                TextColumn::make('age')->label('Umur'),
                TextColumn::make('membership_status')->label('Keanggotaan')->badge()->formatStateUsing(fn ($state) => Opsi::label(Opsi::KEANGGOTAAN, $state)),
                TextColumn::make('activity_status')->label('Keaktifan')->badge()->formatStateUsing(fn ($state) => Opsi::label(Opsi::KEAKTIFAN, $state)),
                TextColumn::make('phone')->label('HP')->visible(fn () => auth()->user()->lihatKontak()),
            ])
            ->headerActions([CreateAction::make()->label('Tambah anggota')])
            ->recordActions([
                Action::make('lihat')->label('Profil')->icon(Heroicon::OutlinedEye)
                    ->url(fn ($record) => MemberResource::getUrl('view', ['record' => $record])),
                EditAction::make(),
                // Tombol langsung (tidak di menu ⋮) supaya mudah ditemukan.
                KeluargaActions::jadikanKepala()->label('Jadikan kepala')->color('warning')
                    ->after(fn () => $this->getOwnerRecord()->refresh()),
                KeluargaActions::pindah()->color('gray')->after(fn () => $this->getOwnerRecord()->refresh()),
            ])
            ->toolbarActions([
                KeluargaActions::pindahBulk()->label('Pindah KK terpilih')->after(fn () => $this->getOwnerRecord()->refresh()),
            ]);
    }
}
