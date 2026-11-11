<?php

namespace App\Providers;

use App\Http\Middleware\SetLocale;
use App\Listeners\OptimizeUploadedImage;
use App\Models\ContactMessage;
use App\Models\Event as EventModel;
use App\Models\Faculty;
use App\Models\Formation;
use App\Models\MediaAsset;
use App\Models\Menu;
use App\Models\NewsCategory;
use App\Models\Newsletter;
use App\Models\NewsPost;
use App\Models\Page;
use App\Models\Partner;
use App\Models\PhotoAlbum;
use App\Models\Project;
use App\Models\Redirect;
use App\Models\TeamMember;
use App\Models\Testimonial;
use App\Models\User;
use App\Policies\ContactMessagePolicy;
use App\Policies\ContentPolicy;
use App\Policies\ManagedContentPolicy;
use App\Policies\MediaAssetPolicy;
use App\Policies\NewsletterPolicy;
use App\Policies\PagePolicy;
use App\Policies\SettingsPolicy;
use App\Policies\UserPolicy;
use App\Support\AdminStats;
use App\View\Composers\SiteComposer;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Tables\Table;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent;

class AppServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    private const POLICIES = [
        Page::class => PagePolicy::class,
        NewsPost::class => ContentPolicy::class,
        EventModel::class => ContentPolicy::class,
        Formation::class => ContentPolicy::class,
        PhotoAlbum::class => ContentPolicy::class,
        Faculty::class => ManagedContentPolicy::class,
        TeamMember::class => ManagedContentPolicy::class,
        Testimonial::class => ManagedContentPolicy::class,
        Partner::class => ManagedContentPolicy::class,
        Project::class => ManagedContentPolicy::class,
        NewsCategory::class => ManagedContentPolicy::class,
        Newsletter::class => NewsletterPolicy::class,
        MediaAsset::class => MediaAssetPolicy::class,
        Menu::class => SettingsPolicy::class,
        Redirect::class => SettingsPolicy::class,
        ContactMessage::class => ContactMessagePolicy::class,
        User::class => UserPolicy::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Limites de l'éditeur de lettres : une limite PROPRE à chaque action. Avec « throttle:10,1 », Laravel compte toutes les routes limitées d'un
        // même utilisateur ensemble : les mises à jour du plan de travail auraient épuisé l'envoi d'essai (« Too Many Attempts »).
        foreach (['newsletter-render' => 240, 'newsletter-save' => 120, 'newsletter-test' => 10, 'newsletter-send' => 5] as $name => $perMinute) {
            RateLimiter::for($name, fn (Request $request) => Limit::perMinute($perMinute)->by($name.'|'.($request->user()?->getAuthIdentifier() ?? $request->ip())));
        }

        // Les requêtes Livewire (formulaire de contact, recherche) partent d'une adresse unique : la langue de la page d'origine
        // (/en, /mg) est rétablie en rejouant le middleware de langue de sa route.
        Livewire::addPersistentMiddleware([SetLocale::class]);

        // Liaison de route publique : seuls les contenus visibles (publiés, ou tous en mode aperçu) répondent ;
        // un brouillon ou un contenu programmé donne une 404. Ici et non dans routes/web.php : avec `route:cache`
        // (production), ce fichier n'est plus exécuté et les adresses par « slug » ne se résoudraient plus (404).
        Route::bind('formation', fn (string $slug) => Formation::visible()->where('slug', $slug)->firstOrFail());
        Route::bind('post', fn (string $slug) => NewsPost::visible()->where('slug', $slug)->firstOrFail());
        Route::bind('event', fn (string $slug) => EventModel::visible()->where('slug', $slug)->firstOrFail());
        Route::bind('album', fn (string $slug) => PhotoAlbum::visible()->where('slug', $slug)->firstOrFail());

        // Chiffres du tableau de bord et compteur de messages : cache vidé dès qu'un contenu concerné change.
        foreach ([NewsPost::class, EventModel::class, ContactMessage::class] as $model) {
            $model::saved(fn () => AdminStats::flush());
            $model::deleted(fn () => AdminStats::flush());
        }

        // Tableaux du back-office : chargement différé (le squelette s'affiche tout de suite, les lignes arrivent ensuite),
        // pagination 10 / 25 / 50 (25 par défaut). Chaque écran peut surcharger ces réglages.
        Table::configureUsing(fn (Table $table) => $table
            ->deferLoading()
            ->paginationPageOptions([10, 25, 50])
            ->defaultPaginationPageOption(25)
            // Recherche, filtres et tri sont mémorisés pour la session : on retrouve sa liste telle qu'on l'a laissée.
            ->persistSearchInSession()
            ->persistFiltersInSession()
            ->persistSortInSession());

        // Le super-admin passe toutes les autorisations, sauf pour se supprimer lui-même ou supprimer le dernier super-admin
        // (garde-fou : on ne doit jamais pouvoir verrouiller le back-office par erreur).
        Gate::before(function ($user, string $ability, array $arguments) {
            if (! $user->hasRole(RolesAndPermissionsSeeder::SUPER_ADMIN)) {
                return null;
            }

            $target = $arguments[0] ?? null;

            if ($ability === 'delete' && $target instanceof User) {
                $lastSuperAdmin = $target->hasRole(RolesAndPermissionsSeeder::SUPER_ADMIN)
                    && User::role(RolesAndPermissionsSeeder::SUPER_ADMIN)->count() <= 1;

                if ($target->is($user) || $lastSuperAdmin) {
                    return false;
                }
            }

            return true;
        });

        foreach (self::POLICIES as $model => $policy) {
            Gate::policy($model, $policy);
        }

        Event::listen(MediaHasBeenAddedEvent::class, OptimizeUploadedImage::class);

        View::composer(['components.layouts.app', 'components.seo', 'components.site.*'], SiteComposer::class);
        Paginator::defaultView('vendor.pagination.uaz');
    }
}
