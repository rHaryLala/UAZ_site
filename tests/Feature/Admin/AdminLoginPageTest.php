<?php

use App\Models\MediaAsset;
use App\Models\NewsPost;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\LoginPhotos;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Auth\Pages\Login;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Cache::forget('login.photos.6');
});

/** Un média d'essai avec ses dimensions et son nom de fichier d'origine. */
function loginAsset(int $width, int $height, string $fileName = 'photo.jpg'): MediaAsset
{
    $asset = MediaAsset::factory()->withImage(400, 300)->create();
    $media = $asset->file();
    $media->setCustomProperty('width', $width)->setCustomProperty('height', $height);
    $media->file_name = $fileName;
    $media->save();

    return $asset->fresh();
}

/** Les trois images des réglages du site : de quoi remplir la vitrine. */
function loginPhotosFromSettings(): array
{
    $assets = [loginAsset(2560, 1707), loginAsset(2560, 1707), loginAsset(2560, 1920)];
    SiteSetting::current()->update(['home_hero_image_id' => $assets[0]->id, 'formations_hero_image_id' => $assets[1]->id, 'home_cta_image_id' => $assets[2]->id]);

    return $assets;
}

it('shows a window of real university photos on the sign-in page, with its style and script, and keeps the form working', function () {
    loginPhotosFromSettings();

    $html = $this->get('/admin/login')->assertOk()
        ->assertSee('data-uaz-scene', false)->assertSee('data-uaz-lens', false)->assertSee('Spiritus · Manus · Mens')
        ->assertSee('Connectez-vous à votre compte')->getContent();

    expect($html)->toContain('admin-login')                              // feuille de style et script de la scène (Vite)
        ->and(substr_count($html, 'data-uaz-gallery='))->toBe(2)          // photos éteintes + photos éclairées
        ->and(substr_count($html, 'data-uaz-tile'))->toBe(6)              // trois photos, deux couches
        ->and($html)->toContain('/storage/')                              // les photos viennent de la médiathèque
        ->and($html)->toContain('wire:submit="authenticate"');            // le formulaire reste celui de Filament
});

it('falls back to a plain seal when the site has fewer than three suitable photos', function () {
    SiteSetting::current()->update(['home_hero_image_id' => loginAsset(2560, 1707)->id]);

    $html = $this->get('/admin/login')->assertOk()->assertSee('data-uaz-scene', false)->getContent();

    expect($html)->not->toContain('data-uaz-gallery=')->not->toContain('data-uaz-lens')->toContain('uaz-plain');
});

it('picks only landscape photos that are big enough, and for news only real camera shots', function () {
    $good = loginPhotosFromSettings();
    SiteSetting::current()->update(['home_hero_image_id' => $good[0]->id]);

    $portrait = loginAsset(1080, 1350);
    $small = loginAsset(800, 533);
    $panorama = loginAsset(2560, 860);
    $screenshot = loginAsset(2560, 1465, 'Design-sans-titre.png');
    $camera = loginAsset(2560, 1707, 'IMG_6538.jpg');
    $other = loginAsset(2560, 1707, 'banniere.jpg');
    foreach ([$portrait, $small, $panorama, $screenshot, $camera, $other] as $i => $asset) {
        NewsPost::factory()->create(['title' => 'Article '.$i, 'cover_id' => $asset->id, 'published_at' => now()->subDays($i + 1)]);
    }

    $ids = LoginPhotos::pick(6)->pluck('id')->all();

    expect($ids)->toContain($good[0]->id, $good[1]->id, $good[2]->id, $camera->id)
        ->not->toContain($portrait->id)->not->toContain($small->id)->not->toContain($panorama->id)
        ->not->toContain($screenshot->id)->not->toContain($other->id);
});

it('keeps the scene decorative and gives the way back to the public site its own landmark', function () {
    loginPhotosFromSettings();

    $html = $this->get('/admin/login')->assertOk()->getContent();

    expect($html)->toMatch('/<div class="uaz-scene" data-uaz-scene aria-hidden="true">/')
        ->and($html)->toContain('<nav class="uaz-back" aria-label="Site public">')
        ->and($html)->toContain('Retour au site public');
});

it('also dresses the forgotten-password page', function () {
    $this->get('/admin/password-reset/request')->assertOk()->assertSee('data-uaz-scene', false)->assertSee('admin-login');
});

it('keeps the scene and its assets out of the rest of the back office', function () {
    $user = User::factory()->create();
    $user->assignRole('editor');

    $html = actingAs($user)->get('/admin')->assertOk()->getContent();

    expect($html)->not->toContain('data-uaz-scene')->not->toContain('admin-login');
});

it('still signs a user in and refuses a wrong password from the dressed page', function () {
    $user = User::factory()->create(['password' => 'Mot-de-passe-test-2026']);
    $user->assignRole('editor');

    Livewire::test(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'faux'])->call('authenticate')
        ->assertHasFormErrors(['email']);

    Livewire::test(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'Mot-de-passe-test-2026'])->call('authenticate')
        ->assertHasNoFormErrors();
});
