<?php

namespace App\Filament\Resources\ActivitySessions\RelationManagers;

use App\Models\Attendance;
use App\Models\Member;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class AttendancesRelationManager extends RelationManager
{
    protected static string $relationship = 'attendances';

    protected static ?string $title = 'Daftar Hadir';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return ! $ownerRecord->isCountOnly();
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($q) => $q->with(['member.family.region', 'guest', 'user']))
            ->defaultSort('checked_in_at', 'desc')
            ->columns([
                TextColumn::make('nama')->label('Nama')->state(fn (Attendance $a) => $a->member?->name ?? ($a->guest ? $a->guest->name.' (tamu)' : '-'))
                    ->searchable(query: fn ($q, $search) => $q->where(fn ($w) => $w->whereHas('member', fn ($m) => $m->search($search))
                        ->orWhereHas('guest', fn ($g) => $g->where('name', 'like', "%{$search}%")))),
                TextColumn::make('member.code')->label('Kode'),
                TextColumn::make('member.family.region.name')->label('Wilayah'),
                TextColumn::make('checked_in_at')->label('Waktu')->dateTime('H:i'),
                TextColumn::make('method')->label('Cara')->badge()->formatStateUsing(fn ($state) => $state === 'qr' ? 'QR mandiri' : 'Petugas'),
                TextColumn::make('user.name')->label('Petugas')->toggleable(),
            ])
            ->headerActions([
                Action::make('tambah')->label('Tambah hadir')->icon(Heroicon::OutlinedPlus)
                    ->schema([
                        Select::make('member_ids')->label('Jemaat')->multiple()->searchable()
                            ->getSearchResultsUsing(fn (string $search) => Member::tercatat()->search($search)->limit(30)->get()
                                ->mapWithKeys(fn ($m) => [$m->id => "{$m->name} ({$m->code})"]))
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $s = $this->getOwnerRecord();
                        foreach ($data['member_ids'] as $id) {
                            Attendance::firstOrCreate(['activity_session_id' => $s->id, 'member_id' => $id],
                                ['checked_in_at' => now(), 'method' => 'petugas', 'user_id' => auth()->id()]);
                        }
                        Notification::make()->success()->title(count($data['member_ids']).' kehadiran dicatat')->send();
                    }),
            ])
            ->recordActions([DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
