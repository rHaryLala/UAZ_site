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
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
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
        // Le super-admin passe toutes les autorisations.
        Gate::before(fn ($user) => $user->hasRole(RolesAndPermissionsSeeder::SUPER_ADMIN) ?: null);

        foreach (self::POLICIES as $model => $policy) {
            Gate::policy($model, $policy);
        }

        Event::listen(MediaHasBeenAddedEvent::class, OptimizeUploadedImage::class);
    }
}
