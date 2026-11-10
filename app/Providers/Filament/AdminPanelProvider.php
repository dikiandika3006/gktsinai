<?php

namespace App\Providers\Filament;

use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->profile()
            ->brandName('Sistem Informasi Jemaat')
            ->brandLogo(fn () => view('filament.brand'))
            ->brandLogoHeight('2.75rem')
            ->favicon(asset('logo-gkt.png'))
            ->colors([
                'primary' => Color::Indigo,
            ])
            ->multiFactorAuthentication(
                [AppAuthentication::make()->recoverable()->brandName('Sistem Informasi Jemaat GKT Sinai Batu')],
                isRequired: fn () => (bool) auth()->user()?->hasAnyRole(['super_admin', 'pendeta']),
            )
            ->renderHook(PanelsRenderHook::HEAD_END, fn () => view('filament.leaflet-assets'))
            ->databaseNotifications()
            ->sidebarCollapsibleOnDesktop()
            ->navigationGroups([
                NavigationGroup::make('Data Jemaat'),
                NavigationGroup::make('Kehadiran'),
                NavigationGroup::make('Tamu & Follow-Up'),
                NavigationGroup::make('Visitasi'),
                NavigationGroup::make('Laporan'),
                NavigationGroup::make('Pengaturan')->collapsed(),
            ])
            ->navigationItems([
                NavigationItem::make('Absensi (HP petugas)')
                    ->url(fn () => route('absen.index'), shouldOpenInNewTab: true)
                    ->icon(Heroicon::OutlinedDevicePhoneMobile)
                    ->group('Kehadiran')
                    ->sort(0)
                    ->visible(fn () => (bool) auth()->user()?->kelolaKehadiran()),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
