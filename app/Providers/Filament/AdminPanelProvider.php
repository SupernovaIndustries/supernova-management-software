<?php

namespace App\Providers\Filament;

use App\Http\Middleware\InjectPwaAssets;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use App\Http\Middleware\VerifyCsrfToken;
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
            ->brandName('Supernova Management')
            ->brandLogo(asset('images/logo-supernova-colored-full.svg'))
            ->brandLogoHeight('3rem')
            ->favicon('/images/logo-supernova-colored-full.svg')
            ->renderHook('panels::body.end', fn() => '<style>' . file_get_contents(resource_path('css/supernova-theme.css')) . '</style>')
            ->colors([
                'primary' => [
                    50 => '#E0FFFF',   // Light Cyan
                    100 => '#B3FFFE',  
                    200 => '#80FFFD',
                    300 => '#4DFFFC',
                    400 => '#1AFFFA',
                    500 => '#00BFBF',  // Primary Bright
                    600 => '#2A6A6A',  // Primary Mid2
                    700 => '#1A4A4A',  // Primary Mid1
                    800 => '#0A1A1A',  // Primary Dark
                    900 => '#050F0F',
                ],
                'success' => [
                    500 => '#48D1CC',  // Accent Medium
                ],
                'info' => [
                    500 => '#40E0D0',  // Secondary Turquoise
                ],
                'warning' => [
                    500 => '#00FFFF',  // Secondary Aqua
                ],
                'danger' => [
                    500 => '#FF6B6B',  // Keep red for danger
                ],
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                // Widgets\FilamentInfoWidget::class, // Rimosso - banner non necessario
            ])
            ->sidebarCollapsibleOnDesktop()
            ->sidebarFullyCollapsibleOnDesktop()
            ->spa()
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                // AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                // InjectPwaAssets::class,
            ])
            ->authGuard('web')
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
