<?php

namespace App\Filament\Resources\GrowthTargets\Pages;

use App\Filament\Resources\GrowthTargets\GrowthTargetResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageGrowthTargets extends ManageRecords
{
    protected static string $resource = GrowthTargetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
