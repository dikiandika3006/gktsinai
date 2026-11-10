<?php

namespace App\Filament\Actions;

use App\Filament\Resources\Families\FamilyResource;
use App\Models\Family;
use App\Models\Member;
use App\Models\Region;
use App\Services\KeluargaService;
use App\Support\Opsi;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/** Aksi merapikan keluarga, dipakai di Data Jemaat dan di halaman KK. */
class KeluargaActions
{
    private static function boleh(): bool
    {
        return (bool) auth()->user()?->kelolaJemaat();
    }

    /** Lihat seluruh anggota KK dari baris jemaat (panel samping). */
    public static function lihatKeluarga(): Action
    {
        return Action::make('keluarga')->label('Keluarga')->icon(Heroicon::OutlinedHome)
            ->visible(fn (Member $record) => (bool) $record->family_id)
            ->modalHeading(fn (Member $record) => 'Keluarga '.($record->family->head?->name ?? $record->family->code))
            ->modalDescription(fn (Member $record) => $record->family->code.($record->family->region ? ' · '.$record->family->region->name : ''))
            ->modalContent(fn (Member $record) => view('filament.keluarga.anggota', ['family' => $record->family, 'current' => $record]))
            ->slideOver()
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Tutup')
            ->extraModalFooterActions(fn (Member $record) => [
                Action::make('buka_kk')->label('Buka halaman KK')->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(FamilyResource::getUrl('view', ['record' => $record->family_id])),
            ]);
    }

    public static function jadikanKepala(): Action
    {
        return Action::make('jadikan_kepala')->label('Jadikan kepala KK')->icon(Heroicon::OutlinedStar)
            ->visible(fn (Member $record) => self::boleh() && $record->family_id && $record->family->head_member_id !== $record->id)
            ->modalHeading(fn (Member $record) => "Jadikan {$record->name} kepala {$record->family->code}?")
            ->modalSubmitActionLabel('Jadikan kepala')
            ->schema(fn (Member $record) => array_filter([
                ($lama = $record->family->head) && $lama->family_id === $record->family_id
                    ? Select::make('hubungan_lama')->label("Hubungan {$lama->name} (kepala lama) setelah ini")
                        ->options(collect(Opsi::HUBUNGAN)->except('kepala'))
                        ->default(self::tebakPasangan($lama, $record))->required()
                    : null,
            ]))
            ->action(function (Member $record, array $data) {
                app(KeluargaService::class)->jadikanKepala($record, $data['hubungan_lama'] ?? 'lainnya');
                Notification::make()->success()->title("{$record->name} sekarang kepala keluarga")->send();
            });
    }

    /** Pindah satu jemaat ke KK lain atau ke KK baru. */
    public static function pindah(): Action
    {
        return Action::make('pindah_kk')->label('Pindah KK')->icon(Heroicon::OutlinedArrowsRightLeft)
            ->visible(fn () => self::boleh())
            ->modalHeading(fn (Member $record) => "Pindah KK: {$record->name}")
            ->modalDescription(fn (Member $record) => $record->family ? 'Sekarang di '.$record->family->display_name : 'Belum masuk KK')
            ->modalSubmitActionLabel('Pindahkan')
            ->schema(fn (Member $record) => self::formPindah(collect([$record])))
            ->action(fn (Member $record, array $data) => self::jalankan(collect([$record]), $data));
    }

    /** Pindah beberapa jemaat sekaligus, mis. pasangan + anak yang membentuk KK baru. */
    public static function pindahBulk(): BulkAction
    {
        return BulkAction::make('pindah_kk')->label('Pindah KK')->icon(Heroicon::OutlinedArrowsRightLeft)
            ->visible(fn () => self::boleh())
            ->modalHeading('Pindah KK jemaat terpilih')
            ->modalSubmitActionLabel('Pindahkan')
            ->schema(fn (Collection $records) => self::formPindah($records))
            ->action(fn (Collection $records, array $data) => self::jalankan($records, $data))
            ->deselectRecordsAfterCompletion();
    }

