<?php

namespace App\Filament\Resources\WorshipSchedules\Pages;

use App\Filament\Resources\WorshipSchedules\WorshipScheduleResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewWorshipSchedule extends ViewRecord
{
    protected static string $resource = WorshipScheduleResource::class;

    public function getTitle(): string
    {
        return 'Jadwal Ibadah '.$this->getRecord()->date->translatedFormat('d F Y');
    }

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }
}
