<?php

namespace App\Filament\Resources\Members;

use App\Exports\MembersExport;
use App\Filament\Actions\KeluargaActions;
use App\Filament\Resources\Families\FamilyResource;
use App\Filament\Resources\Members\Pages\CreateMember;
use App\Filament\Resources\Members\Pages\EditMember;
use App\Filament\Resources\Members\Pages\ListMembers;
use App\Filament\Resources\Members\Pages\ViewMember;
use App\Filament\Resources\Members\RelationManagers\CommissionsRelationManager;
use App\Filament\Resources\Members\RelationManagers\EventsRelationManager;
use App\Models\Commission;
use App\Models\Family;
use App\Models\Member;
use App\Models\Region;
use App\Services\DuplicateFinder;
use App\Support\Opsi;
use App\Support\Telepon;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;
use Maatwebsite\Excel\Facades\Excel;

class MemberResource extends Resource
{
    protected static ?string $model = Member::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|\UnitEnum|null $navigationGroup = 'Jemaat';

    protected static ?string $slug = 'jemaat';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Jemaat';

    protected static ?string $pluralModelLabel = 'Jemaat';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visibleTo(auth()->user())->with(['family.region']);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'alias', 'code'];
    }

    public static function form(Schema $schema): Schema
    {
        $kontak = auth()->user()?->lihatKontak();

        return $schema->components([
            Section::make('Data diri')->columns(3)->schema([
                TextInput::make('code')->label('Kode jemaat')
                    ->placeholder('Otomatis (SIB....)')->disabled()->dehydrated(false),
                TextInput::make('old_registration_no')->label('No Induk lama'),
                Select::make('gender')->label('Kelamin')->options(Opsi::KELAMIN),
                TextInput::make('name')->label('Nama lengkap')->required()->maxLength(255)->columnSpan(2)
                    ->live(onBlur: true),
                TextInput::make('alias')->label('Alias / panggilan'),
                TextInput::make('birth_place')->label('Tempat lahir'),
                DatePicker::make('birth_date')->label('Tanggal lahir')->maxDate(today())->live(onBlur: true),
                TextInput::make('phone')->label('No. HP')->tel()->visible($kontak)
                    ->helperText('Disimpan otomatis ke format +62...'),
                Text::make(function (Get $get, ?Member $record) {
                    if (! $get('name')) {
                        return null;
                    }
                    $kandidat = app(DuplicateFinder::class)->candidatesFor($get('name'), $get('birth_date'), $record?->id);
                    if ($kandidat->isEmpty()) {
                        return null;
                    }

                    return new HtmlString('<span style="color:#b45309">Kemungkinan duplikat: '
                        .e($kandidat->map(fn ($m) => "{$m->code} {$m->name} ({$m->birth_date?->format('d/m/Y')})")->join(', '))
                        .'. Periksa sebelum menyimpan, atau gabungkan di menu Duplikat.</span>');
                })->columnSpanFull(),
            ]),
            Section::make('Keluarga & keanggotaan')->columns(3)->schema([
                Select::make('family_id')->label('Kepala Keluarga (KK)')
                    ->relationship('family', 'code', fn (Builder $query) => $query->visibleTo(auth()->user()))
                    ->getOptionLabelFromRecordUsing(fn (Family $f) => $f->display_name)
                    ->searchable(['code', 'address'])->preload()
                    ->createOptionForm([
                        Textarea::make('address')->label('Alamat')->required(),
                        Select::make('region_id')->label('Wilayah')->options(Region::orderBy('name')->pluck('name', 'id')),
                        TextInput::make('phone')->label('No. HP keluarga')->tel(),
                    ])
                    ->columnSpan(2),
                Select::make('family_relation')->label('Hubungan dalam KK')->options(Opsi::HUBUNGAN),
                Select::make('membership_status')->label('Status keanggotaan')->options(Opsi::KEANGGOTAAN)
                    ->default('simpatisan')->required()->live(),
                Select::make('activity_status')->label('Status keaktifan')->options(Opsi::KEAKTIFAN)->default('aktif')
                    ->visible(fn (Get $get) => ! in_array($get('membership_status'), ['keluar', 'meninggal'], true)),
                DatePicker::make('joined_at')->label('Tanggal bergabung'),
                DatePicker::make('marriage_date')->label('Tanggal pernikahan'),
                DatePicker::make('death_date')->label('Tanggal wafat')
                    ->visible(fn (Get $get) => $get('membership_status') === 'meninggal'),
                Toggle::make('baptized_at_gkt')->label('Baptis di GKT'),
                Toggle::make('confirmed_at_gkt')->label('Sidi di GKT'),
                Textarea::make('notes')->label('Catatan')->columnSpanFull()->rows(2),
            ]),
            Section::make('Data asli Excel (lama)')->columns(3)->collapsed()->schema([
                TextInput::make('legacy_status')->label('Kategori / Status Jemaat'),
                TextInput::make('legacy_region')->label('Wilayah'),
                TextInput::make('legacy_family_code')->label('ID KK'),
                Textarea::make('legacy_address')->label('Alamat')->columnSpanFull()->rows(2),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        $kontak = auth()->user()?->lihatKontak();

        return $schema->components([
            Section::make('Data diri')->columns(4)->schema([
                TextEntry::make('code')->label('Kode'),
                TextEntry::make('old_registration_no')->label('No Induk lama'),
                TextEntry::make('name')->label('Nama'),
                TextEntry::make('alias')->label('Alias'),
                TextEntry::make('gender')->label('Kelamin')->formatStateUsing(fn ($state) => Opsi::label(Opsi::KELAMIN, $state)),
                TextEntry::make('birth_date')->label('Tempat, tgl lahir')
                    ->formatStateUsing(fn ($state, Member $r) => trim(($r->birth_place ? $r->birth_place.', ' : '').$state?->format('d/m/Y'))),
                TextEntry::make('age')->label('Umur')->suffix(' tahun'),
                TextEntry::make('phone')->label('No. HP')->visible($kontak)
                    ->url(fn (Member $r) => Telepon::waLink($r->phone), true),
            ]),
            Section::make('Keluarga & keanggotaan')->columns(4)->schema([
                TextEntry::make('family.display_name')->label('KK')
                    ->url(fn (Member $r) => $r->family ? FamilyResource::getUrl('view', ['record' => $r->family]) : null),
                TextEntry::make('family_relation')->label('Hubungan')->formatStateUsing(fn ($state) => Opsi::label(Opsi::HUBUNGAN, $state)),
                TextEntry::make('family.region.name')->label('Wilayah'),
                TextEntry::make('family.address')->label('Alamat')->visible($kontak),
                TextEntry::make('membership_status')->label('Keanggotaan')->badge()
                    ->formatStateUsing(fn ($state) => Opsi::label(Opsi::KEANGGOTAAN, $state)),
                TextEntry::make('activity_status')->label('Keaktifan')->badge()
                    ->color(fn ($state) => $state === 'aktif' ? 'success' : 'gray')
                    ->formatStateUsing(fn ($state) => Opsi::label(Opsi::KEAKTIFAN, $state)),
                TextEntry::make('commissions.name')->label('Komisi')->badge(),
                TextEntry::make('marriage_date')->label('Tgl pernikahan')->date('d/m/Y'),
                TextEntry::make('joined_at')->label('Bergabung')->date('d/m/Y'),
                TextEntry::make('death_date')->label('Wafat')->date('d/m/Y')->visible(fn (Member $r) => (bool) $r->death_date),
                TextEntry::make('suggested')->label('Usulan komisi (umur & kelamin)')
                    ->state(fn (Member $r) => $r->suggestedCommissions()->pluck('name')->join(', ') ?: '-'),
                TextEntry::make('baptized_at_gkt')->label('Baptis di GKT')->formatStateUsing(fn ($state) => $state ? 'Ya' : 'Tidak'),
                TextEntry::make('confirmed_at_gkt')->label('Sidi di GKT')->formatStateUsing(fn ($state) => $state ? 'Ya' : 'Tidak'),
                TextEntry::make('notes')->label('Catatan')->columnSpanFull(),
            ]),
            Section::make('Anggota keluarga')->collapsible()->schema([
                ViewEntry::make('keluarga')->hiddenLabel()->view('filament.keluarga.entry'),
            ]),
            Section::make('Data asli Excel (lama)')->columns(4)->collapsed()->schema([
                TextEntry::make('legacy_status')->label('Kategori / Status Jemaat'),
                TextEntry::make('legacy_region')->label('Wilayah'),
                TextEntry::make('legacy_family_code')->label('ID KK'),
                TextEntry::make('legacy_address')->label('Alamat')->visible($kontak),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $user = auth()->user();
        $kontak = $user?->lihatKontak();

        return $table
            ->defaultSort('code')
            ->columns([
                TextColumn::make('code')->label('Kode')->sortable()->searchable(),
                TextColumn::make('name')->label('Nama')->sortable()->searchable(['name', 'alias'])
                    ->description(fn (Member $r) => $r->alias),
                TextColumn::make('gender')->label('L/P')->toggleable(),
                TextColumn::make('age')->label('Umur')
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('birth_date', $direction === 'asc' ? 'desc' : 'asc')),
                TextColumn::make('birth_date')->label('Tgl lahir')->date('d/m/Y')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('family.code')->label('KK')->sortable()->searchable()
                    ->description(fn (Member $r) => $r->family_relation ? Opsi::label(Opsi::HUBUNGAN, $r->family_relation) : null)
                    ->url(fn (Member $r) => $r->family_id ? FamilyResource::getUrl('view', ['record' => $r->family_id]) : null)
                    ->color('primary'),
                TextColumn::make('family.region.name')->label('Wilayah')->toggleable(),
                TextColumn::make('membership_status')->label('Keanggotaan')->badge()
                    ->formatStateUsing(fn ($state) => Opsi::label(Opsi::KEANGGOTAAN, $state))
                    ->color(fn ($state) => match ($state) {
                        'sidi', 'baptis', 'anggota' => 'primary',
                        'simpatisan' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('activity_status')->label('Keaktifan')->badge()
                    ->formatStateUsing(fn ($state) => Opsi::label(Opsi::KEAKTIFAN, $state))
                    ->color(fn ($state) => $state === 'aktif' ? 'success' : 'warning'),
                TextColumn::make('phone')->label('HP')->visible($kontak)->toggleable(),
                TextColumn::make('commissions.name')->label('Komisi')->badge()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('region')->label('Wilayah')->multiple()
                    ->options(fn () => Region::orderBy('name')->pluck('name', 'id')->put('none', 'Belum didefinisikan'))
                    ->query(function (Builder $query, array $data) {
                        $v = $data['values'] ?? [];
                        if (! $v) {
                            return $query;
                        }

                        return $query->where(function ($w) use ($v) {
                            $ids = array_filter($v, fn ($x) => $x !== 'none');
                            if ($ids) {
                                $w->whereHas('family', fn ($f) => $f->whereIn('region_id', $ids));
                            }
                            if (in_array('none', $v, true)) {
                                $w->orWhereDoesntHave('family')->orWhereHas('family', fn ($f) => $f->whereNull('region_id'));
                            }
                        });
                    }),
                SelectFilter::make('membership_status')->label('Keanggotaan')->multiple()->options(Opsi::KEANGGOTAAN),
                SelectFilter::make('activity_status')->label('Keaktifan')->options(Opsi::KEAKTIFAN),
                SelectFilter::make('commission')->label('Komisi')
                    ->options(fn () => Commission::orderBy('name')->pluck('name', 'id'))
                    ->query(fn (Builder $query, array $data) => $data['value']
                        ? $query->whereHas('commissions', fn ($c) => $c->where('commissions.id', $data['value'])) : $query),
                SelectFilter::make('age_group')->label('Kelompok umur')->options(Opsi::KELOMPOK_UMUR)
                    ->query(fn (Builder $query, array $data) => $data['value'] ? (new Member)->scopeAgeGroup($query, $data['value']) : $query),
                SelectFilter::make('family_id')->label('KK')->relationship('family', 'code')->searchable(),
                SelectFilter::make('kelengkapan')->label('Kelengkapan data (DJ-17)')
                    ->options([
                        'tanpa_hp' => 'Tanpa No. HP',
                        'tanpa_tgl_lahir' => 'Tanpa tanggal lahir',
                        'tanpa_kelamin' => 'Tanpa kelamin',
                        'tanpa_koordinat' => 'KK tanpa koordinat',
                        'tanpa_kk' => 'Belum masuk KK',
                        'tanpa_sakramen' => 'Anggota tanpa data baptis/sidi',
                    ])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'tanpa_hp' => $query->whereNull('phone')->whereDoesntHave('family', fn ($f) => $f->whereNotNull('phone')),
                        'tanpa_tgl_lahir' => $query->whereNull('birth_date'),
                        'tanpa_kelamin' => $query->whereNull('gender'),
                        'tanpa_koordinat' => $query->whereHas('family', fn ($f) => $f->whereNull('latitude')),
                        'tanpa_kk' => $query->whereNull('family_id'),
                        'tanpa_sakramen' => $query->whereIn('membership_status', Opsi::ANGGOTA_RESMI)
                            ->whereDoesntHave('events', fn ($e) => $e->whereIn('type', ['baptis', 'sidi'])->where('data_status', 'lengkap')),
                        default => $query,
                    }),
                Filter::make('tercatat')->label('Sembunyikan yang keluar/meninggal')->default()
                    ->query(fn (Builder $query) => $query->whereIn('membership_status', Opsi::MASIH_TERCATAT))->toggle(),
            ])
            ->filtersFormColumns(2)
            ->recordActions([
                ViewAction::make(),
                KeluargaActions::lihatKeluarga(),
                EditAction::make(),
                ActionGroup::make([
                    KeluargaActions::pindah(),
                    KeluargaActions::jadikanKepala(),
                    Action::make('pdf')->label('Buku induk')->icon(Heroicon::OutlinedDocumentText)
                        ->url(fn (Member $r) => route('pdf.member', $r), true),
                ]),
            ])
            ->headerActions([
                Action::make('export')->label('Ekspor Excel')->icon(Heroicon::OutlinedArrowDownTray)
                    ->visible(fn () => auth()->user()->lihatSemuaJemaat())
                    ->action(function ($livewire) {
                        $query = $livewire->getFilteredTableQuery()->with(['family.region', 'commissions']);

                        return Excel::download(new MembersExport($query->get(), auth()->user()->eksporKontak()),
                            'data-jemaat-'.now()->format('Ymd-His').'.xlsx');
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    KeluargaActions::pindahBulk(),
                    BulkAction::make('komisi')->label('Tambahkan ke komisi')->icon(Heroicon::OutlinedUserGroup)
                        ->visible(fn () => auth()->user()->kelolaJemaat())
                        ->schema([Select::make('commission_id')->label('Komisi')->options(Commission::pluck('name', 'id'))->required()])
                        ->action(function (Collection $records, array $data) {
                            $c = Commission::find($data['commission_id']);
                            $c->members()->syncWithoutDetaching($records->mapWithKeys(fn ($m) => [$m->id => ['joined_at' => today(), 'is_active' => true]])->all());
                            Notification::make()->success()->title($records->count().' jemaat ditambahkan ke '.$c->name)->send();
                        })->deselectRecordsAfterCompletion(),
                    BulkAction::make('keaktifan')->label('Ubah status keaktifan')->icon(Heroicon::OutlinedArrowPath)
                        ->visible(fn () => auth()->user()->kelolaJemaat())
                        ->schema([Select::make('activity_status')->label('Status')->options(Opsi::KEAKTIFAN)->required()])
                        ->action(function (Collection $records, array $data) {
                            $records->each(fn (Member $m) => $m->update(['activity_status' => $data['activity_status']]));
                            Notification::make()->success()->title('Status diperbarui')->send();
                        })->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make()
                        ->visible(fn () => auth()->user()->peran('super_admin', 'sekretariat')),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            EventsRelationManager::class,
            CommissionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMembers::route('/'),
            'create' => CreateMember::route('/create'),
            'view' => ViewMember::route('/{record}'),
            'edit' => EditMember::route('/{record}/edit'),
        ];
    }
}
