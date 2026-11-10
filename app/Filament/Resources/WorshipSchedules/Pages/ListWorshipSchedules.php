<?php

namespace App\Filament\Resources\WorshipSchedules\Pages;

use App\Filament\Pages\JadwalBulanan;
use App\Filament\Resources\WorshipSchedules\WorshipScheduleResource;
use App\Services\JadwalImporter;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;

class ListWorshipSchedules extends ListRecords
{
    protected static string $resource = WorshipScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('bulanan')->label('Lihat per bulan')->icon(Heroicon::OutlinedTableCells)->color('gray')
                ->url(fn () => JadwalBulanan::getUrl()),
            self::importAction(),
            CreateAction::make(),
        ];
    }

    /** Import Excel jadwal (dipakai juga di halaman Jadwal Bulanan). */
    public static function importAction(): Action
    {
        return Action::make('import')->label('Import Excel')->icon(Heroicon::OutlinedArrowUpTray)->color('gray')
            ->visible(fn () => auth()->user()->kelolaJadwal())
            ->modalDescription('Format seperti jadwal bulanan gereja: baris "Tanggal" berisi tanggal (2 kolom per tanggal: Ibadah Umum I & II), lalu Pembicara, Tema, Ayat Referensi, Introitus, PHB, Liturgis, Singer, Pewarta, LCD, Penyambut & Kolektan, Keterangan. Semua sheet (satu sheet per bulan) dibaca; ibadah gabungan/komisi dikenali dari baris di bawah tanggal. Jadwal pada tanggal yang sama akan ditimpa, nama yang sudah dihubungkan manual tetap dipakai.')
            ->schema([
                FileUpload::make('file')->label('File Excel')->required()->disk('local')->directory('imports')
                    ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel']),
            ])
            ->action(function (array $data) {
                $importer = app(JadwalImporter::class);
                $path = Storage::disk('local')->path($data['file']);
                try {
                    $jadwal = $importer->baca($path);
                } finally {
                    Storage::disk('local')->delete($data['file']);
                }
                if (! $jadwal) {
                    Notification::make()->danger()->title('Tidak ada jadwal terbaca')
                        ->body('Pastikan ada baris berlabel "Tanggal" berisi tanggal, mis. "01 November 2026".')->send();

                    return;
                }
                $hasil = $importer->simpan($jadwal);
                Notification::make()->success()->title("{$hasil['tanggal']} tanggal, {$hasil['petugas']} petugas diimport")
                    ->body($hasil['belum_terdaftar'] ? "{$hasil['belum_terdaftar']} nama belum terdaftar di data jemaat. Cek menu Nama Pelayan." : 'Semua nama cocok dengan data jemaat.')
                    ->send();
            });
    }
}
