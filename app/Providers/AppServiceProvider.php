<?php

namespace App\Providers;

use App\Support\HakAkses;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Facades\FilamentIcon;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsIconAlias;
use Filament\Infolists\Components\TextEntry;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
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

        // Tombol ciutkan/lebarkan sidebar memakai ikon hamburger (bukan panah).
        FilamentIcon::register(array_fill_keys([
            PanelsIconAlias::SIDEBAR_COLLAPSE_BUTTON, PanelsIconAlias::SIDEBAR_COLLAPSE_BUTTON_RTL,
            PanelsIconAlias::SIDEBAR_EXPAND_BUTTON, PanelsIconAlias::SIDEBAR_EXPAND_BUTTON_RTL,
        ], Heroicon::OutlinedBars3));

        // HTTPS wajib di produksi.
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
        Model::preventLazyLoading(false);

        // Nominatim: maksimal 1 permintaan per detik.
        RateLimiter::for('geocoding', fn () => Limit::perSecond(1));

        // Super Admin boleh segalanya kecuali hak khusus pendeta (catatan rahasia) yang dicek manual.
        Gate::before(fn ($user, $ability) => $user->isSuperAdmin() && $ability !== 'readConfidential' ? true : null);
        // Matriks Hak Akses (Pengaturan → Hak Akses); sel yang belum diatur diserahkan ke policy.
        Gate::before(fn ($user, $ability, $args) => HakAkses::gate($user, $ability, $args));

        // Format tanggal dd/mm/yyyy di seluruh panel.
        DatePicker::configureUsing(fn (DatePicker $c) => $c->native(false)->displayFormat('d/m/Y'));
        TextColumn::configureUsing(fn (TextColumn $c) => $c->placeholder('-'));
        TextEntry::configureUsing(fn (TextEntry $c) => $c->placeholder('-'));
    }
}
