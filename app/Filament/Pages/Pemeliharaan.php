<?php

namespace App\Filament\Pages;

use App\Jobs\GeocodeFamily;
use App\Models\Family;
use App\Services\PaketData;
use App\Support\Opsi;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

/** Menjalankan perintah artisan dari panel, untuk hosting tanpa SSH. Khusus super admin. */
class Pemeliharaan extends Page
{
    protected string $view = 'filament.pages.pemeliharaan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static string|\UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 5;

    protected static ?string $title = 'Pemeliharaan Sistem';

    public ?string $output = null;

    public array $pending = [];

    /** Tab aktif: database | data | peta | paket (diingat di URL). */
    #[\Livewire\Attributes\Url]
    public string $tab = 'database';

    public static function canAccess(): bool
    {
        return auth()->user()->peran('super_admin');
    }

    public function mount(): void
    {
        $this->loadPending();
    }

    protected function loadPending(): void
    {
        Artisan::call('migrate:status', ['--pending' => true]);
        $this->pending = collect(explode("\n", Artisan::output()))
            ->filter(fn ($l) => str_contains($l, 'Pending'))
            ->map(fn ($l) => trim(preg_replace('/\s*\.+\s*Pending\s*$/', '', $l)))
            ->values()->all();
    }

    protected function run(string $judul, string $perintah, array $param = []): void
    {
        try {
            $kode = Artisan::call($perintah, $param);
            $this->output = "$ php artisan {$perintah}\n".trim(Artisan::output());
            Notification::make()->title($judul.($kode === 0 ? ' selesai' : ' gagal'))
                ->{$kode === 0 ? 'success' : 'danger'}()->send();
        } catch (\Throwable $e) {
            $this->output = "$ php artisan {$perintah}\n".$e->getMessage();
            Notification::make()->title($judul.' gagal')->body(str($e->getMessage())->limit(200))->danger()->send();
        }
        $this->loadPending();
    }

    // ---- Proses pin alamat (OSM) bertahap lewat wire:poll: tanpa SSH, tanpa queue worker ----

    public bool $pinJalan = false;

    /** @var list<int> */
    public array $pinAntrean = [];

    public int $pinTotal = 0;

    /** @var array<string, int> */
    public array $pinHasil = [];

    /** @var list<string> */
    public array $pinLog = [];

    protected function queryPin(string $cakupan)
    {
        return Family::query()->whereNotNull('address')->where('address', '!=', '')
            ->where('geocode_status', '!=', 'manual')
            ->when($cakupan === 'belum', fn ($q) => $q->where('geocode_status', 'belum'))
            ->when($cakupan === 'ulang', fn ($q) => $q->whereIn('geocode_status', ['belum', 'gagal', 'perkiraan']));
    }

    public function mulaiPin(string $cakupan): void
    {
        $this->pinAntrean = $this->queryPin($cakupan)->orderBy('id')->pluck('id')->all();
        $this->pinTotal = count($this->pinAntrean);
        $this->pinHasil = [];
        $this->pinLog = [];
        $this->pinJalan = $this->pinTotal > 0;
        $this->tab = 'peta';
        if (! $this->pinJalan) {
            Notification::make()->success()->title('Tidak ada KK yang perlu diproses')->send();
        }
    }

    /** Dipanggil berulang oleh wire:poll; tiap panggilan memproses beberapa KK (aman dari batas waktu PHP). */
    public function prosesPin(): void
    {
        if (! $this->pinJalan) {
            return;
        }
        @set_time_limit(60);
        foreach (array_splice($this->pinAntrean, 0, 3) as $i => $id) {
            if ($i > 0) {
                usleep(1_100_000); // Nominatim: maks. 1 permintaan per detik
            }
            try {
                (new GeocodeFamily($id))->handle();
                $f = Family::find($id);
                $status = $f?->geocode_status ?? 'hilang';
            } catch (\Throwable $e) {
                $f = Family::find($id);
                $status = 'error';
            }
            $this->pinHasil[$status] = ($this->pinHasil[$status] ?? 0) + 1;
            array_unshift($this->pinLog, sprintf('%s · %s · %s', $f?->code ?? $id, Opsi::label(Opsi::GEOCODE, $status) ?? $status, str((string) $f?->address)->limit(70)));
        }
        $this->pinLog = array_slice($this->pinLog, 0, 12);

        if (! $this->pinAntrean) {
            $this->pinJalan = false;
            Notification::make()->success()->title('Pin alamat selesai diproses')
                ->body(collect($this->pinHasil)->map(fn ($n, $s) => (Opsi::label(Opsi::GEOCODE, $s) ?? $s).": {$n}")->join(', '))->send();
        }
    }

    /** Jumlah KK per status pin, untuk tab Peta. @return array<string,int> */
    public function statPin(): array
    {
        return Family::query()->selectRaw('geocode_status, COUNT(*) n')->groupBy('geocode_status')->pluck('n', 'geocode_status')->all();
    }

    public function hentikanPin(): void
    {
        $this->pinJalan = false;
        Notification::make()->title('Proses pin dihentikan')->body(count($this->pinAntrean).' KK belum diproses; tekan tombol lagi untuk melanjutkan.')->send();
    }

    /** Tombol dirender per tab di view (bukan header), lihat pemeliharaan.blade.php. */
    public function migrateAction(): Action
    {
        return Action::make('migrate')->label('Update database')->icon(Heroicon::OutlinedCircleStack)
            ->requiresConfirmation()
            ->modalDescription('Menjalankan migrasi yang belum dijalankan (menambah tabel/kolom baru). Data yang ada tidak dihapus.')
            ->action(fn () => $this->run('Update database', 'migrate', ['--force' => true]));
    }

