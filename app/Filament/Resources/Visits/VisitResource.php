<?php

namespace App\Filament\Resources\Visits;

use App\Filament\Resources\Families\FamilyResource;
use App\Filament\Resources\Visits\Pages\CreateVisit;
use App\Filament\Resources\Visits\Pages\EditVisit;
use App\Filament\Resources\Visits\Pages\ListVisits;
use App\Filament\Resources\Visits\Pages\ViewVisit;
use App\Models\Family;
use App\Models\Member;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitReport;
use App\Support\Opsi;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VisitResource extends Resource
{
    protected static ?string $model = Visit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHomeModern;

    protected static string|\UnitEnum|null $navigationGroup = 'Pastoral';

    protected static ?string $slug = 'kunjungan';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Kunjungan';

    protected static ?string $pluralModelLabel = 'Jadwal Kunjungan';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visibleTo(auth()->user())->with(['family.head', 'family.region', 'member', 'team', 'pendamping', 'report']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Jadwal (VS-02)')->columns(3)->schema([
                Select::make('family_id')->label('KK')
                    ->options(fn () => Family::visibleTo(auth()->user())->with('head')->get()->mapWithKeys(fn ($f) => [$f->id => $f->display_name]))
                    ->searchable()->live()->default(fn () => request()->integer('family_id') ?: null)
                    ->required(fn (Get $get) => ! $get('member_id')),
                Select::make('member_id')->label('Orang (opsional)')
                    ->options(fn (Get $get) => Member::query()->tercatat()
                        ->when($get('family_id'), fn ($query, $f) => $query->where('family_id', $f))
                        ->orderBy('name')->limit(500)->pluck('name', 'id'))
                    ->searchable()->helperText('Kosongkan untuk kunjungan per KK.'),
                Select::make('type')->label('Jenis')->options(Opsi::JENIS_VISITASI)->required()
                    ->default(fn () => request()->query('type', 'rutin')),
                DatePicker::make('planned_date')->label('Tanggal rencana')->required()->default(today()->addDays(7)),
                Select::make('team')->label('Tim yang ditugaskan (user)')->multiple()->relationship('team', 'name')
                    ->options(fn () => User::role(['tim_visitasi', 'pendeta', 'majelis', 'koordinator_wilayah'])->where('is_active', true)->pluck('name', 'id'))
                    ->helperText('Bisa lebih dari satu. User tim bisa membuka dan mengisi laporan kunjungan ini.')
                    ->preload()->columnSpan(2),
                Select::make('pendamping')->label('Pendamping (dari data jemaat)')->multiple()->relationship('pendamping', 'name')
                    ->getSearchResultsUsing(fn (string $search) => Member::query()->tercatat()->search($search)->orderBy('name')->limit(30)
                        ->get()->mapWithKeys(fn (Member $m) => [$m->id => $m->name.($m->alias ? " ({$m->alias})" : '')]))
                    ->searchable()->helperText('Jemaat yang ikut berkunjung tanpa akun login. Tidak mendapat akses ke laporan.')
                    ->columnSpan(3),
                Select::make('status')->label('Status')->options(Opsi::STATUS_VISITASI)->default('rencana')->required()->live(),
                DatePicker::make('realized_date')->label('Tanggal realisasi')
                    ->visible(fn (Get $get) => $get('status') === 'selesai')->default(today()),
                Textarea::make('notes')->label('Catatan jadwal')->columnSpanFull()->rows(2)
                    ->default(fn () => request()->query('reason')),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Jadwal')->columns(4)->schema([
                TextEntry::make('family.display_name')->label('KK')
                    ->url(fn (Visit $v) => $v->family ? FamilyResource::getUrl('view', ['record' => $v->family]) : null),
                TextEntry::make('member.name')->label('Orang'),
                TextEntry::make('family.region.name')->label('Wilayah'),
                TextEntry::make('type')->label('Jenis')->formatStateUsing(fn ($state) => Opsi::label(Opsi::JENIS_VISITASI, $state)),
                TextEntry::make('planned_date')->label('Rencana')->date('d/m/Y'),
                TextEntry::make('realized_date')->label('Realisasi')->date('d/m/Y'),
                TextEntry::make('status')->badge()->formatStateUsing(fn ($state) => Opsi::label(Opsi::STATUS_VISITASI, $state)),
                TextEntry::make('team.name')->label('Tim')->badge(),
                TextEntry::make('pendamping.name')->label('Pendamping')->badge()->color('gray')->placeholder('-'),
                TextEntry::make('family.address')->label('Alamat')->columnSpan(2)->visible(fn () => auth()->user()->lihatKontak()),
                TextEntry::make('notes')->label('Catatan')->columnSpan(2),
            ]),
            Section::make('Laporan kunjungan (VS-04)')->columns(2)
                ->visible(fn (Visit $v) => $v->report !== null)
                ->schema(fn (Visit $v) => $v->report && $v->report->canBeReadBy(auth()->user()) ? [
                    TextEntry::make('report.daftar_hadir')->label('Yang hadir')->placeholder('-'),
                    TextEntry::make('report.author.name')->label('Ditulis oleh')
                        ->suffix(fn () => $v->report->is_confidential ? '  (RAHASIA)' : ''),
                    TextEntry::make('report.conversation')->label('Pokok percakapan')->columnSpanFull(),
                    TextEntry::make('report.prayer_points')->label('Pokok doa')->columnSpanFull(),
                    TextEntry::make('report.follow_up')->label('Tindak lanjut')->columnSpanFull(),
                ] : [
                    TextEntry::make('hidden')->label('')->state($v->report?->is_confidential
                        ? 'Laporan bertanda rahasia: hanya dapat dibaca pendeta dan penulisnya.'
                        : 'Ringkasan: kunjungan telah dilaporkan. Isi catatan pastoral tidak ditampilkan untuk peran Anda.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('planned_date', 'desc')
            ->columns([
                TextColumn::make('planned_date')->label('Rencana')->date('d/m/Y')->sortable(),
                TextColumn::make('target_name')->label('KK / Orang')
                    ->searchable(query: fn ($query, $search) => $query->whereHas('family', fn ($f) => $f->where('code', 'like', "%{$search}%")
                        ->orWhereHas('head', fn ($h) => $h->where('name', 'like', "%{$search}%")))->orWhereHas('member', fn ($m) => $m->where('name', 'like', "%{$search}%"))),
                TextColumn::make('family.region.name')->label('Wilayah'),
                TextColumn::make('type')->label('Jenis')->badge()->formatStateUsing(fn ($state) => Opsi::label(Opsi::JENIS_VISITASI, $state)),
                TextColumn::make('team.name')->label('Tim')->badge(),
                TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => Opsi::label(Opsi::STATUS_VISITASI, $state))
                    ->color(fn ($state) => match ($state) {
                        'selesai' => 'success', 'batal' => 'gray', default => 'warning'
                    }),
                TextColumn::make('realized_date')->label('Realisasi')->date('d/m/Y')->toggleable(),
                TextColumn::make('report.id')->label('Laporan')->formatStateUsing(fn ($state) => $state ? 'Ada' : null)->placeholder('Belum'),
            ])
            ->filters([
                SelectFilter::make('status')->options(Opsi::STATUS_VISITASI),
                SelectFilter::make('type')->label('Jenis')->options(Opsi::JENIS_VISITASI),
                SelectFilter::make('region')->label('Wilayah')->relationship('family.region', 'name'),
                Filter::make('saya')->label('Tugas saya')->toggle()->query(fn (Builder $query) => $query->whereHas('team', fn ($t) => $t->whereKey(auth()->id()))),
            ])
            ->recordActions([
                self::reportAction(),
                ViewAction::make(),
                EditAction::make(),
            ]);
    }

    /** VS-04: isi laporan kunjungan. */
    public static function reportAction(): Action
    {
        return Action::make('laporan')->label('Isi laporan')->icon(Heroicon::OutlinedPencilSquare)->color('success')
            ->visible(fn (Visit $v) => auth()->user()->can('report', $v)
                && (! $v->report || $v->report->user_id === auth()->id() || auth()->user()->kelolaVisitasi()))
            ->fillForm(fn (Visit $v) => $v->report && $v->report->canBeReadBy(auth()->user())
                ? $v->report->only(['attendee_member_ids', 'attendees', 'conversation', 'prayer_points', 'follow_up', 'is_confidential']) + ['realized_date' => $v->realized_date ?? today()]
                : ['realized_date' => $v->realized_date ?? today(), 'attendee_member_ids' => array_filter([$v->member_id])])
            ->schema([
                DatePicker::make('realized_date')->label('Tanggal kunjungan')->required(),
                CheckboxList::make('attendee_member_ids')->label('Anggota KK yang hadir')
                    ->options(fn (Visit $v) => self::anggotaUntukLaporan($v))
                    ->visible(fn (Visit $v) => self::anggotaUntukLaporan($v) !== [])
                    ->columns(2)->bulkToggleable(),
                Textarea::make('attendees')->label('Yang hadir lainnya (kerabat, tamu)')->rows(2),
                Textarea::make('conversation')->label('Pokok percakapan')->rows(4),
                Textarea::make('prayer_points')->label('Pokok doa')->rows(3),
                Textarea::make('follow_up')->label('Tindak lanjut')->rows(2),
                Toggle::make('is_confidential')->label('Rahasia (hanya pendeta & penulis)'),
            ])
            ->action(function (Visit $v, array $data) {
                $v->update(['status' => 'selesai', 'realized_date' => $data['realized_date']]);
                VisitReport::updateOrCreate(['visit_id' => $v->id], collect($data)->except('realized_date')->all()
                    + ['user_id' => $v->report?->user_id ?? auth()->id()]);
                Notification::make()->success()->title('Laporan kunjungan disimpan')->send();
            });
    }

    /** Anggota KK yang dikunjungi (atau orangnya bila tanpa KK), untuk dicentang di laporan. @return array<int,string> */
    public static function anggotaUntukLaporan(Visit $v): array
    {
        return Member::query()->tercatat()
            ->when($v->family_id, fn ($query) => $query->where('family_id', $v->family_id), fn ($query) => $query->whereKey($v->member_id))
            ->orderByRaw("family_relation = 'kepala' desc")->orderBy('birth_date')
            ->pluck('name', 'id')->all();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVisits::route('/'),
            'create' => CreateVisit::route('/create'),
            'view' => ViewVisit::route('/{record}'),
            'edit' => EditVisit::route('/{record}/edit'),
        ];
    }
}
