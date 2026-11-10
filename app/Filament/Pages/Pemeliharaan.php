<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use App\Services\PaketData;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
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

    protected static ?string $title = 'Pemeliharaan Sistem';

    public ?string $output = null;

    public array $pending = [];

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

    protected function getHeaderActions(): array
    {
        return [
            Action::make('migrate')->label('Update database')->icon(Heroicon::OutlinedCircleStack)
                ->requiresConfirmation()
                ->modalDescription('Menjalankan migrasi yang belum dijalankan (menambah tabel/kolom baru). Data yang ada tidak dihapus.')
                ->action(fn () => $this->run('Update database', 'migrate', ['--force' => true])),

            Action::make('import')->label('Impor data jemaat (Excel)')->icon(Heroicon::OutlinedArrowUpTray)->color('warning')
                ->modalDescription('Impor otomatis semua kolom dari file Excel jemaat. Untuk pemetaan kolom manual & pratinjau, pakai menu Import Data Jemaat.')
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
                }),

            Action::make('geocode')->label('Proses pin alamat')->icon(Heroicon::OutlinedMapPin)->color('gray')
                ->modalDescription('Mencari titik peta (OpenStreetMap) untuk KK yang belum punya pin, 25 KK per klik (sekitar 1 menit). Klik lagi sampai sisa 0. Pin manual tidak diubah.')
                ->requiresConfirmation()
                ->action(fn () => $this->run('Proses pin alamat', 'sij:geocode', ['--sync' => true, '--limit' => 25])),

            Action::make('paketEkspor')->label('Ekspor paket data')->icon(Heroicon::OutlinedArrowDownTray)->color('gray')
                ->modalDescription('Unduh data peta dan/atau jadwal pelayanan untuk dikirim ke server lain (mis. dari lokal ke hosting). Data jemaat, KK, dan user tidak ikut.')
                ->schema([
                    CheckboxList::make('bagian')->label('Data yang dikirim')->required()->default(['peta', 'pelayanan'])
                        ->options(['peta' => 'Peta: pin alamat KK & batas wilayah', 'pelayanan' => 'Pelayanan: jadwal ibadah, petugas & hubungan nama pelayan']),
                ])
                ->action(function (array $data) {
                    $json = json_encode(app(PaketData::class)->ekspor($data['bagian']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                    return response()->streamDownload(fn () => print ($json), 'paket-data-'.now()->format('Ymd-His').'.json', ['Content-Type' => 'application/json']);
                }),

            Action::make('paketImpor')->label('Impor paket data')->icon(Heroicon::OutlinedArrowUpTray)->color('gray')
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
                        Notification::make()->success()->title('Paket data diimpor')->send();
                    } catch (\Throwable $e) {
                        $this->output = 'Impor paket data gagal: '.$e->getMessage();
                        Notification::make()->danger()->title('Impor paket data gagal')->body(str($e->getMessage())->limit(200))->send();
                    } finally {
                        Storage::disk('local')->delete($data['file']);
                    }
                }),

            Action::make('cache')->label('Bersihkan cache')->icon(Heroicon::OutlinedArrowPath)->color('gray')
                ->action(fn () => $this->run('Bersihkan cache', 'optimize:clear')),

            Action::make('storage')->label('Buat storage link')->icon(Heroicon::OutlinedLink)->color('gray')
                ->action(fn () => $this->run('Storage link', 'storage:link', ['--force' => true])),
        ];
    }
}
