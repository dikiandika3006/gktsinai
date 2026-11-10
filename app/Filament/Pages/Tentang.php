<?php

namespace App\Filament\Pages;

use App\Models\Family;
use App\Models\Member;
use App\Services\DatabaseBackupService;
use App\Support\Tentang as Konten;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Halaman Tentang + form rahasia Backup & Restore database (Alt+F7, khusus super admin, wajib PIN).
 * Pola sama dengan project djitu.
 */
class Tentang extends Page
{
    use WithFileUploads;

    protected string $view = 'filament.pages.tentang';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInformationCircle;

    protected static string|\UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 100;

    protected static ?string $title = 'Tentang Aplikasi';

    protected static ?string $navigationLabel = 'Tentang';

    public bool $panelOpen = false;

    public string $pin = '';

    public $restoreFile;

    public ?string $restoreName = null;

    public string $konfirmasi = '';

    public ?string $message = null;

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    protected function getViewData(): array
    {
        $data = [
            'aturan' => Konten::ATURAN,
            'riwayat' => Konten::RIWAYAT,
            'stat' => ['jemaat' => Member::count(), 'kk' => Family::count()],
            'bolehBackup' => $this->bolehBackup(),
            'unlocked' => $this->unlocked(),
        ];
        if ($data['unlocked']) {
            $svc = app(DatabaseBackupService::class);
            $data['backups'] = $svc->list();
            $data['databaseSize'] = $svc->databaseSize();
        }

        return $data;
    }

    protected function bolehBackup(): bool
    {
        return (bool) auth()->user()?->peran('super_admin');
    }

    protected function unlocked(): bool
    {
        return $this->bolehBackup() && (string) config('backup.pin') !== ''
            && session('backup_unlocked_until', 0) >= now()->timestamp;
    }

    protected function ensureUnlocked(): void
    {
        abort_unless($this->unlocked(), 403, 'Sesi form backup sudah berakhir. Masukkan PIN lagi.');
    }

    public function openPanel(): void
    {
        abort_unless($this->bolehBackup(), 403);
        $this->panelOpen = true;
        $this->message = null;
    }

    public function unlock(): void
    {
        abort_unless($this->bolehBackup(), 403);
        $pin = (string) config('backup.pin');
        if ($pin === '') {
            $this->message = 'Fitur backup belum diaktifkan (BACKUP_PIN kosong di .env server).';

            return;
        }
        if (! hash_equals($pin, $this->pin)) {
            $this->audit('PIN salah');
            $this->message = 'PIN salah.';

            return;
        }
        session(['backup_unlocked_until' => now()->addMinutes((int) config('backup.unlock_minutes', 15))->timestamp]);
        $this->pin = '';
        $this->message = null;
    }

    public function createBackup(DatabaseBackupService $svc): void
    {
        $this->ensureUnlocked();
        try {
            $name = $svc->exclusive(fn () => $svc->backup());
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title('Backup gagal')->body($e->getMessage())->danger()->send();

            return;
        }
        $this->audit('backup', $name);
        Notification::make()->title('Backup dibuat')->body($name)->success()->send();
    }

    public function download(string $name, DatabaseBackupService $svc): BinaryFileResponse
    {
        $this->ensureUnlocked();
        $path = $svc->path($name);
        $this->audit('download', $name);

        return response()->download($path);
    }

    public function deleteBackup(string $name, DatabaseBackupService $svc): void
    {
        $this->ensureUnlocked();
        $svc->delete($name);
        $this->audit('hapus', $name);
        Notification::make()->title('Backup dihapus')->body($name)->success()->send();
    }

    public function restore(DatabaseBackupService $svc): void
    {
        $this->ensureUnlocked();
        $this->message = null;

        if ($this->konfirmasi !== 'RESTORE') {
            $this->message = 'Ketik RESTORE (huruf besar) untuk konfirmasi.';

            return;
        }

        // Semua pemeriksaan file dilakukan sebelum data apa pun diubah.
        try {
            if ($this->restoreFile) {
                $asli = $this->restoreFile->getClientOriginalName();
                if (! preg_match('/\.sql(\.gz)?$/i', $asli)) {
                    throw new \RuntimeException('File harus berformat .sql atau .sql.gz.');
                }
                $path = $this->restoreFile->getRealPath();
                $source = 'upload: '.$asli;
            } elseif ($this->restoreName) {
                $path = $svc->path($this->restoreName);
                $source = $this->restoreName;
            } else {
                throw new \RuntimeException('Pilih file backup dari riwayat atau upload file .sql / .sql.gz.');
            }
            $svc->validate($path);
        } catch (\RuntimeException $e) {
            $this->message = $e->getMessage().' Database tidak diubah.';

            return;
        }

        try {
            $hasil = $svc->exclusive(function () use ($svc, $path) {
                $pre = $svc->backup('_pre-restore');
                $jumlah = $svc->restore($path);
                // Koneksi baru, lalu naikkan skema ke versi aplikasi saat ini.
                DB::purge();
                DB::reconnect();
                Artisan::call('migrate', ['--force' => true]);

                return compact('pre', 'jumlah');
            });
        } catch (\Throwable $e) {
            report($e);
            $this->audit('restore GAGAL', $source);
            $this->message = 'Restore gagal: '.$e->getMessage().' Bila data rusak, restore ulang dari file backup "_pre-restore" terbaru di riwayat.';

            return;
        }

        $this->audit('restore', $source, ['pre_restore' => $hasil['pre'], 'statements' => $hasil['jumlah']]);
        $this->reset('restoreFile', 'restoreName', 'konfirmasi');
        Notification::make()->title('Restore selesai')
            ->body("{$hasil['jumlah']} perintah dijalankan. Backup sebelum restore: {$hasil['pre']}. Semua user perlu login ulang.")
            ->success()->persistent()->send();
    }

    private function audit(string $action, ?string $file = null, array $extra = []): void
    {
        Log::warning("Backup database: {$action}", [
            'user_id' => auth()->id(),
            'user' => auth()->user()?->name,
            'file' => $file,
            'ip' => request()->ip(),
        ] + $extra);
    }
}
