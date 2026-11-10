<?php

namespace App\Filament\Resources\Families;

use App\Filament\Resources\Families\Pages\CreateFamily;
use App\Filament\Resources\Families\Pages\EditFamily;
use App\Filament\Resources\Families\Pages\ListFamilies;
use App\Filament\Resources\Families\Pages\ViewFamily;
use App\Filament\Resources\Families\RelationManagers\MembersRelationManager;
use App\Filament\Resources\Families\RelationManagers\VisitsRelationManager;
use App\Jobs\GeocodeFamily;
use App\Models\Family;
use App\Models\Region;
use App\Support\Opsi;
use App\Support\Telepon;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class FamilyResource extends Resource
{
    protected static ?string $model = Family::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static string|\UnitEnum|null $navigationGroup = 'Data Jemaat';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Kepala Keluarga (KK)';

    protected static ?string $pluralModelLabel = 'Kepala Keluarga (KK)';

    protected static ?string $recordTitleAttribute = 'code';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visibleTo(auth()->user())
            ->with(['region', 'head', 'lastVisit'])->withCount('members');
    }

    public static function form(Schema $schema): Schema
    {
        $pin = auth()->user()?->pinManual() || auth()->user()?->kelolaJemaat();

        return $schema->components([
            Section::make('Data KK')->columns(3)->schema([
                TextInput::make('code')->label('ID KK')->placeholder('Otomatis (KK-...)')
                    ->unique(ignoreRecord: true)->maxLength(20),
                Select::make('region_id')->label('Wilayah')->options(fn () => Region::orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->helperText(fn (?Family $record) => $record?->suggestedRegion && $record->suggested_region_id !== $record->region_id
                        ? 'Usulan dari koordinat: '.$record->suggestedRegion->name : null),
                TextInput::make('phone')->label('No. HP keluarga')->tel(),
                Textarea::make('address')->label('Alamat rumah')->rows(2)->columnSpan(2)
                    ->helperText('Perubahan alamat akan memicu geocoding ulang otomatis.'),
                Select::make('head_member_id')->label('Kepala keluarga')
                    ->relationship('head', 'name', fn (Builder $query, ?Family $record) => $query->where('family_id', $record?->id ?? 0))
                    ->visibleOn('edit'),
                Textarea::make('notes')->label('Catatan')->rows(2)->columnSpanFull(),
            ]),
            Section::make('Lokasi')->columns(3)->collapsible()->schema([
                TextInput::make('latitude')->numeric()->disabled(! $pin)->live(onBlur: true),
                TextInput::make('longitude')->numeric()->disabled(! $pin)->live(onBlur: true),
                Select::make('geocode_status')->label('Status geocoding')->options(Opsi::GEOCODE)->default('belum')->disabled(! $pin),
                ViewField::make('map')->view('filament.forms.map-picker')->viewData(['editable' => $pin])
                    ->dehydrated(false)->columnSpanFull(),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        $kontak = auth()->user()?->lihatKontak();

        return $schema->components([
            Section::make('Data KK')->columns(4)->schema([
                TextEntry::make('code')->label('ID KK'),
                TextEntry::make('head.name')->label('Kepala keluarga'),
                TextEntry::make('region.name')->label('Wilayah')
                    ->hint(fn (Family $r) => $r->suggestedRegion && $r->suggested_region_id !== $r->region_id ? 'Usulan: '.$r->suggestedRegion->name : null),
                TextEntry::make('members_count')->label('Jumlah anggota')->state(fn (Family $r) => $r->members()->tercatat()->count()),
                TextEntry::make('address')->label('Alamat')->columnSpan(2)->visible($kontak),
                TextEntry::make('phone')->label('No. HP')->visible($kontak)->url(fn (Family $r) => Telepon::waLink($r->phone), true),
                TextEntry::make('lastVisit.realized_date')->label('Terakhir dikunjungi')->date('d/m/Y')->placeholder('Belum pernah'),
                TextEntry::make('geocode_status')->label('Geocoding')->badge()->visible($kontak)
                    ->formatStateUsing(fn ($state) => Opsi::label(Opsi::GEOCODE, $state))
                    ->color(fn ($state) => match ($state) {
                        'tepat', 'manual' => 'success', 'perkiraan' => 'warning', 'gagal' => 'danger', default => 'gray'
                    }),
                TextEntry::make('notes')->label('Catatan')->columnSpanFull(),
            ]),
            Section::make('Lokasi')->collapsible()->visible($kontak)->schema([
                ViewEntry::make('map')->view('filament.infolists.map-view'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $kontak = auth()->user()?->lihatKontak();

        return $table
            ->defaultSort('code')
            ->columns([
                TextColumn::make('code')->label('ID KK')->sortable()->searchable(),
                TextColumn::make('head.name')->label('Kepala keluarga')->searchable(),
                TextColumn::make('address')->label('Alamat')->limit(40)->searchable()->visible($kontak)->wrap(),
                TextColumn::make('region.name')->label('Wilayah')->sortable(),
                TextColumn::make('members_count')->label('Anggota')->sortable(),
                TextColumn::make('phone')->label('HP')->visible($kontak)->toggleable(),
                TextColumn::make('geocode_status')->label('Lokasi')->badge()->visible($kontak)
                    ->formatStateUsing(fn ($state) => Opsi::label(Opsi::GEOCODE, $state))
                    ->color(fn ($state) => match ($state) {
                        'tepat', 'manual' => 'success', 'perkiraan' => 'warning', 'gagal' => 'danger', default => 'gray'
                    }),
                TextColumn::make('lastVisit.realized_date')->label('Terakhir dikunjungi')->date('d/m/Y')->placeholder('Belum pernah'),
            ])
            ->filters([
                SelectFilter::make('region_id')->label('Wilayah')->multiple()->relationship('region', 'name'),
                SelectFilter::make('geocode_status')->label('Status lokasi')->options(Opsi::GEOCODE),
                SelectFilter::make('wilayah_usulan')->label('Wilayah usulan berbeda')
                    ->options(['ya' => 'Ada usulan wilayah berbeda'])
                    ->query(fn (Builder $query, array $data) => $data['value'] ? $query->whereNotNull('suggested_region_id')
                        ->where(fn ($w) => $w->whereNull('region_id')->orWhereColumn('suggested_region_id', '!=', 'region_id')) : $query),
                SelectFilter::make('kunjungan')->label('Kunjungan')
                    ->options(['lebih12' => 'Belum dikunjungi > 12 bulan'])
                    ->query(fn (Builder $query, array $data) => $data['value'] ? $query->whereDoesntHave('visits', fn ($v) => $v->where('status', 'selesai')
                        ->where('realized_date', '>=', today()->subMonths(12))) : $query),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('pdf')->label('Buku induk KK')->icon(Heroicon::OutlinedDocumentText)
                    ->url(fn (Family $r) => route('pdf.family', $r), true),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('geocode')->label('Geocoding ulang')->icon(Heroicon::OutlinedMapPin)
                        ->visible(fn () => auth()->user()->pinManual())
                        ->action(function (Collection $records) {
                            $records->each(fn (Family $f) => GeocodeFamily::dispatch($f->id));
                            Notification::make()->success()->title($records->count().' KK masuk antrean geocoding')
                                ->body('Diproses 1 alamat per detik oleh queue worker.')->send();
                        }),
                    BulkAction::make('terapkan_wilayah')->label('Terapkan wilayah usulan')->icon(Heroicon::OutlinedCheck)
                        ->visible(fn () => auth()->user()->kelolaJemaat())->requiresConfirmation()
                        ->action(function (Collection $records) {
                            $n = 0;
                            $records->each(function (Family $f) use (&$n) {
                                if ($f->suggested_region_id) {
                                    $f->update(['region_id' => $f->suggested_region_id]);
                                    $n++;
                                }
                            });
                            Notification::make()->success()->title("Wilayah {$n} KK diperbarui")->send();
                        }),
                    DeleteBulkAction::make()->visible(fn () => auth()->user()->peran('super_admin', 'sekretariat')),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            MembersRelationManager::class,
            VisitsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFamilies::route('/'),
            'create' => CreateFamily::route('/create'),
            'view' => ViewFamily::route('/{record}'),
            'edit' => EditFamily::route('/{record}/edit'),
        ];
    }
}
