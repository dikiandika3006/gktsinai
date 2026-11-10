<?php

namespace App\Providers;

use Carbon\Carbon;
use Filament\Infolists\Components\TextEntry;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\DatePicker;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Carbon::setLocale('id');

        // HTTPS wajib di produksi.
        if ($this->app->isProduction()) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }
        Model::preventLazyLoading(false);

        // Nominatim: maksimal 1 permintaan per detik.
        RateLimiter::for('geocoding', fn () => Limit::perSecond(1));

        // Super Admin boleh segalanya kecuali hak khusus pendeta (catatan rahasia) yang dicek manual.
        Gate::before(fn ($user, $ability) => $user->isSuperAdmin() && $ability !== 'readConfidential' ? true : null);

        // Format tanggal dd/mm/yyyy di seluruh panel.
        DatePicker::configureUsing(fn (DatePicker $c) => $c->native(false)->displayFormat('d/m/Y'));
        TextColumn::configureUsing(fn (TextColumn $c) => $c->placeholder('-'));
        TextEntry::configureUsing(fn (TextEntry $c) => $c->placeholder('-'));
    }
}
