<?php

namespace App\Filament\Resources\OrgUnits\Pages;

use App\Filament\Resources\OrgUnits\OrgUnitResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOrgUnit extends CreateRecord
{
    protected static string $resource = OrgUnitResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
