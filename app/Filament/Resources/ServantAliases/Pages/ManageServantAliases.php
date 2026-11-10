<?php

namespace App\Filament\Resources\ServantAliases\Pages;

use App\Filament\Resources\ServantAliases\ServantAliasResource;
use App\Services\PelayanMatcher;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;

class ManageServantAliases extends ManageRecords
{
    protected static string $resource = ServantAliasResource::class;

    public function getSubheading(): ?string
    {
        return 'Nama yang tertulis di jadwal pelayanan. Hubungkan ke data jemaat, daftarkan sebagai jemaat baru, atau tandai sebagai simpatisan / pekerja / pelayan tamu.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('cocokkan')->label('Cocokkan ulang otomatis')->icon(Heroicon::OutlinedArrowPath)->color('gray')
                ->visible(fn () => auth()->user()->kelolaJadwal())
                ->requiresConfirmation()
                ->modalDescription('Mencocokkan lagi semua nama yang tidak dihubungkan manual dengan data jemaat terbaru.')
                ->action(function () {
                    $n = app(PelayanMatcher::class)->cocokkanUlang();
                    Notification::make()->success()->title("{$n} nama cocok dengan data jemaat")->send();
                }),
        ];
    }
}
