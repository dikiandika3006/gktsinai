<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use App\Http\Middleware\HanyaAbsensi;
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
            ->login(Login::class)
            ->profile()
            ->brandName('Sistem Informasi Jemaat')
            ->brandLogo(fn () => view('filament.brand'))
            ->brandLogoHeight('2.75rem')
            ->favicon(asset('logo-gkt.png'))
            ->colors([
                'primary' => Color::Indigo,
            ])
            ->renderHook(PanelsRenderHook::HEAD_END, fn () => view('filament.leaflet-assets'))
            ->renderHook(PanelsRenderHook::SIDEBAR_NAV_START, fn () => view('filament.menu-search'))
            ->databaseNotifications()
            ->sidebarCollapsibleOnDesktop()
            // Konten memakai seluruh lebar layar (bawaan Filament dibatasi 7xl / ±1280px).
            ->maxContentWidth(\Filament\Support\Enums\Width::Full)
            ->navigationGroups([
                NavigationGroup::make('Jemaat'),
                NavigationGroup::make('Ibadah & Kehadiran'),
                NavigationGroup::make('Pelayanan'),
                NavigationGroup::make('Pastoral'),
                NavigationGroup::make('Laporan'),
                NavigationGroup::make('Pengaturan')->collapsed(),
            ])
            ->navigationItems([
                NavigationItem::make('Absensi (HP petugas)')
                    ->url(fn () => route('absen.index'), shouldOpenInNewTab: true)
                    ->icon(Heroicon::OutlinedDevicePhoneMobile)
                    ->group('Ibadah & Kehadiran')
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
                HanyaAbsensi::class,
            ]);
    }
}
