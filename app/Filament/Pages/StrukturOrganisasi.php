<?php

namespace App\Filament\Pages;

use App\Filament\Resources\OrgUnits\OrgUnitResource;
use App\Models\OrgPeriod;
use App\Models\OrgPosition;
use App\Models\OrgUnit;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;

/** Bagan struktur pertanggungjawaban pelayanan per periode. */
class StrukturOrganisasi extends Page
{
    protected string $view = 'filament.pages.struktur-organisasi';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static string|\UnitEnum|null $navigationGroup = 'Pelayanan';

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'struktur-organisasi';

    protected static ?string $title = 'Struktur Organisasi';

    public ?int $periode = null;

    public function mount(): void
    {
        $this->periode = OrgPeriod::aktif()?->id;
    }

    public static function canAccess(): bool
    {
        return auth()->user()->lihatSemuaJemaat();
    }

    protected function getHeaderActions(): array
    {
        $kelola = fn () => auth()->user()->kelolaJadwal();

        return [
            Action::make('cetak')->label('Cetak')->icon(Heroicon::OutlinedPrinter)->color('gray')
                ->alpineClickHandler('window.print()'),
            Action::make('kelola')->label('Kelola struktur')->icon(Heroicon::OutlinedPencilSquare)->color('gray')
                ->visible($kelola)->url(fn () => OrgUnitResource::getUrl('index')),
            Action::make('periodeBaru')->label('Periode baru')->icon(Heroicon::OutlinedPlus)
                ->visible($kelola)
                ->modalDescription('Buat periode kepengurusan baru. Struktur periode yang sedang dilihat bisa disalin sebagai awal, lalu diubah seperlunya.')
                ->schema([
                    TextInput::make('name')->label('Nama periode')->required()->placeholder('mis. 2028–2030'),
                    DatePicker::make('start_date')->label('Mulai')->required(),
                    DatePicker::make('end_date')->label('Selesai')->required()->after('start_date'),
                    Toggle::make('salin')->label('Salin struktur & pengurus dari periode yang sedang dilihat')->default(true),
                    Toggle::make('is_active')->label('Jadikan periode aktif')->default(true),
                ])
                ->action(function (array $data) {
                    $baru = DB::transaction(function () use ($data) {
                        $p = OrgPeriod::create(collect($data)->except('salin')->all());
                        if ($data['salin'] && $this->periode) {
                            $this->salinStruktur($this->periode, $p->id);
                        }

                        return $p;
                    });
                    $this->periode = $baru->id;
                    Notification::make()->success()->title("Periode {$baru->name} dibuat")->send();
                }),
            Action::make('aktifkan')->label('Jadikan periode aktif')->icon(Heroicon::OutlinedCheckCircle)->color('gray')
                ->visible(fn () => $kelola() && $this->periode && ! OrgPeriod::find($this->periode)?->is_active)
                ->requiresConfirmation()
                ->action(fn () => OrgPeriod::find($this->periode)?->update(['is_active' => true])),
        ];
    }

    private function salinStruktur(int $dari, int $ke): void
    {
        $peta = [];
        $units = OrgUnit::with('positions')->where('org_period_id', $dari)->orderByRaw('parent_id IS NOT NULL')->orderBy('sort')->get();
        foreach ($units as $u) {
            $baru = $u->replicate(['org_period_id', 'parent_id']);
            $baru->org_period_id = $ke;
            $baru->parent_id = $u->parent_id ? ($peta[$u->parent_id] ?? null) : null;
            $baru->save();
            $peta[$u->id] = $baru->id;
            foreach ($u->positions as $pos) {
                $baru->positions()->create($pos->only(['role', 'member_id', 'name', 'note', 'sort']));
            }
        }
    }

    protected function getViewData(): array
    {
        $periode = $this->periode ? OrgPeriod::find($this->periode) : null;
        $units = $periode ? OrgUnit::with(['positions.member', 'children.positions.member', 'commission'])
            ->where('org_period_id', $periode->id)->whereNull('parent_id')->orderBy('sort')->get() : collect();

        $pimpinan = $units->where('type', 'pimpinan');
        $majelis = $pimpinan->first(fn ($u) => $u->positions->whereIn('role', ['ketua_majelis', 'penatua'])->isNotEmpty()) ?? $pimpinan->first();

        // Ringkasan per penatua: tugas yang dipegang di periode ini.
        $tugas = $periode ? OrgPosition::with('unit')->whereHas('unit', fn ($query) => $query->where('org_period_id', $periode->id))
            ->whereIn('role', ['ketua_majelis', 'sekretaris_mj', 'penatua_pj', 'majelis_pendamping'])->get() : collect();
        $penatua = $majelis ? $majelis->positions->whereIn('role', ['penatua', 'ketua_majelis'])
            ->unique(fn ($p) => $p->member_id ?: mb_strtolower($p->display_name))
            ->map(function (OrgPosition $p) use ($tugas) {
                $milik = $tugas->filter(fn (OrgPosition $t) => $p->member_id ? $t->member_id === $p->member_id
                    : mb_strtolower($t->display_name) === mb_strtolower($p->display_name));

                return [
                    'posisi' => $p,
                    'tugas' => $milik->map(fn (OrgPosition $t) => match ($t->role) {
                        'majelis_pendamping' => 'MP '.$t->unit->name,
                        'penatua_pj' => 'Bidang '.$t->unit->name.($t->note ? " ({$t->note})" : ''),
                        default => $t->role_label,
                    })->unique()->values()->all(),
                ];
            })->values() : collect();

        return [
            'periodeOpsi' => OrgPeriod::orderByDesc('start_date')->get(),
            'periodeAktif' => $periode,
            'majelis' => $majelis,
            'pimpinanLain' => $pimpinan->reject(fn ($u) => $u->is($majelis))->values(),
            'sekretariat' => $units->where('type', 'sekretariat')->values(),
            'bidang' => $units->whereIn('type', ['bidang', 'tim'])->values(),
            'komisi' => $units->where('type', 'komisi')->values(),
            'penatua' => $penatua,
            'bisaKelola' => auth()->user()->kelolaJadwal(),
        ];
    }
}
