<?php

namespace App\Providers;

use App\Listeners\OptimizeUploadedImage;
use App\Models\ContactMessage;
use App\Models\Event as EventModel;
use App\Models\Faculty;
use App\Models\Formation;
use App\Models\MediaAsset;
use App\Models\Menu;
use App\Models\NewsCategory;
use App\Models\NewsPost;
use App\Models\Page;
use App\Models\Partner;
use App\Models\PhotoAlbum;
use App\Models\Redirect;
use App\Models\TeamMember;
use App\Models\Testimonial;
use App\Models\User;
use App\Policies\ContactMessagePolicy;
use App\Policies\ContentPolicy;
use App\Policies\ManagedContentPolicy;
use App\Policies\MediaAssetPolicy;
use App\Policies\PagePolicy;
use App\Policies\SettingsPolicy;
use App\Policies\UserPolicy;
use App\View\Composers\SiteComposer;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
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
        NewsCategory::class => ManagedContentPolicy::class,
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
