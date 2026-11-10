<?php

namespace App\Filament\Resources\OrgUnits\Pages;

use App\Filament\Resources\OrgUnits\OrgUnitResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditOrgUnit extends EditRecord
{
    protected static string $resource = OrgUnitResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
