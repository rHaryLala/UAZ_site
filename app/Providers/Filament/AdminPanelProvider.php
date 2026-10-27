<?php

namespace App\Providers\Filament;

use App\Filament\Widgets\ContentOverview;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
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
            ->passwordReset()
            ->brandName('UAZ · Administration')
            ->colors([
                // Bleu marine du sceau (docs/DESIGN-SYSTEM.md).
                'primary' => [
                    50 => '#E1EBFF', 100 => '#C6D2F7', 200 => '#ACB9E1', 300 => '#93A0CC', 400 => '#7A88B7',
                    500 => '#4C5A8E', 600 => '#37447A', 700 => '#232E66', 800 => '#1B2455', 900 => '#131A3F', 950 => '#05081C',
                ],
                'gray' => Color::Slate,
            ])
            // 2FA par application (TOTP), optionnel ; chaque utilisateur l'active depuis son profil.
            ->profile()
            ->multiFactorAuthentication([
                AppAuthentication::make()->recoverable(),
            ])
            ->navigationGroups([
                NavigationGroup::make('Contenus'),
                NavigationGroup::make('Formations'),
                NavigationGroup::make('Communauté'),
                NavigationGroup::make('Médias'),
                NavigationGroup::make('Site'),
                NavigationGroup::make('Administration'),
            ])
            // Zone de glisser-déposer au-dessus de la médiathèque.
            ->renderHook(
                \Filament\View\PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE,
                fn (): \Illuminate\Contracts\View\View => view('filament.media.dropzone'),
                scopes: \App\Filament\Resources\MediaAssets\Pages\ListMediaAssets::class,
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                ContentOverview::class,
            ])
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
