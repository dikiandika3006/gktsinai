<?php

namespace App\Filament\Resources\WorshipSchedules;

use App\Filament\Resources\WorshipSchedules\Pages\CreateWorshipSchedule;
use App\Filament\Resources\WorshipSchedules\Pages\EditWorshipSchedule;
use App\Filament\Resources\WorshipSchedules\Pages\ListWorshipSchedules;
use App\Filament\Resources\WorshipSchedules\Pages\ViewWorshipSchedule;
use App\Models\ServiceAssignment;
use App\Models\WorshipSchedule;
use App\Support\Opsi;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Jadwal ibadah Minggu: pembicara, tema, dan petugas pelayanan per kebaktian. */
class WorshipScheduleResource extends Resource
{
    protected static ?string $model = WorshipSchedule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendar;

    protected static string|\UnitEnum|null $navigationGroup = 'Pelayanan';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Jadwal Ibadah';

    protected static ?string $pluralModelLabel = 'Jadwal Ibadah';

    public static function kebaktianOpsi(): array
    {
        return ['KU I' => 'Ibadah Umum I', 'KU II' => 'Ibadah Umum II'];
    }

    /** Baris kosong untuk jadwal baru, mengikuti format jadwal gereja. */
    public static function templatePetugas(): array
    {
        $baris = [['peran' => 'pembicara', 'kebaktian' => null, 'nama' => null]];
        foreach (['liturgis' => 1, 'singer' => 2, 'pewarta' => 1, 'lcd' => 1, 'penyambut' => 2] as $peran => $n) {
            foreach (array_keys(self::kebaktianOpsi()) as $k) {
                for ($i = 0; $i < $n; $i++) {
                    $baris[] = ['peran' => $peran, 'kebaktian' => $k, 'nama' => null];
                }
            }
        }

        return $baris;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Ibadah')->columns(3)->columnSpanFull()->schema([
                DatePicker::make('date')->label('Tanggal')->required()->unique(ignoreRecord: true)
                    ->default(today()->next('Sunday')),
                TextInput::make('tema')->label('Tema')->columnSpan(2),
                TextInput::make('ayat_referensi')->label('Ayat referensi')->columnSpan(2),
                TextInput::make('introitus')->label('Introitus'),
                TextInput::make('phb')->label('PHB'),
                TextInput::make('keterangan')->label('Keterangan')->columnSpan(2)->placeholder('mis. Stola Hijau / Perjamuan Kudus'),
            ]),
            Section::make('Petugas pelayanan')->columnSpanFull()
                ->description('Satu baris satu orang. "Kel. Bp. ..." untuk satu keluarga. Kebaktian kosong = melayani di kedua kebaktian. Baris yang namanya kosong diabaikan.')
                ->schema([
                    Repeater::make('petugas')->hiddenLabel()
                        ->table([
                            TableColumn::make('Peran')->width('30%'),
                            TableColumn::make('Kebaktian')->width('25%'),
                            TableColumn::make('Nama'),
                        ])
                        ->compact()
                        ->schema([
                            Select::make('peran')->options(Opsi::PERAN_PELAYANAN)->required(),
                            Select::make('kebaktian')->options(self::kebaktianOpsi())->placeholder('KU I & II'),
                            TextInput::make('nama')->placeholder('mis. Pnt. Anis W.'),
                        ])
                        ->default(fn () => self::templatePetugas())
                        ->addActionLabel('Tambah petugas')
                        ->reorderable(false),
                ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            ViewEntry::make('grid')->hiddenLabel()->columnSpanFull()
                ->view('filament.jadwal.grid-entry'),
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['assignments.member', 'assignments.alias']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('date')->label('Tanggal')->date('d M Y')->sortable(),
                TextColumn::make('tema')->label('Tema')->searchable()->wrap()
                    ->description(fn (WorshipSchedule $r) => $r->keterangan),
                TextColumn::make('pembicara')->label('Pembicara')
                    ->state(fn (WorshipSchedule $r) => $r->assignments->where('peran', 'pembicara')->pluck('nama')->unique()->join(', ')),
                TextColumn::make('petugas')->label('Petugas')
                    ->state(fn (WorshipSchedule $r) => $r->assignments->count()),
                TextColumn::make('belum')->label('Belum terdaftar')->badge()
                    ->state(fn (WorshipSchedule $r) => $r->assignments->filter(fn (ServiceAssignment $a) => ! $a->member_id && ! $a->alias?->category)->count())
                    ->color(fn ($state) => $state ? 'danger' : 'success'),
            ])
            ->filters([
                Filter::make('mendatang')->label('Mulai hari ini')->toggle()
                    ->query(fn (Builder $query) => $query->where('date', '>=', today())),
            ])
            ->recordActions([ViewAction::make(), EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWorshipSchedules::route('/'),
            'create' => CreateWorshipSchedule::route('/create'),
            'view' => ViewWorshipSchedule::route('/{record}'),
            'edit' => EditWorshipSchedule::route('/{record}/edit'),
        ];
    }
}
