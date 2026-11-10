<?php

namespace App\Filament\Resources\WorshipSchedules\Pages;

use App\Filament\Resources\WorshipSchedules\WorshipScheduleResource;
use App\Models\WorshipSchedule;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditWorshipSchedule extends EditRecord
{
    protected static string $resource = WorshipScheduleResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['petugas'] = $this->getRecord()->petugasUntukForm();

        return $data;
    }

    /** @param  WorshipSchedule  $record */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $petugas = $data['petugas'] ?? [];
        unset($data['petugas']);
        $record->update($data);
        $record->simpanPetugas(array_values($petugas));

        return $record;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function getHeaderActions(): array
    {
        return [ViewAction::make(), DeleteAction::make()];
    }
}