    /** @param Collection<int, Member> $members */
    private static function formPindah(Collection $members): array
    {
        $members = (new \Illuminate\Database\Eloquent\Collection($members->all()))->loadMissing('family.head', 'family.members');
        $satu = $members->count() === 1;
        $asal = $members->pluck('family_id')->filter()->unique();

        $fields = [
            Radio::make('mode')->label('Pindah ke')->default('ada')->live()->inline()
                ->options(['ada' => 'KK yang sudah ada', 'baru' => 'Buat KK baru']),
            Select::make('family_id')->label('KK tujuan')
                ->helperText('Cari kode KK, nama kepala keluarga, atau alamat.')
                ->searchable()
                ->getSearchResultsUsing(fn (string $search) => Family::query()->visibleTo(auth()->user())
                    ->whereNotIn('id', $asal->count() === 1 ? $asal : [])
                    ->where(fn (Builder $q) => $q->where('code', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%")
                        ->orWhereHas('head', fn (Builder $h) => $h->where('name', 'like', "%{$search}%")))
                    ->with('head')->limit(30)->get()->mapWithKeys(fn (Family $f) => [$f->id => $f->display_name]))
                ->getOptionLabelUsing(fn ($value) => Family::find($value)?->display_name)
                ->visible(fn (Get $get) => $get('mode') === 'ada')
                ->required(fn (Get $get) => $get('mode') === 'ada')
                ->live(),
        ];

        if ($satu) {
            $m = $members->first();
            $fields[] = Select::make('hubungan')->label('Hubungan di KK tujuan')
                ->options(collect(Opsi::HUBUNGAN)->except('kepala'))
                ->default($m->family_relation !== 'kepala' ? $m->family_relation : null)
                ->visible(fn (Get $get) => $get('mode') === 'ada' && ! $get('jadi_kepala'))
                ->required(fn (Get $get) => $get('mode') === 'ada' && ! $get('jadi_kepala'));
            $fields[] = Toggle::make('jadi_kepala')->label('Jadikan kepala di KK tujuan')->live()
                ->helperText('Kepala lama di KK tujuan akan menjadi "Lainnya"; ubah lewat "Jadikan kepala KK" bila perlu.')
                ->visible(fn (Get $get) => $get('mode') === 'ada');
        } else {
            $fields[] = Select::make('kepala_id')->label('Kepala keluarga')
                ->options($members->pluck('name', 'id'))
                ->helperText(fn (Get $get) => $get('mode') === 'ada' ? 'Kosongkan bila kepala KK tujuan tetap.' : 'Yang lain tetap dengan hubungannya sekarang.')
                ->required(fn (Get $get) => $get('mode') === 'baru');
        }

        // Data KK baru: default salin alamat & wilayah dari KK asal.
        $sumber = $members->first()?->family;
        $fields[] = Textarea::make('address')->label('Alamat KK baru')->rows(2)->default($sumber?->address)
            ->visible(fn (Get $get) => $get('mode') === 'baru');
        $fields[] = Select::make('region_id')->label('Wilayah')->default($sumber?->region_id)
            ->options(fn () => Region::orderBy('name')->pluck('name', 'id'))
            ->visible(fn (Get $get) => $get('mode') === 'baru');
        $fields[] = TextInput::make('phone')->label('No. HP keluarga')->tel()
            ->visible(fn (Get $get) => $get('mode') === 'baru' && auth()->user()->lihatKontak());

        // Yang dipindah adalah kepala KK lama yang masih punya anggota lain: tanyakan penggantinya.
        foreach ($members as $m) {
            $f = $m->family;
            if (! $f || $f->head_member_id !== $m->id) {
                continue;
            }
            $sisa = $f->members->whereNotIn('id', $members->pluck('id'));
            if ($sisa->isEmpty()) {
                continue;
            }
            $fields[] = Select::make("kepala_pengganti.{$f->id}")->label("Kepala baru untuk {$f->code} (ditinggal {$m->name})")
                ->options($sisa->pluck('name', 'id'))
                ->default(self::tebakPengganti($m, $sisa)?->id)
                ->placeholder('Belum ditentukan');
        }

        return $fields;
    }

    private static function jalankan(Collection $members, array $data): void
    {
        $baru = $data['mode'] === 'baru';
        $tujuan = $baru ? null : Family::visibleTo(auth()->user())->find($data['family_id']);
        if (! $baru && ! $tujuan) {
            Notification::make()->danger()->title('KK tujuan tidak ditemukan')->send();

            return;
        }
        $satu = $members->count() === 1;
        $kepalaId = $satu ? (($baru || ! empty($data['jadi_kepala'])) ? $members->first()->id : null) : ($data['kepala_id'] ?? null);

        try {
            $hasil = app(KeluargaService::class)->pindah($members, $tujuan, [
                'hubungan' => $satu && ! $baru ? ($data['hubungan'] ?? null) : null,
                'kepala_id' => $kepalaId ? (int) $kepalaId : null,
                'kepala_pengganti' => array_map('intval', array_filter($data['kepala_pengganti'] ?? [])),
                'data_kk' => $baru ? collect($data)->only(['address', 'region_id', 'phone'])->all() : [],
            ]);
        } catch (InvalidArgumentException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();

            return;
        }

        Notification::make()->success()
            ->title($members->count().' jemaat dipindah ke '.$hasil['family']->display_name)
            ->body(implode("\n", $hasil['catatan']) ?: null)
            ->actions([Action::make('lihat')->label('Buka KK')->url(FamilyResource::getUrl('view', ['record' => $hasil['family']]))])
            ->persistent()
            ->send();
    }

    /** Kepala lama biasanya pasangan kepala baru. */
    private static function tebakPasangan(Member $lama, Member $baru): string
    {
        if (in_array($baru->family_relation, ['suami', 'istri'], true)) {
            return $lama->gender === 'P' ? 'istri' : 'suami';
        }

        return in_array($baru->family_relation, ['anak'], true) ? 'orang_tua' : 'lainnya';
    }

    /** Pengganti kepala yang wajar: pasangan, lalu anggota tertua. */
    private static function tebakPengganti(Member $kepala, Collection $sisa): ?Member
    {
        return $sisa->first(fn (Member $x) => in_array($x->family_relation, ['istri', 'suami'], true))
            ?? $sisa->sortBy(fn (Member $x) => $x->birth_date ?? now())->first();
    }
}
