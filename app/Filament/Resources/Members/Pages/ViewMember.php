<?php

namespace App\Filament\Resources\Members\Pages;

use App\Filament\Actions\KeluargaActions;
use App\Filament\Resources\Families\FamilyResource;
use App\Filament\Resources\Members\MemberResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewMember extends ViewRecord
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('buka_kk')->label('Buka KK')->icon(Heroicon::OutlinedHome)->color('gray')
                ->visible(fn () => (bool) $this->record->family_id)
                ->url(fn () => FamilyResource::getUrl('view', ['record' => $this->record->family_id])),
            EditAction::make(),
            ActionGroup::make([
                KeluargaActions::pindah()->after(fn () => $this->muatUlang()),
                KeluargaActions::jadikanKepala()->after(fn () => $this->muatUlang()),
            ])->label('Keluarga')->button()->color('gray'),
        ];
    }

    private function muatUlang(): void
    {
        $this->redirect(static::getUrl(['record' => $this->record]));
    }
}
