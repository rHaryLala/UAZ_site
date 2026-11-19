<?php

use App\Filament\Resources\SiteVisits\Pages\ListSiteVisits;
use App\Filament\Resources\SiteVisits\SiteVisitResource;
use App\Filament\Widgets\TopPages;
use App\Models\Page;
use App\Models\SiteVisit;
use App\Models\User;
use App\Services\IpGeolocation;
use App\Support\UserAgent;
use App\Support\VisitStats;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

// Lieu approximatif des visiteurs (d'après l'adresse IP, qui n'est jamais conservée), appareil, et écran « Visiteurs du site ».

const DESKTOP_CHROME = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36';

beforeEach(function () {
    config(['uaz.analytics.enabled' => true]);
    Cache::flush();
});

function visitFrom(string $ip, string $agent = DESKTOP_CHROME): TestResponse
{
    return test()->withServerVariables(['HTTP_USER_AGENT' => $agent, 'REMOTE_ADDR' => $ip])->get('/formations');
}

function visitRow(string $visitor, string $time, string $path, array $more = []): array
{
    return array_merge([
        'visited_on' => now()->toDateString(), 'visitor' => $visitor, 'path' => $path, 'locale' => 'fr', 'referrer' => null,
        'country_code' => 'MG', 'country' => 'Madagascar', 'region' => 'Analamanga', 'city' => 'Antananarivo', 'device' => 'mobile', 'browser' => 'Chrome',
        'visited_at' => now()->toDateString().' '.$time,
    ], $more);
}

it('records the approximate place, the device and the browser — never the address or the browser text', function () {
    visitFrom('102.16.0.1', 'Mozilla/5.0 (Linux; Android 14; SM-A546B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Mobile Safari/537.36')->assertOk();

    $visit = SiteVisit::query()->sole();

    expect($visit->device)->toBe('mobile')->and($visit->browser)->toBe('Chrome')
        ->and(json_encode($visit->getAttributes()))->not->toContain('102.16.0.1')->not->toContain('Android')->not->toContain('SM-A546B')
        ->and($visit->country_code)->toBe('MG')->and($visit->city)->toBe('Antananarivo');
})->skip(fn () => ! IpGeolocation::available(), 'base de géolocalisation absente (php artisan geoip:update)');

it('finds a place from an IP address with the local database, and nothing for private or unknown addresses', function () {
    $geo = new IpGeolocation;

    expect($geo->lookup('102.16.0.1'))->toMatchArray(['country_code' => 'MG', 'country' => 'Madagascar', 'region' => 'Analamanga', 'city' => 'Antananarivo'])
        ->and($geo->lookup('8.8.8.8')['country_code'])->toBe('US')
        ->and($geo->lookup('2001:4860:4860::8888')['country_code'])->not->toBeNull()
        ->and($geo->lookup('192.168.1.5'))->toBeNull()
        ->and($geo->lookup('127.0.0.1'))->toBeNull()
        ->and($geo->lookup('pas une adresse'))->toBeNull()
        ->and($geo->lookup(null))->toBeNull();
})->skip(fn () => ! IpGeolocation::available(), 'base de géolocalisation absente (php artisan geoip:update)');

it('still counts the visit, without a place, when the geolocation database is missing', function () {
    $path = IpGeolocation::path();
    $backup = $path.'.sauvegarde';
    $existed = is_file($path);

    if ($existed) {
        IpGeolocation::reset();
        rename($path, $backup);
    }

    try {
        visitFrom('102.16.0.1')->assertOk();
        $visit = SiteVisit::query()->sole();
        expect($visit->country_code)->toBeNull()->and($visit->city)->toBeNull()->and($visit->device)->toBe('desktop');
    } finally {
        if ($existed) {
            rename($backup, $path);
        }
    }
});

it('tells the device and the browser from the user agent', function (string $agent, string $device, string $browser) {
    expect(UserAgent::parse($agent))->toBe(['device' => $device, 'browser' => $browser]);
})->with([
    [DESKTOP_CHROME, 'desktop', 'Chrome'],
    [DESKTOP_CHROME.' Edg/130.0', 'desktop', 'Edge'],
    ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1', 'mobile', 'Safari'],
    ['Mozilla/5.0 (iPad; CPU OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1', 'tablet', 'Safari'],
    ['Mozilla/5.0 (Android 14; Mobile; rv:128.0) Gecko/128.0 Firefox/128.0', 'mobile', 'Firefox'],
    ['Mozilla/5.0 (Linux; Android 14; SM-X200) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36', 'tablet', 'Chrome'],
    ['Mozilla/5.0 (Linux; Android 14; SAMSUNG SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/25.0 Chrome/121.0 Mobile Safari/537.36', 'mobile', 'Samsung Internet'],
    ['', 'desktop', 'Autre'],
]);

