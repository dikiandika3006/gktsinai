<?php

namespace App\Filament\Resources\Members\Pages;

use App\Filament\Pages\DuplikatJemaat;
use App\Filament\Pages\Pemeliharaan;
use App\Services\DuplicateFinder;
use App\Filament\Resources\Members\MemberResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListMembers extends ListRecords
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('import')->label('Import Excel (Pemeliharaan)')->icon(Heroicon::OutlinedArrowUpTray)->url(fn () => Pemeliharaan::getUrl())
                    ->visible(fn () => Pemeliharaan::canAccess()),
                Action::make('duplikat')->label(fn () => 'Kemungkinan duplikat ('.app(DuplicateFinder::class)->jumlah().')')->icon(Heroicon::OutlinedDocumentDuplicate)->color('danger')
                    ->visible(fn () => app(DuplicateFinder::class)->jumlah() > 0)->url(fn () => DuplikatJemaat::getUrl()),
            ])->visible(fn () => auth()->user()->peran('super_admin', 'sekretariat'))->label('Alat')->button()->color('gray'),
            CreateAction::make(),
        ];
    }
}
