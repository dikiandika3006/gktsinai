<?php

namespace App\Filament\Resources\Regions;

use App\Filament\Resources\Regions\Pages\CreateRegion;
use App\Filament\Resources\Regions\Pages\EditRegion;
use App\Filament\Resources\Regions\Pages\ListRegions;
use App\Models\Region;
use App\Models\User;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** DJ-05: master wilayah + PT-04 batas area. */
class RegionResource extends Resource
{
    protected static ?string $model = Region::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static string|\UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?string $modelLabel = 'Wilayah';

    protected static ?string $pluralModelLabel = 'Wilayah';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(4)->schema([
                TextInput::make('name')->label('Nama wilayah')->required()->unique(ignoreRecord: true)->columnSpan(2),
                ColorPicker::make('map_color')->label('Warna pin')->default('#2563eb'),
                Toggle::make('is_active')->label('Aktif')->default(true)->inline(false),
                Select::make('coordinator_id')->label('Koordinator wilayah')
                    ->options(fn () => User::role('koordinator_wilayah')->pluck('name', 'id'))
                    ->helperText('Belum ada? Tugas koordinator dipegang Sekretariat.')->columnSpan(2),
            ]),
            Section::make('Batas area (poligon)')->description('Gambar batas wilayah di peta. Dipakai untuk usulan wilayah otomatis tiap KK.')
                ->schema([
                    Hidden::make('area_geojson'),
                    ViewField::make('area_editor')->view('filament.forms.area-editor')->dehydrated(false),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                ColorColumn::make('map_color')->label(''),
                TextColumn::make('name')->label('Wilayah')->searchable()->sortable(),
                TextColumn::make('families_count')->label('KK')->counts('families')->sortable(),
                TextColumn::make('coordinator.name')->label('Koordinator'),
                IconColumn::make('area_geojson')->label('Batas area')->boolean()
                    ->state(fn (Region $r) => ! empty($r->area_geojson)),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRegions::route('/'),
            'create' => CreateRegion::route('/create'),
            'edit' => EditRegion::route('/{record}/edit'),
        ];
    }
}
