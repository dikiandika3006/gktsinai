<?php

namespace App\Notifications;

use App\Filament\Resources\Guests\GuestResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/** TF-07: pengingat untuk PIC yang FU-nya lewat batas waktu. */
class OverdueFollowupNotification extends Notification
{
    public function __construct(public Collection $guests) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return FilamentNotification::make()
            ->danger()
            ->title($this->guests->count().' follow-up tamu lewat batas waktu')
            ->body($this->guests->pluck('name')->take(5)->join(', ').($this->guests->count() > 5 ? ', ...' : ''))
            ->actions([Action::make('lihat')->label('Buka daftar')->url(GuestResource::getUrl('index', ['tableFilters' => ['saya' => ['isActive' => true]]]))])
            ->getDatabaseMessage();
    }
}
