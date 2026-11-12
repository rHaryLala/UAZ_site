<?php

namespace App\Providers\Filament;

use App\Filament\Resources\MediaAssets\MediaAssetResource;
use App\Filament\Resources\MediaAssets\Pages\ListMediaAssets;
use App\Filament\Widgets\ContentOverview;
use App\Filament\Widgets\LatestDrafts;
use App\Filament\Widgets\LatestMessages;
use App\Filament\Widgets\QuickActions;
use App\Models\MediaAsset;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Auth\Pages\Login;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset;
use Filament\Auth\Pages\PasswordReset\ResetPassword;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Livewire\Livewire;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        // Pages de connexion et de mot de passe oublié : celles qui portent la scène de l'aube.
        $authPages = [Login::class, RequestPasswordReset::class, ResetPassword::class];

        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            // Mode SPA : la navigation remplace le contenu sans recharger la page (scripts et styles conservés), avec préchargement au survol.
            ->spa(hasPrefetching: true)
            // L'éditeur visuel est une page plein écran à part : il se charge normalement (pas par la navigation SPA du panneau).
            ->spaUrlExceptions(fn (): array => [url('/admin/visual-editor/*'), url('/admin/newsletter-editor/*'), url('/admin/retouche-photo*'), url('/admin/studio*')])
            ->login()
            ->passwordReset()
            ->brandName('UAZ · Administration')
            // Logo de l'UAZ dans l'onglet du navigateur (mêmes fichiers que le site public).
            ->favicon(asset('favicon-32.png'))
            // Habillage « shadcn/ui » : thème Vite (resources/css/filament/admin/theme.css) et palette neutre.
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->colors([
                'primary' => [
                    50 => '#fafafa', 100 => '#f5f5f5', 200 => '#e5e5e5', 300 => '#d4d4d4', 400 => '#a3a3a3',
                    500 => '#525252', 600 => '#171717', 700 => '#0a0a0a', 800 => '#0a0a0a', 900 => '#0a0a0a', 950 => '#000000',
                ],
                'gray' => Color::Neutral,
            ])
            // 2FA par application (TOTP), optionnel ; chaque utilisateur l'active depuis son profil.
            ->profile()
            ->multiFactorAuthentication([
                AppAuthentication::make()->recoverable(),
            ])
            // Outils plein écran (retouche des photos, Studio UAZ) : des pages à part, ouvertes depuis le menu.
            ->navigationItems([
                NavigationItem::make('Retouche photo')
                    ->group('Site & médias')
                    ->icon(Heroicon::OutlinedSparkles)
                    ->sort(5)
                    ->url(fn (): string => route('admin.retouche.index'))
                    ->visible(fn (): bool => (bool) auth()->user()?->can('create', MediaAsset::class)),
            ])
            ->navigationGroups([
                NavigationGroup::make('Contenus'),
                NavigationGroup::make('Formations'),
                NavigationGroup::make('Personnes & messages'),
                NavigationGroup::make('Site & médias'),
            ])
            // Pages de connexion : l'aube (scène, feuille de style et script ne sont chargés que sur ces pages).
            ->renderHook(PanelsRenderHook::SIMPLE_LAYOUT_START, fn (): View => view('filament.auth.scene'), scopes: $authPages)
            ->renderHook(PanelsRenderHook::SIMPLE_LAYOUT_END, fn (): View => view('filament.auth.back'), scopes: $authPages)
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): View => view('filament.auth.assets'), scopes: $authPages)
            // Sélecteur de médias façon WordPress (composant Alpine chargé une fois pour tout le panneau).
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => '<script src="'.asset('js/admin/media-picker.js').'?v='.filemtime(public_path('js/admin/media-picker.js')).'" defer></script>',
            )
            // Zone de glisser-déposer au-dessus de la médiathèque.
            ->renderHook(
                PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE,
                fn (): View => view('filament.media.dropzone'),
                scopes: ListMediaAssets::class,
            )
            // « Charger plus » de la médiathèque en grille (la vue liste garde sa pagination).
            ->renderHook(
                PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER,
                fn (): string => MediaAssetResource::isGrid() ? view('filament.media.load-more', ['counts' => Livewire::current()->mediaCounts()])->render() : '',
                scopes: ListMediaAssets::class,
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                \App\Filament\Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                QuickActions::class,
                ContentOverview::class,
                LatestDrafts::class,
                LatestMessages::class,
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
