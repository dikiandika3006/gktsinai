<?php

namespace App\Notifications;

use App\Filament\Resources\Guests\GuestResource;
use App\Models\Guest;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;

class NewGuestNotification extends Notification
{
    public function __construct(public Guest $guest) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return FilamentNotification::make()
            ->title('Tamu baru: '.$this->guest->name)
            ->body('Mohon tetapkan PIC follow-up (batas '.$this->guest->fu_due_at?->format('d/m H:i').').')
            ->icon('heroicon-o-user-plus')
            ->actions([Action::make('lihat')->label('Buka')->url(GuestResource::getUrl('view', ['record' => $this->guest]))])
            ->getDatabaseMessage();
    }
}
