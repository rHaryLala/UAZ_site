<?php

use App\Filament\Pages\ManageSiteSettings;
use App\Filament\Resources\ContactMessages\Pages\ListContactMessages;
use App\Filament\Resources\ContentPages\Pages\CreatePage;
use App\Filament\Resources\ContentPages\Pages\ListPages;
use App\Filament\Resources\Events\Pages\CreateEvent;
use App\Filament\Resources\Events\Pages\ListEvents;
use App\Filament\Resources\Faculties\Pages\ManageFaculties;
use App\Filament\Resources\Formations\Pages\CreateFormation;
use App\Filament\Resources\Formations\Pages\ListFormations;
use App\Filament\Resources\MediaAssets\Pages\ListMediaAssets;
use App\Filament\Resources\Menus\Pages\CreateMenu;
use App\Filament\Resources\Menus\Pages\ListMenus;
use App\Filament\Resources\NewsCategories\Pages\ManageNewsCategories;
use App\Filament\Resources\NewsPosts\Pages\CreateNewsPost;
use App\Filament\Resources\NewsPosts\Pages\ListNewsPosts;
use App\Filament\Resources\Partners\Pages\ManagePartners;
use App\Filament\Resources\PhotoAlbums\Pages\CreatePhotoAlbum;
use App\Filament\Resources\PhotoAlbums\Pages\ListPhotoAlbums;
use App\Filament\Resources\Redirects\Pages\ManageRedirects;
use App\Filament\Resources\TeamMembers\Pages\ManageTeamMembers;
use App\Filament\Resources\Testimonials\Pages\ManageTestimonials;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Filament\Widgets\ContentOverview;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    actingAs(tap(User::factory()->create())->assignRole(RolesAndPermissionsSeeder::SUPER_ADMIN));
});

it('renders every list, manage and create screen without error', function (string $component) {
    Livewire::test($component)->assertOk();
})->with([
    ListPages::class, CreatePage::class,
    ListNewsPosts::class, CreateNewsPost::class,
    ListEvents::class, CreateEvent::class,
    ListFormations::class, CreateFormation::class,
    ListPhotoAlbums::class, CreatePhotoAlbum::class,
    ListMediaAssets::class,
    ListMenus::class, CreateMenu::class,
    ListContactMessages::class,
    ManageFaculties::class, ManageNewsCategories::class, ManageTeamMembers::class,
    ManageTestimonials::class, ManagePartners::class, ManageRedirects::class, ManageUsers::class,
    ManageSiteSettings::class,
]);

it('renders the dashboard', function () {
    // Le widget de statistiques est chargé en différé : on vérifie la page, puis le widget séparément.
    $this->get('/admin')->assertOk()->assertSee('Tableau de bord');

    Livewire::test(ContentOverview::class)->assertOk()->assertSee('Actualités publiées');
});

it('lets the media library switch between list and grid layouts', function () {
    $this->actingAs(\App\Models\User::factory()->create()->assignRole('super-admin'));
    \App\Models\MediaAsset::create(['title' => 'Photo test', 'alt' => 'Description']);

    session(['media_layout' => 'list']);
    \Livewire\Livewire::test(\App\Filament\Resources\MediaAssets\Pages\ListMediaAssets::class)
        ->assertSee('Vue grille')->assertDontSee('Vue liste');

    session(['media_layout' => 'grid']);
    \Livewire\Livewire::test(\App\Filament\Resources\MediaAssets\Pages\ListMediaAssets::class)
        ->loadTable()
        ->assertSee('Vue liste')->assertSee('Photo test');
});

it('serves the dashboard stats from a cache that is flushed when content changes', function () {
    $this->actingAs(\App\Models\User::factory()->create()->assignRole('super-admin'));
    \Illuminate\Support\Facades\Cache::flush();

    expect(\App\Support\AdminStats::get()['messages_unread'])->toBe(0);

    \App\Models\ContactMessage::factory()->create();

    expect(\App\Support\AdminStats::get()['messages_unread'])->toBe(1);
});

it('does not poll the dashboard stats and loads them lazily', function () {
    $widget = new \ReflectionClass(\App\Filament\Widgets\ContentOverview::class);

    expect($widget->getProperty('pollingInterval')->getDefaultValue())->toBeNull()
        ->and($widget->getProperty('isLazy')->getDefaultValue())->toBeTrue();
});

it('keeps the admin list screens under a small SQL query budget', function () {
    $this->actingAs(\App\Models\User::factory()->create()->assignRole('super-admin'));
    \App\Models\TeamMember::factory()->count(12)->create();
    \Livewire\Livewire::test(\App\Filament\Resources\TeamMembers\Pages\ManageTeamMembers::class)->loadTable(); // échauffement (permissions)

    $count = 0;
    \Illuminate\Support\Facades\DB::listen(function () use (&$count) { $count++; });
    \Livewire\Livewire::test(\App\Filament\Resources\TeamMembers\Pages\ManageTeamMembers::class)->loadTable();

    // Avant le chargement groupé : 15 requêtes par membre affiché.
    expect($count)->toBeLessThan(40);
});

it('gives list thumbnails an absolute URL that Filament image columns accept', function () {
    $asset = \App\Models\MediaAsset::create(['title' => 'Vignette', 'alt' => 'Une vignette']);
    $path = sys_get_temp_dir().'/vignette-'.uniqid().'.jpg';
    imagejpeg(imagecreatetruecolor(900, 600), $path);
    $asset->addMedia($path)->toMediaCollection(\App\Models\MediaAsset::COLLECTION);

    expect($asset->refresh()->absoluteUrl())->toStartWith('http')->and(\App\Models\MediaAsset::create(['alt' => 'Sans fichier'])->absoluteUrl())->toBeNull();
});

it('organises the navigation in four groups with the inbox first', function () {
    $this->actingAs(\App\Models\User::factory()->create()->assignRole('super-admin'));
    $groups = collect(\Filament\Facades\Filament::getPanel('admin')->getNavigationGroups())->map(fn ($g) => $g->getLabel())->all();

    expect($groups)->toBe(['Contenus', 'Formations', 'Personnes & messages', 'Site & médias'])
        ->and(\App\Filament\Resources\ContactMessages\ContactMessageResource::getNavigationSort())->toBe(1)
        ->and(\App\Filament\Resources\ContactMessages\ContactMessageResource::getNavigationGroup())->toBe('Personnes & messages');
});

it('renders the dashboard with its shortcuts and lists for a super admin', function () {
    $this->actingAs(\App\Models\User::factory()->create()->assignRole('super-admin'));

    \Livewire\Livewire::test(\App\Filament\Pages\Dashboard::class)->assertOk();
    \Livewire\Livewire::test(\App\Filament\Widgets\QuickActions::class)->assertSee('Écrire une actualité')->assertSee('Lire les messages');
    \Livewire\Livewire::test(\App\Filament\Widgets\LatestMessages::class)->assertOk();
    \Livewire\Livewire::test(\App\Filament\Widgets\LatestDrafts::class)->assertOk();
});

it('hides the messages shortcut and list from people who cannot read messages', function () {
    $this->actingAs(\App\Models\User::factory()->create()->assignRole('contributor'));

    expect(\App\Filament\Widgets\LatestMessages::canView())->toBeFalse();
    \Livewire\Livewire::test(\App\Filament\Widgets\QuickActions::class)->assertDontSee('Lire les messages');
});
