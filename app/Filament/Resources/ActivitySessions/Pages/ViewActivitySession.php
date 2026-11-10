<?php

namespace App\Filament\Resources\ActivitySessions\Pages;

use App\Filament\Resources\ActivitySessions\ActivitySessionResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewActivitySession extends ViewRecord
{
    protected static string $resource = ActivitySessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