    public function importAction(): Action
    {
        return Action::make('import')->label('Impor data jemaat (Excel)')->icon(Heroicon::OutlinedArrowUpTray)->color('warning')
            ->modalDescription('Impor otomatis semua kolom dari sheet pertama file Excel jemaat. Jemaat dengan kode SIB yang sama diperbarui, bukan digandakan.')
            ->schema([
                FileUpload::make('file')->label('File Excel')->disk('local')->directory('imports')
                    ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel', 'text/csv'])
                    ->maxSize(20480)->required(),
                Toggle::make('fresh')->label('Hapus dulu semua data jemaat, KK, kehadiran, tamu & visitasi yang ada')
                    ->helperText('Akun user, komisi, dan wilayah tidak dihapus. Tidak bisa dibatalkan.'),
            ])
            ->action(function (array $data) {
                $param = ['file' => Storage::disk('local')->path($data['file'])];
                if ($data['fresh'] ?? false) {
                    $param['--fresh'] = true;
                }
                $this->run('Impor data jemaat', 'sij:import-excel', $param);
                Storage::disk('local')->delete($data['file']);
            });
    }

    public function geocodeAction(): Action
    {
        return Action::make('geocode')->label('Proses pin alamat (OSM)')->icon(Heroicon::OutlinedMapPin)->color('primary')
            ->disabled(fn () => $this->pinJalan)
            ->modalHeading('Cari pin alamat lewat OpenStreetMap')
            ->modalDescription('Diproses otomatis sampai selesai, sekitar 1 detik per KK. Biarkan halaman ini tetap terbuka; bila ditutup, proses berhenti dan bisa dilanjutkan nanti. Pin yang digeser manual tidak diubah.')
            ->modalSubmitActionLabel('Mulai')
            ->schema([
                Radio::make('cakupan')->label('KK yang diproses')->required()->default('belum')
                    ->options(fn () => [
                        'belum' => 'Belum punya pin ('.$this->queryPin('belum')->count().' KK)',
                        'ulang' => 'Belum punya pin + gagal + perkiraan ('.$this->queryPin('ulang')->count().' KK)',
                    ]),
            ])
            ->action(fn (array $data) => $this->mulaiPin($data['cakupan']));
    }

    public function paketEksporAction(): Action
    {
        return Action::make('paketEkspor')->label('Ekspor paket data')->icon(Heroicon::OutlinedArrowDownTray)->color('gray')
            ->modalDescription('Unduh data peta dan/atau jadwal pelayanan untuk dikirim ke server lain (mis. dari lokal ke hosting). Data jemaat, KK, dan user tidak ikut.')
            ->schema([
                CheckboxList::make('bagian')->label('Data yang dikirim')->required()->default(['peta', 'pelayanan'])
                    ->options(['peta' => 'Peta: pin alamat KK & batas wilayah', 'pelayanan' => 'Pelayanan: jadwal ibadah, petugas & hubungan nama pelayan']),
            ])
            ->action(function (array $data) {
                $json = json_encode(app(PaketData::class)->ekspor($data['bagian']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                return response()->streamDownload(fn () => print ($json), 'paket-data-'.now()->format('Ymd-His').'.json', ['Content-Type' => 'application/json']);
            });
    }

    public function paketImporAction(): Action
    {
        return Action::make('paketImpor')->label('Impor paket data')->icon(Heroicon::OutlinedArrowUpTray)->color('gray')
            ->modalDescription('Terima paket data dari server lain. Hanya pin KK (dicocokkan lewat kode KK), batas wilayah (nama wilayah), dan jadwal pelayanan (tanggal) yang diperbarui. Data jemaat, kepala keluarga, absensi, dll. di server ini tidak diubah. Pin yang sudah digeser manual di server ini tidak ditimpa.')
            ->requiresConfirmation()
            ->schema([
                FileUpload::make('file')->label('File paket (.json)')->disk('local')->directory('imports')->required()
                    ->acceptedFileTypes(['application/json', 'text/plain'])->maxSize(51200),
            ])
            ->action(function (array $data) {
                $path = Storage::disk('local')->path($data['file']);
                try {
                    $hasil = app(PaketData::class)->impor(json_decode(file_get_contents($path), true) ?? []);
                    $this->output = "Impor paket data selesai:\n".collect($hasil)->map(fn ($v, $k) => "- {$k}: {$v}")->join("\n");
                    $gagal = collect($hasil)->keys()->filter(fn ($k) => str_ends_with($k, 'GAGAL'));
                    $gagal->isEmpty()
                        ? Notification::make()->success()->title('Paket data diimpor')->send()
                        : Notification::make()->warning()->title('Sebagian gagal: '.$gagal->map(fn ($k) => str_replace(' GAGAL', '', $k))->join(', '))
                            ->body('Lihat rincian di kotak hasil di bawah.')->send();
                } catch (\Throwable $e) {
                    $this->output = 'Impor paket data gagal: '.$e->getMessage();
                    Notification::make()->danger()->title('Impor paket data gagal')->body(str($e->getMessage())->limit(200))->send();
                } finally {
                    Storage::disk('local')->delete($data['file']);
                }
            });
    }

    public function bersihkanCacheAction(): Action
    {
        return Action::make('bersihkanCache')->label('Bersihkan cache')->icon(Heroicon::OutlinedArrowPath)->color('gray')
            ->action(fn () => $this->run('Bersihkan cache', 'optimize:clear'));
    }

    public function storageAction(): Action
    {
        return Action::make('storage')->label('Buat storage link')->icon(Heroicon::OutlinedLink)->color('gray')
            ->action(fn () => $this->run('Storage link', 'storage:link', ['--force' => true]));
    }
}
