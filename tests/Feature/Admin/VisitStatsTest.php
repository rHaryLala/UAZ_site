<?php

use App\Filament\Widgets\TopPages;
use App\Filament\Widgets\VisitorsChart;
use App\Filament\Widgets\VisitorsOverview;
use App\Models\Page;
use App\Models\SiteVisit;
use App\Models\User;
use App\Support\VisitStats;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\StructureSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;

// Statistiques de fréquentation : comptage des visites sans cookie ni adresse IP, et chiffres du tableau de bord.

const BROWSER = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36';

beforeEach(function () {
    config(['uaz.analytics.enabled' => true]);
    Cache::flush();
});

function viewPage(string $uri, array $server = []): TestResponse
{
    return test()->withServerVariables(array_merge(['HTTP_USER_AGENT' => BROWSER, 'REMOTE_ADDR' => '203.0.113.7'], $server))->get($uri);
}

it('counts a public page view without keeping the IP address, a cookie, or anything after the question mark', function () {
    viewPage('/formations?q=secret', ['HTTP_REFERER' => 'https://www.google.com/search?q=uaz'])->assertOk();

    $visit = SiteVisit::query()->sole();

    expect($visit->path)->toBe('/formations')
        ->and($visit->locale)->toBe('fr')
        ->and($visit->referrer)->toBe('google.com')
        ->and($visit->visited_on)->toBe(now()->toDateString())
        ->and($visit->visitor)->toHaveLength(32)->not->toContain('203.0.113.7');

    // Aucune colonne ne porte l'adresse ni le navigateur.
    expect(json_encode($visit->getAttributes()))->not->toContain('203.0.113')->not->toContain('Mozilla');
});

it('recognises the same visitor during a day, and a different one the next day', function () {
    viewPage('/')->assertOk();
    viewPage('/formations')->assertOk();
    viewPage('/', ['REMOTE_ADDR' => '198.51.100.9'])->assertOk();

    $today = SiteVisit::query()->get();
    expect($today)->toHaveCount(3)->and($today->pluck('visitor')->unique())->toHaveCount(2);

    $this->travel(1)->days();
    viewPage('/')->assertOk();

    $first = $today->first()->visitor;
    expect(SiteVisit::query()->latest('id')->first()->visitor)->not->toBe($first);
});

it('records the language of the page and ignores a visit coming from the site itself', function () {
    viewPage('/en/formations', ['HTTP_REFERER' => 'http://localhost/'])->assertOk();

    $visit = SiteVisit::query()->sole();
    expect($visit->locale)->toBe('en')->and($visit->referrer)->toBeNull();
});

it('does not count robots, the administration, files, errors, signed links, prefetching, API calls or people who are logged in', function () {
    viewPage('/', ['HTTP_USER_AGENT' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)']);
    viewPage('/', ['HTTP_USER_AGENT' => 'curl/8.5.0']);
    viewPage('/', ['HTTP_USER_AGENT' => '']);
    viewPage('/page-qui-n-existe-pas')->assertNotFound();
    viewPage('/admin/login');
    viewPage('/sitemap.xml');
    viewPage('/robots.txt');
    viewPage('/up');
    viewPage('/?signature=abc');
    viewPage('/', ['HTTP_PURPOSE' => 'prefetch']);
    test()->withServerVariables(['HTTP_USER_AGENT' => BROWSER])->getJson('/formations');
    test()->withServerVariables(['HTTP_USER_AGENT' => BROWSER])->post('/newsletter', ['email' => 'x@exemple.test']);

    expect(SiteVisit::query()->count())->toBe(0);

    $this->seed(RolesAndPermissionsSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(RolesAndPermissionsSeeder::EDITOR));
    viewPage('/')->assertOk();

    expect(SiteVisit::query()->count())->toBe(0);
});

it('counts nothing when the tracking is switched off, and never breaks the page if the table is unreachable', function () {
    config(['uaz.analytics.enabled' => false]);
    viewPage('/')->assertOk();
    expect(SiteVisit::query()->count())->toBe(0);

    config(['uaz.analytics.enabled' => true]);
    Schema::drop('site_visits');
    viewPage('/')->assertOk();
});

it('computes visitors and page views per day, with empty days filled in', function () {
    $row = fn (string $day, string $visitor, string $path = '/', ?string $referrer = null) => ['visited_on' => $day, 'visitor' => $visitor, 'path' => $path, 'locale' => 'fr', 'referrer' => $referrer, 'visited_at' => $day.' 10:00:00'];
    $today = now()->toDateString();
    $yesterday = now()->subDay()->toDateString();

    SiteVisit::query()->insert([
        $row($today, 'a'), $row($today, 'a', '/formations'), $row($today, 'b', '/formations', 'facebook.com'),
        $row($yesterday, 'c'), $row(now()->subDays(10)->toDateString(), 'd'), $row(now()->subDays(40)->toDateString(), 'e'),
    ]);

    $summary = VisitStats::summary();

    expect($summary)->toMatchArray(['today' => 2, 'yesterday' => 1, 'week' => 3, 'month' => 4, 'views_month' => 5])
        ->and($summary['since'])->toBe(now()->subDays(40)->toDateString())
        ->and(VisitStats::daily(7))->toHaveCount(7)
        ->and(VisitStats::daily(7)[$today])->toBe(['visitors' => 2, 'views' => 3])
        ->and(VisitStats::daily(7)[now()->subDays(3)->toDateString()])->toBe(['visitors' => 0, 'views' => 0])
        ->and(VisitStats::topPages(30, 5))->toBe([['path' => '/', 'views' => 3, 'visitors' => 3], ['path' => '/formations', 'views' => 2, 'visitors' => 2]])
        ->and(VisitStats::topReferrers())->toBe([['host' => 'facebook.com', 'views' => 1]]);
});

it('shows the visitor figures on the dashboard widgets, even before the first visit', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(RolesAndPermissionsSeeder::SUPER_ADMIN));

    Livewire::test(VisitorsOverview::class)->assertOk()->assertSee('Visiteurs aujourd')->assertSee('Le comptage démarre');
    Livewire::test(TopPages::class)->assertOk()->assertSee('Aucune visite comptée');

    // (Une personne connectée au back-office n'est pas comptée : la visite est donc enregistrée directement.)
    SiteVisit::query()->insert(['visited_on' => now()->toDateString(), 'visitor' => 'abc', 'path' => '/formations', 'locale' => 'fr', 'referrer' => 'facebook.com', 'visited_at' => now()]);
    Cache::flush();

    Livewire::test(VisitorsOverview::class)->assertSee('Pages vues sur 30 jours')->assertSee('1,0 pages par visite');
    Livewire::test(TopPages::class)->assertSee('/formations')->assertSee('facebook.com');
    Livewire::test(VisitorsChart::class)->assertOk()->assertSee('Fréquentation');
});

