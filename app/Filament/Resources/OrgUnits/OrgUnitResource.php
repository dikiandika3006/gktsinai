<?php

namespace App\Filament\Resources\OrgUnits;

use App\Filament\Resources\OrgUnits\Pages\CreateOrgUnit;
use App\Filament\Resources\OrgUnits\Pages\EditOrgUnit;
use App\Filament\Resources\OrgUnits\Pages\ListOrgUnits;
use App\Models\Commission;
use App\Models\Member;
use App\Models\OrgPeriod;
use App\Models\OrgPosition;
use App\Models\OrgUnit;
use App\Support\Opsi;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Unit & jabatan struktur organisasi (dikelola per periode). */
class OrgUnitResource extends Resource
{
    protected static ?string $model = OrgUnit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|\UnitEnum|null $navigationGroup = 'Pelayanan';

    protected static ?int $navigationSort = 11;

    protected static ?string $slug = 'unit-organisasi';

    protected static ?string $modelLabel = 'Unit Organisasi';

    protected static ?string $pluralModelLabel = 'Kelola Struktur';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->kelolaJadwal() ?? false;
    }

    public static function memberSelect(string $nama = 'member_id'): Select
    {
        return Select::make($nama)->label('Data jemaat')->searchable()->placeholder('Belum dihubungkan')
            ->getSearchResultsUsing(fn (string $search) => Member::query()->search($search)->orderBy('name')->limit(30)->get()
                ->mapWithKeys(fn (Member $m) => [$m->id => "{$m->name} ({$m->code})"])->all())
            ->getOptionLabelUsing(fn ($value) => Member::find($value)?->name);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(3)->columnSpanFull()->schema([
                Select::make('org_period_id')->label('Periode')->required()
                    ->options(fn () => OrgPeriod::orderByDesc('start_date')->pluck('name', 'id'))
                    ->default(fn () => request('periode') ?? OrgPeriod::aktif()?->id)->live(),
                Select::make('type')->label('Jenis')->options(Opsi::JENIS_UNIT)->required()->default('bidang')->live(),
                TextInput::make('name')->label('Nama unit')->required(),
                Select::make('parent_id')->label('Di bawah')->placeholder('— (tingkat atas)')
                    ->options(fn (Get $get, ?OrgUnit $record) => OrgUnit::query()
                        ->where('org_period_id', $get('org_period_id'))
                        ->whereIn('type', ['pimpinan', 'sekretariat', 'bidang', 'komisi'])
                        ->when($record, fn ($query) => $query->where('id', '!=', $record->id))
                        ->orderBy('sort')->pluck('name', 'id')),
                Select::make('commission_id')->label('Data komisi')->options(fn () => Commission::orderBy('name')->pluck('name', 'id'))
                    ->helperText('Hubungkan ke data komisi (anggota, jadwal).')
                    ->visible(fn (Get $get) => $get('type') === 'komisi'),
                TextInput::make('sort')->label('Urutan')->numeric()->default(fn () => (int) OrgUnit::max('sort') + 1),
                Textarea::make('notes')->label('Catatan / tugas')->rows(2)->columnSpanFull(),
            ]),
            Section::make('Pengurus / penanggung jawab')->columnSpanFull()
                ->description('Nama ditulis seperti biasa (dengan gelar). Hubungkan ke data jemaat agar jabatan tampil di profil jemaat.')
                ->schema([
                    Repeater::make('positions')->relationship()->hiddenLabel()
                        ->table([
                            TableColumn::make('Jabatan')->width('22%'),
                            TableColumn::make('Nama'),
                            TableColumn::make('Data jemaat'),
                            TableColumn::make('Keterangan')->width('18%'),
                        ])
                        ->compact()
                        ->schema([
                            Select::make('role')->options(Opsi::JABATAN)->required(),
                            TextInput::make('name')->placeholder('mis. Pnt. Hizkia')->required(),
                            self::memberSelect(),
                            TextInput::make('note')->placeholder('opsional'),
                        ])
                        ->orderColumn('sort')
                        ->addActionLabel('Tambah orang'),
                ]),
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['period', 'parent', 'positions.member']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('name')->label('Unit')->searchable()->weight('bold')
                    ->description(fn (OrgUnit $r) => $r->parent ? 'di bawah '.$r->parent->name : null),
                TextColumn::make('type')->label('Jenis')->badge()->formatStateUsing(fn ($state) => Opsi::label(Opsi::JENIS_UNIT, $state)),
                TextColumn::make('pengurus')->label('Pengurus')->html()
                    ->state(fn (OrgUnit $r) => $r->positions->map(fn (OrgPosition $p) => e($p->role_label).': <b>'.e($p->display_name).'</b>'
                        .($p->member_id ? '' : ' <span style="color:#d97706" title="Belum dihubungkan ke data jemaat">•</span>'))->join('<br>')),
                TextColumn::make('period.name')->label('Periode')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('org_period_id')->label('Periode')->options(fn () => OrgPeriod::orderByDesc('start_date')->pluck('name', 'id'))
                    ->default(fn () => OrgPeriod::aktif()?->id),
                SelectFilter::make('type')->label('Jenis')->options(Opsi::JENIS_UNIT),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrgUnits::route('/'),
            'create' => CreateOrgUnit::route('/tambah'),
            'edit' => EditOrgUnit::route('/{record}/ubah'),
        ];
    }
}
