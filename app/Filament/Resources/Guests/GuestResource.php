<?php

namespace App\Filament\Resources\Guests;

use App\Filament\Resources\Guests\Pages\CreateGuest;
use App\Filament\Resources\Guests\Pages\EditGuest;
use App\Filament\Resources\Guests\Pages\ListGuests;
use App\Filament\Resources\Guests\Pages\ViewGuest;
use App\Filament\Resources\Guests\RelationManagers\FollowupsRelationManager;
use App\Filament\Resources\Members\MemberResource;
use App\Models\Family;
use App\Models\Guest;
use App\Models\GuestFollowup;
use App\Models\Region;
use App\Models\User;
use App\Services\GuestConverter;
use App\Support\Opsi;
use App\Support\Telepon;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class GuestResource extends Resource
{
    protected static ?string $model = Guest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserPlus;

    protected static string|\UnitEnum|null $navigationGroup = 'Tamu & Follow-Up';

    protected static ?string $modelLabel = 'Tamu';

    protected static ?string $pluralModelLabel = 'Jemaat Tamu';

    public static function getEloquentQuery(): Builder
    {
        $u = auth()->user();
        $query = parent::getEloquentQuery()->with('pic')->withCount('attendances');
        if ($u->peran('super_admin', 'sekretariat', 'pendeta', 'majelis')) {
            return $query;
        }
        if ($u->peran('koordinator_wilayah', 'koordinator_komisi')) {
            return $query->where('pic_id', $u->id);
        }
        if ($u->peran('penyambut')) {
            return $query->whereDate('created_at', today());
        }

        return $query->whereRaw('1 = 0');
    }

    public static function getNavigationBadge(): ?string
    {
        $n = static::getEloquentQuery()->where('fu_stage', 'baru')->where('fu_due_at', '<', now())->count();

        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function form(Schema $schema): Schema
    {
        $kelola = auth()->user()->kelolaTamu() || auth()->user()->peran('pendeta');

        return $schema->components([
            Section::make('Data tamu (TF-01)')->columns(3)->schema([
                TextInput::make('name')->label('Nama')->required(),
                TextInput::make('phone')->label('No. HP / WA')->tel(),
                TextInput::make('city')->label('Alamat / kota'),
                TextInput::make('came_with')->label('Datang dengan siapa'),
                Select::make('source')->label('Tahu dari mana')->options(Opsi::SUMBER_INFO),
                TextInput::make('origin_church')->label('Gereja asal'),
                Toggle::make('consent_contact')->label('Bersedia dihubungi')
                    ->helperText('Persetujuan dicatat (UU PDP No. 27/2022).'),
                DatePicker::make('first_visit_at')->label('Kunjungan pertama')->default(today()),
            ]),
            Section::make('Follow-up')->columns(3)->visible($kelola)->schema([
                Select::make('fu_stage')->label('Tahap FU')->options(Opsi::TAHAP_FU)->default('baru')->required(),
                Select::make('pic_id')->label('PIC follow-up')
                    ->options(fn () => User::where('is_active', true)->orderBy('name')->pluck('name', 'id'))->searchable(),
                DateTimePicker::make('fu_due_at')->label('Batas waktu FU')->native(false)->displayFormat('d/m/Y H:i')
                    ->helperText('Default 2x24 jam setelah kunjungan pertama.'),
                Textarea::make('notes')->label('Catatan')->columnSpanFull()->rows(2),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(4)->schema([
                TextEntry::make('name')->label('Nama'),
                TextEntry::make('phone')->label('HP')->url(fn (Guest $g) => Telepon::waLink($g->phone), true),
                TextEntry::make('city')->label('Alamat / kota'),
                TextEntry::make('came_with')->label('Datang dengan'),
                TextEntry::make('source')->label('Sumber info')->formatStateUsing(fn ($state) => Opsi::label(Opsi::SUMBER_INFO, $state)),
                TextEntry::make('origin_church')->label('Gereja asal'),
                TextEntry::make('consent_contact')->label('Bersedia dihubungi')
                    ->formatStateUsing(fn ($state, Guest $g) => $state ? 'Ya ('.$g->consent_at?->format('d/m/Y').')' : 'Tidak'),
                TextEntry::make('first_visit_at')->label('Kunjungan pertama')->date('d/m/Y'),
                TextEntry::make('visit_count')->label('Jumlah kunjungan'),
                TextEntry::make('fu_stage')->label('Tahap FU')->badge()->formatStateUsing(fn ($state) => Opsi::label(Opsi::TAHAP_FU, $state)),
                TextEntry::make('pic.name')->label('PIC'),
                TextEntry::make('fu_due_at')->label('Batas FU')->dateTime('d/m/Y H:i')
                    ->color(fn (Guest $g) => $g->isOverdue() ? 'danger' : null),
                TextEntry::make('convertedMember.name')->label('Menjadi jemaat')
                    ->url(fn (Guest $g) => $g->convertedMember ? MemberResource::getUrl('view', ['record' => $g->convertedMember]) : null),
                TextEntry::make('notes')->label('Catatan')->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('first_visit_at', 'desc')
            ->groups([
                Group::make('fu_stage')->label('Tahap')->getTitleFromRecordUsing(fn (Guest $g) => Opsi::label(Opsi::TAHAP_FU, $g->fu_stage)),
            ])
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->description(fn (Guest $g) => $g->city),
                TextColumn::make('phone')->label('HP'),
                TextColumn::make('first_visit_at')->label('Pertama datang')->date('d/m/Y')->sortable(),
                TextColumn::make('kunjungan')->label('Kunjungan')->state(fn (Guest $g) => $g->visit_count),
                TextColumn::make('fu_stage')->label('Tahap')->badge()
                    ->formatStateUsing(fn ($state) => Opsi::label(Opsi::TAHAP_FU, $state))
                    ->color(fn ($state) => match ($state) {
                        'baru' => 'warning', 'dihubungi' => 'info', 'datang_lagi', 'rutin' => 'primary',
                        'jemaat' => 'success', default => 'gray',
                    }),
                TextColumn::make('pic.name')->label('PIC'),
                TextColumn::make('fu_due_at')->label('Batas FU')->dateTime('d/m H:i')->sortable()
                    ->color(fn (Guest $g) => $g->isOverdue() ? 'danger' : null)
                    ->description(fn (Guest $g) => $g->isOverdue() ? 'Lewat batas' : null),
                TextColumn::make('source')->label('Sumber')->formatStateUsing(fn ($state) => Opsi::label(Opsi::SUMBER_INFO, $state))->toggleable(),
            ])
            ->filters([
                SelectFilter::make('fu_stage')->label('Tahap')->options(Opsi::TAHAP_FU)->multiple(),
                SelectFilter::make('pic_id')->label('PIC')->relationship('pic', 'name'),
                Filter::make('terlambat')->label('FU lewat batas waktu')->toggle()
                    ->query(fn (Builder $query) => $query->where('fu_stage', 'baru')->where('fu_due_at', '<', now())),
                Filter::make('saya')->label('FU saya')->toggle()->query(fn (Builder $query) => $query->where('pic_id', auth()->id())),
            ])
            ->recordActions([
                ViewAction::make(),
                ActionGroup::make([
                    self::logAction(),
                    ...self::waActions(),
                    self::convertAction(),
                    EditAction::make(),
                ]),
            ]);
    }

    /** TF-04: catat kontak. */
    public static function logAction(): Action
    {
        return Action::make('log')->label('Catat kontak')->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
            ->visible(fn (Guest $g) => ! in_array($g->fu_stage, ['jemaat'], true))
            ->schema([
                DatePicker::make('date')->label('Tanggal')->default(today())->required(),
                Select::make('method')->label('Cara')->options(Opsi::CARA_FU)->required()->default('wa'),
                TextInput::make('result')->label('Hasil')->required(),
                Textarea::make('notes')->label('Catatan'),
                Select::make('fu_stage')->label('Ubah tahap ke')->options(Opsi::TAHAP_FU)->placeholder('Tidak diubah'),
            ])
            ->action(function (Guest $g, array $data) {
                GuestFollowup::create(['guest_id' => $g->id] + collect($data)->except('fu_stage')->all());
                if ($data['fu_stage']) {
                    $g->update(['fu_stage' => $data['fu_stage']]);
                }
                Notification::make()->success()->title('Kontak dicatat')->send();
            });
    }

    /** TF-08: template pesan WhatsApp. */
    public static function waActions(): array
    {
        return collect(config('sij.wa_templates'))->map(fn ($tpl, $key) => Action::make('wa_'.$key)
            ->label('WA: '.ucfirst($key))->icon(Heroicon::OutlinedChatBubbleOvalLeft)->color('success')
            ->visible(fn (Guest $g) => $g->phone && $g->consent_contact)
            ->url(fn (Guest $g) => Telepon::waLink($g->phone, str_replace('{nama}', $g->name, $tpl)), true))
            ->values()->all();
    }

    /** TF-06: konversi tamu jadi jemaat. */
    public static function convertAction(): Action
    {
        return Action::make('convert')->label('Jadikan jemaat')->icon(Heroicon::OutlinedUserCircle)->color('primary')
            ->visible(fn (Guest $g) => ! $g->converted_member_id && auth()->user()->kelolaJemaat())
            ->modalHeading('Konversi tamu menjadi jemaat')
            ->schema([
                Radio::make('family_mode')->label('Kepala Keluarga')->options(['baru' => 'Buat KK baru', 'gabung' => 'Gabung ke KK yang ada'])
                    ->default('baru')->live()->inline(),
                Select::make('family_id')->label('Pilih KK')
                    ->options(fn () => Family::with('head')->get()->mapWithKeys(fn ($f) => [$f->id => $f->display_name]))
                    ->searchable()->visible(fn (Get $get) => $get('family_mode') === 'gabung')->required(fn (Get $get) => $get('family_mode') === 'gabung'),
                Select::make('family_relation')->label('Hubungan')->options(Opsi::HUBUNGAN)
                    ->visible(fn (Get $get) => $get('family_mode') === 'gabung'),
                Textarea::make('new_family_address')->label('Alamat rumah')->default(fn (Guest $g) => $g->city)
                    ->visible(fn (Get $get) => $get('family_mode') === 'baru'),
                Select::make('region_id')->label('Wilayah')->options(fn () => Region::orderBy('name')->pluck('name', 'id'))
                    ->visible(fn (Get $get) => $get('family_mode') === 'baru'),
                Select::make('gender')->label('Kelamin')->options(Opsi::KELAMIN),
                DatePicker::make('birth_date')->label('Tanggal lahir'),
                Select::make('membership_status')->label('Status keanggotaan')->options(Opsi::KEANGGOTAAN)->default('simpatisan')->required(),
                Toggle::make('atestasi_masuk')->label('Catat atestasi masuk dari gereja asal'),
            ])
            ->action(function (Guest $g, array $data) {
                if (($data['family_mode'] ?? 'baru') === 'baru') {
                    $data['family_id'] = null;
                }
                $m = app(GuestConverter::class)->convert($g, $data);
                Notification::make()->success()->title($g->name.' kini tercatat sebagai jemaat ('.$m->code.')')
                    ->actions([Action::make('lihat')->label('Buka profil')->url(MemberResource::getUrl('view', ['record' => $m]))])->send();
            });
    }

    public static function getRelations(): array
    {
        return [FollowupsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGuests::route('/'),
            'create' => CreateGuest::route('/create'),
            'view' => ViewGuest::route('/{record}'),
            'edit' => EditGuest::route('/{record}/edit'),
        ];
    }
}
