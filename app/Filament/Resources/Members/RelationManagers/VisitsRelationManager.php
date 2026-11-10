<?php

namespace App\Filament\Resources\Members\RelationManagers;

use App\Filament\Resources\Visits\VisitResource;
use App\Models\Member;
use App\Models\Visit;
use App\Support\Opsi;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/** Riwayat kunjungan di profil jemaat: kunjungan ke KK-nya dan kunjungan khusus ke dirinya. */
class VisitsRelationManager extends RelationManager
{
    protected static string $relationship = 'kunjunganKeluarga';

    protected static ?string $title = 'Visitasi';

    protected static ?string $modelLabel = 'kunjungan';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return (bool) auth()->user()?->can('viewAny', Visit::class);
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $n = self::relasiUntuk($ownerRecord)->where('status', 'selesai')->count();

        return $n ? (string) $n : null;
    }

    /** Jemaat tanpa KK: hanya kunjungan khusus ke dirinya. */
    private static function relasiUntuk(Model $m): Relation
    {
        /** @var Member $m */
        return $m->family_id ? $m->kunjunganKeluarga() : $m->visits();
    }

    public function getRelationship(): Relation
    {
        return self::relasiUntuk($this->getOwnerRecord());
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        $member = $this->getOwnerRecord();

        return $table
            ->modifyQueryUsing(fn ($query) => $query->visibleTo(auth()->user())->with(['member', 'team', 'report']))
            ->defaultSort('planned_date', 'desc')
            ->emptyStateHeading('Belum ada kunjungan')
            ->columns([
                TextColumn::make('planned_date')->label('Rencana')->date('d/m/Y')->sortable(),
                TextColumn::make('realized_date')->label('Realisasi')->date('d/m/Y')->placeholder('-'),
                TextColumn::make('type')->label('Jenis')->formatStateUsing(fn ($state) => Opsi::label(Opsi::JENIS_VISITASI, $state)),
                TextColumn::make('member.name')->label('Untuk')
                    ->state(fn (Visit $record) => $record->member?->name ?? 'Seluruh KK')
                    ->weight(fn (Visit $record) => $record->member_id === $member->id ? 'bold' : null),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn ($state) => Opsi::label(Opsi::STATUS_VISITASI, $state))
                    ->color(fn ($state) => match ($state) { 'selesai' => 'success', 'batal' => 'gray', default => 'warning' }),
                TextColumn::make('team.name')->label('Tim')->badge(),
                TextColumn::make('report.follow_up')->label('Tindak lanjut')->limit(60)->wrap()
                    ->state(fn (Visit $record) => $record->report?->canBeReadBy(auth()->user()) ? $record->report->follow_up : null)
                    ->placeholder('-'),
            ])
            ->headerActions([
                Action::make('jadwalkan')->label('Jadwalkan kunjungan')->icon(Heroicon::OutlinedCalendarDays)
                    ->visible(fn () => auth()->user()->can('create', Visit::class))
                    ->url(fn () => VisitResource::getUrl('create').'?'.http_build_query(array_filter([
                        'family_id' => $member->family_id, 'member_id' => $member->family_id ? null : $member->id,
                    ]))),
            ])
            ->recordActions([
                Action::make('buka')->label('Buka')->icon(Heroicon::OutlinedEye)
                    ->url(fn (Visit $record) => VisitResource::getUrl('view', ['record' => $record])),
            ]);
    }
}
