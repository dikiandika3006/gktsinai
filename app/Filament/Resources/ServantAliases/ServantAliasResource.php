<?php

namespace App\Filament\Resources\ServantAliases;

use App\Filament\Resources\Members\MemberResource;
use App\Filament\Resources\ServantAliases\Pages\ManageServantAliases;
use App\Models\Member;
use App\Models\ServantAlias;
use App\Services\PelayanMatcher;
use App\Support\Opsi;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/** Nama pelayan di jadwal: hubungkan ke data jemaat atau tandai simpatisan / pekerja / tamu. */
class ServantAliasResource extends Resource
{
    protected static ?string $model = ServantAlias::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|\UnitEnum|null $navigationGroup = 'Pelayanan';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'Nama Pelayan';

    protected static ?string $pluralModelLabel = 'Nama Pelayan';

    public static function getNavigationBadge(): ?string
    {
        $n = ServantAlias::whereNull('member_id')->whereNull('category')->count();

        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Nama di jadwal yang belum terdaftar di data jemaat';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('member')->withCount('assignments')
            ->withMax(['assignments as terakhir' => fn ($query) => $query->join('worship_schedules', 'worship_schedules.id', '=', 'service_assignments.worship_schedule_id')], 'worship_schedules.date');
    }

    public static function memberSelect(): Select
    {
        return Select::make('member_id')->label('Jemaat')->required()->searchable()
            ->getSearchResultsUsing(fn (string $search) => Member::query()->search($search)->orderBy('name')->limit(30)->get()
                ->mapWithKeys(fn (Member $m) => [$m->id => "{$m->name} ({$m->code}) · ".Opsi::label(Opsi::KEANGGOTAAN, $m->membership_status)])->all())
            ->getOptionLabelUsing(fn ($value) => Member::find($value)?->name);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('assignments_count', 'desc')
            ->columns([
                TextColumn::make('display_name')->label('Nama di jadwal')->searchable(['display_name', 'key'])->weight('bold'),
                TextColumn::make('status')->label('Status')->badge()
                    ->state(fn (ServantAlias $r) => ServantAlias::statusLabel($r->member, $r->category))
                    ->color(fn (ServantAlias $r) => ServantAlias::statusColor($r->member, $r->category)),
                TextColumn::make('member.name')->label('Data jemaat')->placeholder('-')
                    ->description(fn (ServantAlias $r) => $r->member?->code)
                    ->url(fn (ServantAlias $r) => $r->member ? MemberResource::getUrl('view', ['record' => $r->member]) : null),
                IconColumn::make('is_manual')->label('Manual')->boolean()->trueIcon(Heroicon::OutlinedHandRaised)->falseIcon(Heroicon::OutlinedSparkles)
                    ->tooltip(fn (ServantAlias $r) => $r->is_manual ? 'Dihubungkan manual' : 'Dicocokkan otomatis'),
                TextColumn::make('assignments_count')->label('Jumlah tugas')->sortable(),
                TextColumn::make('terakhir')->label('Jadwal terakhir')->date('d/m/Y')->sortable(),
            ])
            ->filters([
                SelectFilter::make('keadaan')->label('Keadaan')->default('belum')
                    ->options([
                        'belum' => 'Belum terdaftar & belum ditandai',
                        'tidak_terdaftar' => 'Semua yang tidak terdaftar',
                        'terhubung' => 'Terhubung ke data jemaat',
                        'otomatis' => 'Terhubung otomatis (perlu dicek)',
                    ])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'belum' => $query->whereNull('member_id')->whereNull('category'),
                        'tidak_terdaftar' => $query->whereNull('member_id'),
                        'terhubung' => $query->whereNotNull('member_id'),
                        'otomatis' => $query->whereNotNull('member_id')->where('is_manual', false),
                        default => $query,
                    }),
                SelectFilter::make('category')->label('Kategori')->options(Opsi::KATEGORI_PELAYAN),
            ])
            ->recordActions([
                Action::make('hubungkan')->label('Hubungkan')->icon(Heroicon::OutlinedLink)->color('success')
                    ->visible(fn () => auth()->user()->kelolaJadwal())
                    ->modalHeading(fn (ServantAlias $r) => "Hubungkan \"{$r->display_name}\" ke data jemaat")
                    ->fillForm(fn (ServantAlias $r) => ['member_id' => $r->member_id])
                    ->schema([self::memberSelect()])
                    ->action(function (ServantAlias $record, array $data) {
                        $record->update(['member_id' => $data['member_id'], 'is_manual' => true, 'category' => null]);
                        Notification::make()->success()->title('Nama dihubungkan')->send();
                    }),
                Action::make('daftarkan')->label('Daftarkan')->icon(Heroicon::OutlinedUserPlus)->color('warning')
                    ->visible(fn (ServantAlias $r) => ! $r->member_id && auth()->user()->kelolaJadwal() && auth()->user()->kelolaJemaat())
                    ->modalHeading('Tambahkan ke data jemaat')
                    ->modalDescription('Membuat data jemaat baru (default simpatisan) lalu menghubungkan nama ini. Lengkapi data lainnya nanti di Data Jemaat.')
                    ->fillForm(function (ServantAlias $r) {
                        $u = app(PelayanMatcher::class)->urai($r->display_name);

                        return ['name' => Str::title($u['bersih']), 'gender' => $u['kelamin'], 'membership_status' => 'simpatisan'];
                    })
                    ->schema([
                        TextInput::make('name')->label('Nama lengkap')->required(),
                        Select::make('gender')->label('Jenis kelamin')->options(Opsi::KELAMIN),
                        Select::make('membership_status')->label('Status keanggotaan')->required()
                            ->options(collect(Opsi::KEANGGOTAAN)->only(Opsi::MASIH_TERCATAT)->all()),
                    ])
                    ->action(function (ServantAlias $record, array $data) {
                        $m = Member::create($data + ['activity_status' => 'aktif', 'notes' => 'Ditambahkan dari jadwal pelayanan ("'.$record->display_name.'").']);
                        $record->update(['member_id' => $m->id, 'is_manual' => true, 'category' => null]);
                        Notification::make()->success()->title("{$m->name} ({$m->code}) ditambahkan")->send();
                    }),
                Action::make('kategori')->label('Tandai')->icon(Heroicon::OutlinedTag)->color('gray')
                    ->visible(fn (ServantAlias $r) => ! $r->member_id && auth()->user()->kelolaJadwal())
                    ->fillForm(fn (ServantAlias $r) => ['category' => $r->category])
                    ->schema([Select::make('category')->label('Kategori')->options(Opsi::KATEGORI_PELAYAN)->placeholder('Belum ditandai')])
                    ->action(fn (ServantAlias $record, array $data) => $record->update(['category' => $data['category'] ?: null])),
                Action::make('lepas')->label('Lepas')->icon(Heroicon::OutlinedLinkSlash)->color('danger')->requiresConfirmation()
                    ->modalDescription('Hubungan ke data jemaat dilepas dan nama ini tidak akan dicocokkan otomatis lagi.')
                    ->visible(fn (ServantAlias $r) => $r->member_id && auth()->user()->kelolaJadwal())
                    ->action(fn (ServantAlias $record) => $record->update(['member_id' => null, 'is_manual' => true])),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageServantAliases::route('/')];
    }
}
