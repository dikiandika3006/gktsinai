<?php

namespace App\Filament\Resources\Members\RelationManagers;

use App\Models\ActivitySession;
use App\Models\Attendance;
use App\Models\Member;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Riwayat kehadiran pribadi + ringkasan 3 bulan terakhir. */
class AttendancesRelationManager extends RelationManager
{
    protected static string $relationship = 'attendances';

    protected static ?string $title = 'Kehadiran';

    protected static ?string $modelLabel = 'kehadiran';

    public function isReadOnly(): bool
    {
        return true;
    }

    /**
     * Ringkasan Ibadah Umum 3 bulan terakhir. KU I / KU II / Gabungan di tanggal yang sama dihitung satu ibadah.
     *
     * @return array{hadir:int, total:int, persen:?int, terakhir:?string}
     */
    public static function ringkasan(Member $m): array
    {
        $dari = today()->subMonths(3);
        $tanggalIbadah = ActivitySession::query()
            ->whereHas('activityType', fn ($q) => $q->where('is_general_worship', true))
            ->whereDate('date', '>=', $dari)->whereDate('date', '<=', today())
            ->distinct()->pluck('date')->map(fn ($d) => substr((string) $d, 0, 10))->unique();
        $hadir = $m->attendances()->whereHas('session', fn ($q) => $q->whereHas('activityType', fn ($t) => $t->where('is_general_worship', true))
            ->whereDate('date', '>=', $dari))->with('session:id,date')->get()
            ->map(fn (Attendance $a) => $a->session->date->toDateString())->unique();
        $terakhir = $m->attendances()->join('activity_sessions', 'activity_sessions.id', '=', 'attendances.activity_session_id')
            ->max('activity_sessions.date');

        return [
            'hadir' => $hadir->count(),
            'total' => $tanggalIbadah->count(),
            'persen' => $tanggalIbadah->count() ? (int) round($hadir->count() / $tanggalIbadah->count() * 100) : null,
            'terakhir' => $terakhir ? \Illuminate\Support\Carbon::parse($terakhir)->format('d/m/Y') : null,
        ];
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $r = self::ringkasan($ownerRecord);

        return $r['persen'] !== null ? $r['persen'].'%' : null;
    }

    public function table(Table $table): Table
    {
        $r = self::ringkasan($this->getOwnerRecord());

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('session.activityType')
                ->join('activity_sessions', 'activity_sessions.id', '=', 'attendances.activity_session_id')
                ->select('attendances.*')->orderByDesc('activity_sessions.date'))
            ->description('Ibadah Umum 3 bulan terakhir: hadir '.$r['hadir'].' dari '.$r['total'].' Minggu'
                .($r['persen'] !== null ? ' ('.$r['persen'].'%)' : '').' · terakhir hadir: '.($r['terakhir'] ?? 'belum pernah'))
            ->emptyStateHeading('Belum ada catatan kehadiran')
            ->columns([
                TextColumn::make('session.date')->label('Tanggal')->date('D, d/m/Y'),
                TextColumn::make('session.activityType.name')->label('Ibadah / kegiatan')
                    ->description(fn (Attendance $record) => $record->session?->kebaktian),
                TextColumn::make('method')->label('Cara')->badge()->color('gray')
                    ->formatStateUsing(fn ($state) => match ($state) { 'petugas' => 'Petugas', 'qr' => 'Check-in QR', default => $state }),
                TextColumn::make('checked_in_at')->label('Jam')->time('H:i')->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('jenis')->label('Ibadah / kegiatan')
                    ->options(fn () => \App\Models\ActivityType::orderBy('name')->pluck('name', 'id'))
                    ->query(fn ($query, array $data) => $data["value"] ? $query->where('activity_sessions.activity_type_id', $data['value']) : $query),
            ])
            ->paginated([10, 25, 50]);
    }
}
