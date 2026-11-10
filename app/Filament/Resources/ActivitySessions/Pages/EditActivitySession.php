<?php

namespace App\Filament\Resources\ActivitySessions\Pages;

use App\Filament\Resources\ActivitySessions\ActivitySessionResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditActivitySession extends EditRecord
{
    protected static string $resource = ActivitySessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
