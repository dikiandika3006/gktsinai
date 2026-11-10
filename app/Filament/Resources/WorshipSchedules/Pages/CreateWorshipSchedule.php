<?php

namespace App\Filament\Resources\WorshipSchedules\Pages;

use App\Filament\Resources\WorshipSchedules\WorshipScheduleResource;
use App\Models\WorshipSchedule;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateWorshipSchedule extends CreateRecord
{
    protected static string $resource = WorshipScheduleResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $petugas = $data['petugas'] ?? [];
        unset($data['petugas']);
        $record = WorshipSchedule::create($data);
        $record->simpanPetugas(array_values($petugas));

        return $record;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
