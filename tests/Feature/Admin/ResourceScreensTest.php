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
