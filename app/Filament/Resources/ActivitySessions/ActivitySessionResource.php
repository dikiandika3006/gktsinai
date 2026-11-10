<?php

namespace App\Filament\Resources\ActivitySessions;

use App\Filament\Resources\ActivitySessions\Pages\CreateActivitySession;
use App\Filament\Resources\ActivitySessions\Pages\EditActivitySession;
use App\Filament\Resources\ActivitySessions\Pages\ListActivitySessions;
use App\Filament\Resources\ActivitySessions\Pages\ViewActivitySession;
use App\Filament\Resources\ActivitySessions\RelationManagers\AttendancesRelationManager;
use App\Models\ActivitySession;
use App\Models\ActivityType;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** KH-02: sesi kegiatan. */
class ActivitySessionResource extends Resource
{
    protected static ?string $model = ActivitySession::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|\UnitEnum|null $navigationGroup = 'Kehadiran';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Sesi Kegiatan';

    protected static ?string $pluralModelLabel = 'Sesi Kegiatan';

    public static function getEloquentQuery(): Builder
    {
        $u = auth()->user();
        $q = parent::getEloquentQuery()->with('activityType')->withCount('attendances');
        if ($u->hasRole('koordinator_komisi') && ! $u->lihatSemuaJemaat() && ! $u->peran('sekretariat')) {
            $q->whereHas('activityType', fn ($t) => $t->whereIn('commission_id', $u->komisiDikoordinasi()));
        }

        return $q;
    }

    public static function activityTypeOptions(): array
    {
        $u = auth()->user();

        return ActivityType::query()->where('is_active', true)
            ->when($u->hasRole('koordinator_komisi') && ! $u->peran('super_admin', 'sekretariat'),
                fn ($q) => $q->whereIn('commission_id', $u->komisiDikoordinasi()))
            ->orderByDesc('is_general_worship')->orderBy('name')->pluck('name', 'id')->all();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(3)->schema([
                Select::make('activity_type_id')->label('Jenis kegiatan')->options(fn () => self::activityTypeOptions())
                    ->required()->live(),
                DatePicker::make('date')->label('Tanggal')->default(today())->required(),
                TextInput::make('location')->label('Tempat')->default('Gedung Gereja'),
                TimePicker::make('start_time')->label('Jam mulai')->seconds(false)->default('07:00'),
                TimePicker::make('end_time')->label('Jam selesai')->seconds(false)->default('09:00'),
                TextInput::make('manual_count')->label('Jumlah total hadir')->numeric()->minValue(0)
                    ->helperText('KH-05: diisi untuk Sekolah Minggu atau kegiatan yang tidak dicatat per nama.')
                    ->visible(fn (Get $get) => (bool) ActivityType::find($get('activity_type_id'))?->count_only),
                Textarea::make('notes')->label('Catatan')->columnSpanFull()->rows(2),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(4)->schema([
                TextEntry::make('activityType.name')->label('Kegiatan'),
                TextEntry::make('date')->label('Tanggal')->date('d/m/Y'),
                TextEntry::make('jam')->label('Jam')->state(fn (ActivitySession $r) => substr((string) $r->start_time, 0, 5).' - '.substr((string) $r->end_time, 0, 5)),
                TextEntry::make('location')->label('Tempat'),
                TextEntry::make('total_hadir')->label('Total hadir'),
                TextEntry::make('qr')->label('Check-in QR')
                    ->state(fn (ActivitySession $r) => $r->isCountOnly() ? 'Tidak dipakai (jumlah total)' : ($r->isCheckinOpen() ? 'Sedang dibuka' : 'Tertutup (hanya berlaku saat jam ibadah)'))
                    ->badge()->color(fn (ActivitySession $r) => $r->isCheckinOpen() ? 'success' : 'gray'),
                TextEntry::make('notes')->label('Catatan')->columnSpan(2),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('date')->label('Tanggal')->date('d/m/Y')->sortable(),
                TextColumn::make('start_time')->label('Jam')->formatStateUsing(fn ($state) => substr((string) $state, 0, 5)),
                TextColumn::make('activityType.name')->label('Kegiatan')->searchable(),
                TextColumn::make('location')->label('Tempat')->toggleable(),
                TextColumn::make('hadir')->label('Hadir')
                    ->state(fn (ActivitySession $r) => $r->manual_count ?? $r->attendances_count),
            ])
            ->filters([
                SelectFilter::make('activity_type_id')->label('Kegiatan')->relationship('activityType', 'name'),
                Filter::make('bulan_ini')->label('30 hari terakhir')->query(fn (Builder $q) => $q->where('date', '>=', today()->subDays(30)))->toggle(),
            ])
            ->recordActions([
                Action::make('absen')->label('Absensi')->icon(Heroicon::OutlinedDevicePhoneMobile)->color('success')
                    ->visible(fn (ActivitySession $r) => auth()->user()->kelolaKehadiran())
                    ->url(fn (ActivitySession $r) => route('absen.session', $r), true),
                Action::make('qr')->label('QR')->icon(Heroicon::OutlinedQrCode)
                    ->visible(fn (ActivitySession $r) => ! $r->isCountOnly())
                    ->url(fn (ActivitySession $r) => route('sessions.qr', $r), true),
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [AttendancesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivitySessions::route('/'),
            'create' => CreateActivitySession::route('/create'),
            'view' => ViewActivitySession::route('/{record}'),
            'edit' => EditActivitySession::route('/{record}/edit'),
        ];
    }
}