it('shows the visitors, one per day, with their place and the pages they viewed in order, to the super-administrator only', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    SiteVisit::query()->insert([
        visitRow(str_repeat('a', 32), '09:00:00', '/', ['referrer' => 'google.com']),
        visitRow(str_repeat('a', 32), '09:02:30', '/formations'),
        visitRow(str_repeat('a', 32), '09:05:10', '/contact'),
        visitRow(str_repeat('b', 32), '10:00:00', '/admissions', ['country_code' => 'FR', 'country' => 'France', 'region' => 'Paris', 'city' => 'Paris', 'device' => 'desktop', 'browser' => 'Firefox']),
    ]);

    $this->actingAs(User::factory()->create()->assignRole(RolesAndPermissionsSeeder::EDITOR));
    $this->get(SiteVisitResource::getUrl())->assertForbidden();

    $this->actingAs(User::factory()->create()->assignRole(RolesAndPermissionsSeeder::SUPER_ADMIN));
    $this->get(SiteVisitResource::getUrl())->assertOk();

    Livewire::test(ListSiteVisits::class)->loadTable()->assertOk()->assertSee('Antananarivo, Analamanga, Madagascar')->assertSee('Paris, France')->assertSee('#AAAAAA')->assertSee('#BBBBBB');

    $visitors = SiteVisitResource::getEloquentQuery()->get();
    expect($visitors)->toHaveCount(2)->and($visitors->firstWhere('visitor', str_repeat('a', 32))->pages)->toBe(3);

    Livewire::test(ListSiteVisits::class)->loadTable()->filterTable('country_code', 'FR')->assertSee('Paris, France')->assertDontSee('#AAAAAA');

    $record = $visitors->firstWhere('visitor', str_repeat('a', 32));
    // (Les filtres sont gardés en session : celui du pays est retiré avant d'ouvrir le parcours du visiteur de Madagascar.)
    $component = Livewire::test(ListSiteVisits::class)->loadTable()->resetTableFilters()->mountTableAction('journey', $record);
    expect($component->instance()->mountedActions[0]['name'])->toBe('journey');

    // Le contenu de la fenêtre « Parcours » (la fenêtre elle-même est dessinée par le navigateur) : les trois pages, dans l'ordre.
    $journey = (string) $component->instance()->getMountedAction()->getModalContent();

    expect($journey)->toContain('/formations')->toContain('/contact')->toContain('Venu de google.com')->toContain('09:02:30')->toContain('3 pages vues')
        ->not->toContain('/admissions')
        ->and(strpos($journey, '/formations'))->toBeLessThan(strpos($journey, '/contact'));
});

it('adds the cities to the dashboard and keeps the permission for the super-administrator', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    SiteVisit::query()->insert([
        visitRow('a', '09:00:00', '/'),
        visitRow('b', '09:01:00', '/'),
        visitRow('c', '09:02:00', '/', ['country_code' => 'FR', 'country' => 'France', 'region' => null, 'city' => null, 'device' => 'desktop', 'browser' => 'Safari']),
    ]);

    expect(VisitStats::topPlaces())->toBe([['place' => 'Antananarivo, Madagascar', 'visitors' => 2], ['place' => 'France', 'visitors' => 1]]);

    $this->actingAs(User::factory()->create()->assignRole(RolesAndPermissionsSeeder::SUPER_ADMIN));
    Livewire::test(TopPages::class)->assertSee('Antananarivo, Madagascar')->assertSee('Voir chaque visiteur');

    $this->actingAs(User::factory()->create()->assignRole(RolesAndPermissionsSeeder::EDITOR));
    Livewire::test(TopPages::class)->assertSee('Antananarivo, Madagascar')->assertDontSee('Voir chaque visiteur');

    expect(Permission::where('name', 'visitors.view')->exists())->toBeTrue()
        ->and(Role::findByName(RolesAndPermissionsSeeder::EDITOR)->hasPermissionTo('visitors.view'))->toBeFalse();
});

it('keeps the geolocation database when no new one can be downloaded, and does not download a recent one', function () {
    Http::fake(['download.db-ip.com/*' => Http::response('', 404)]);
    $path = IpGeolocation::path();
    $existed = is_file($path);

    $this->artisan('geoip:update', ['--force' => true])->assertFailed();
    expect(is_file($path))->toBe($existed);
    Http::assertSentCount(3);

    if ($existed) {
        touch($path);
        $this->artisan('geoip:update')->expectsOutputToContain('à jour')->assertSuccessful();
        Http::assertSentCount(3);
    }
});

it('rewrites the privacy page once more to describe the place, the device and the database, and leaves a text edited by hand alone', function () {
    $first = require database_path('migrations/2026_11_19_120000_add_visit_statistics_to_privacy_page.php');
    $second = require database_path('migrations/2026_11_19_140000_describe_visitor_location_in_privacy_page.php');
    $block = fn (string $heading, string $html) => ['type' => 'rich_text', 'data' => ['heading' => $heading, 'content' => $html, 'width' => 'normal']];

    $page = Page::factory()->create(['key' => 'privacy', 'blocks' => [
        $block('En bref', "<p>Ce site n'utilise <strong>aucun cookie publicitaire, aucun outil de mesure d'audience et aucun traceur de réseau social</strong>. Il ne dépose que deux cookies.</p>"),
        $block('Les cookies de ce site', '<p>Deux cookies.</p>'),
        $block('Mise à jour', '<p>Dernière mise à jour : 28 octobre 2026.</p>'),
    ]]);

    $first->up();
    $second->up();
    $second->up(); // une seconde fois : rien ne change

    $blocks = collect($page->refresh()->blocks);
    $stats = $blocks->firstWhere('data.heading', 'Statistiques de fréquentation')['data']['content'];

    expect($blocks->pluck('data.heading')->all())->toBe(['En bref', 'Les cookies de ce site', 'Statistiques de fréquentation', 'Mise à jour'])
        ->and($blocks[0]['data']['content'])->toContain('localisation approximative')->not->toContain('le nombre de visiteurs')
        ->and($stats)->toContain('localisation approximative')->toContain("votre adresse n'est envoyée à aucun service extérieur")->toContain('DB-IP')->toContain('400 jours')
        ->not->toContain('empreinte chiffrée');

    $edited = $blocks->all();
    $edited[2]['data']['content'] = '<p>Texte réécrit à la main.</p>';
    $page->forceFill(['blocks' => $edited])->save();
    $second->up();

    expect(collect($page->refresh()->blocks)->firstWhere('data.heading', 'Statistiques de fréquentation')['data']['content'])->toBe('<p>Texte réécrit à la main.</p>');
});
