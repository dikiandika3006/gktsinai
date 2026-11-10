<?php

namespace App\Filament\Resources\Guests\Pages;

use App\Filament\Resources\Guests\GuestResource;
use App\Filament\Widgets\GuestConversionStats;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListGuests extends ListRecords
{
    protected static string $resource = GuestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('form')->label('Form tamu (HP)')->icon(Heroicon::OutlinedDevicePhoneMobile)->color('gray')
                ->url(route('tamu.create'), true),
            CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return auth()->user()->lihatSemuaJemaat() ? [GuestConversionStats::class] : [];
    }
}
