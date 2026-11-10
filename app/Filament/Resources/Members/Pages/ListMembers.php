<?php

namespace App\Filament\Resources\Members\Pages;

use App\Filament\Pages\DuplikatJemaat;
use App\Filament\Pages\ImportJemaat;
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
                Action::make('import')->label('Import Excel')->icon(Heroicon::OutlinedArrowUpTray)->url(ImportJemaat::getUrl()),
                Action::make('duplikat')->label('Cek duplikat')->icon(Heroicon::OutlinedDocumentDuplicate)->url(DuplikatJemaat::getUrl()),
            ])->visible(fn () => auth()->user()->peran('super_admin', 'sekretariat'))->label('Alat')->button()->color('gray'),
            CreateAction::make(),
        ];
    }
}