it('puts the visitor widgets on the dashboard, and the statistics in the privacy page', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole(RolesAndPermissionsSeeder::SUPER_ADMIN));

    // Les widgets se chargent après l'affichage de la page (chargement différé) : on vérifie qu'ils sont déclarés sur le panneau, dans l'ordre voulu.
    $this->get('/admin')->assertOk();
    expect(Filament\Facades\Filament::getPanel('admin')->getWidgets())->toContain(VisitorsOverview::class, VisitorsChart::class, TopPages::class);

    $this->seed(StructureSeeder::class);
    $page = Page::query()->where('key', 'privacy')->firstOrFail();
    $text = json_encode($page->blocks, JSON_UNESCAPED_UNICODE);

    expect($text)->toContain('Statistiques de fréquentation')->not->toContain("outil de mesure d'audience")->toContain('Aucun cookie n\'est déposé');
});

it('rewrites the privacy page of an existing site once, in French and English, and leaves a text edited by hand alone', function () {
    $migration = require database_path('migrations/2026_11_19_120000_add_visit_statistics_to_privacy_page.php');
    $block = fn (string $heading, string $html) => ['type' => 'rich_text', 'data' => ['heading' => $heading, 'content' => $html, 'width' => 'normal']];

    $page = Page::factory()->create(['key' => 'privacy', 'blocks' => [
        $block('En bref', "<p>Ce site n'utilise <strong>aucun cookie publicitaire, aucun outil de mesure d'audience et aucun traceur de réseau social</strong>. Il ne dépose que deux cookies.</p>"),
        $block('Les cookies de ce site', '<p>Deux cookies.</p>'),
        $block('Mise à jour', '<p>Dernière mise à jour : 28 octobre 2026.</p>'),
    ]]);
    DB::table('pages')->where('id', $page->id)->update(['translations' => json_encode(['en' => ['blocks' => [
        $block('In short', '<p>This site uses <strong>no advertising cookies, no audience-measurement tools and no social-network trackers</strong>. It only sets two cookies.</p>'),
        $block('The cookies on this site', '<p>Two cookies.</p>'),
    ]]])]);

    $migration->up();
    $migration->up(); // une seconde fois : rien ne change

    $page->refresh();
    $french = collect($page->blocks);
    $english = collect($page->translations['en']['blocks']);

    expect($french->pluck('data.heading')->all())->toBe(['En bref', 'Les cookies de ce site', 'Statistiques de fréquentation', 'Mise à jour'])
        ->and($french[0]['data']['content'])->not->toContain("mesure d'audience")->toContain('le nombre de visiteurs')
        ->and($french[3]['data']['content'])->toContain('19 novembre 2026')
        ->and($english->pluck('data.heading')->all())->toBe(['In short', 'The cookies on this site', 'Visit statistics'])
        ->and($english[0]['data']['content'])->not->toContain('audience-measurement');

    // Un texte réécrit à la main (sans la phrase d'origine) n'est pas touché, et le bloc n'est pas ajouté s'il manque son point d'ancrage.
    $edited = Page::factory()->create(['key' => 'privacy-copie', 'blocks' => [$block('En bref', '<p>Texte réécrit.</p>')]]);
    $migration->up();

    expect($edited->refresh()->blocks)->toHaveCount(1)->and($edited->blocks[0]['data']['content'])->toBe('<p>Texte réécrit.</p>');
});
