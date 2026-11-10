<?php

namespace App\Filament\Pages;

use App\Support\HakAkses;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/** Pengaturan menu apa saja yang bisa dibuka tiap peran. Khusus super admin. */
class HakAksesPeran extends Page
{
    protected string $view = 'filament.pages.hak-akses';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|\UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'hak-akses';

    protected static ?string $title = 'Hak Akses Peran';

    protected static ?string $navigationLabel = 'Hak Akses';

    /** @var array<string, array<string, int>> [peran][menu] => level */
    public array $matriks = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isSuperAdmin();
    }

    public function mount(): void
    {
        $this->muat();
    }

    private function muat(): void
    {
        $this->matriks = collect(HakAkses::peranDiatur())->map(fn ($_, $peran) => collect(HakAkses::menu())
            ->map(fn ($_, $menu) => HakAkses::levelPeran($peran, $menu))->all())->all();
    }

    public function simpan(): void
    {
        HakAkses::simpan($this->matriks);
        $this->muat();
        Notification::make()->success()->title('Hak akses disimpan')->body('Berlaku saat pengguna membuka halaman berikutnya.')->send();
    }

    public function kembalikan(string $peran): void
    {
        abort_unless(array_key_exists($peran, HakAkses::peranDiatur()), 404);
        HakAkses::kembalikanBawaan($peran);
        $this->muat();
        Notification::make()->success()->title('Hak akses '.HakAkses::peranDiatur()[$peran].' kembali ke bawaan')->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('simpan')->label('Simpan')->icon(Heroicon::OutlinedCheck)->action('simpan'),
        ];
    }

    /** Untuk tampilan: sel yang berbeda dari aturan bawaan aplikasi ditandai. */
    public function diubah(string $peran, string $menu): bool
    {
        return isset(HakAkses::tersimpan()[$peran][$menu]);
    }
}
