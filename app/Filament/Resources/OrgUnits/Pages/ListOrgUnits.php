<?php

namespace App\Filament\Resources\OrgUnits\Pages;

use App\Filament\Pages\StrukturOrganisasi;
use App\Filament\Resources\OrgUnits\OrgUnitResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListOrgUnits extends ListRecords
{
    protected static string $resource = OrgUnitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('bagan')->label('Lihat bagan')->icon(Heroicon::OutlinedRectangleGroup)->color('gray')->url(fn () => StrukturOrganisasi::getUrl()),
            CreateAction::make(),
        ];
    }
}
