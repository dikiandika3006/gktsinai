<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
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

            Action::make('cache')->label('Bersihkan cache')->icon(Heroicon::OutlinedArrowPath)->color('gray')
                ->action(fn () => $this->run('Bersihkan cache', 'optimize:clear')),

            Action::make('storage')->label('Buat storage link')->icon(Heroicon::OutlinedLink)->color('gray')
                ->action(fn () => $this->run('Storage link', 'storage:link', ['--force' => true])),
        ];
    }
}
