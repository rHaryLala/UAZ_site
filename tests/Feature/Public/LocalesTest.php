<?php

use App\Models\Formation;
use App\Models\NewsletterSubscriber;
use App\Models\Page;
use App\Support\Locales;
use Database\Seeders\StructureSeeder;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->seed(StructureSeeder::class);
    Cache::flush();
});

it('serves the site in French without prefix, and in English and Malagasy under /en and /mg', function () {
    $this->get('/formations')->assertOk()->assertSee('<html lang="fr"', false);
    $this->get('/en/formations')->assertOk()->assertSee('<html lang="en"', false);
    $this->get('/mg/formations')->assertOk()->assertSee('<html lang="mg"', false);
    $this->get('/en')->assertOk()->assertSee('<html lang="en"', false);
    $this->get('/mg/')->assertOk()->assertSee('<html lang="mg"', false);
});

it('prefixes every link of a translated page but never the files', function () {
    $html = $this->get('/en/formations')->assertOk()->getContent();

    expect($html)->toContain('href="'.u('/').'/en/formations')
        ->and($html)->toContain('href="'.u('/').'/en/actualites"')
        ->and($html)->not->toContain('/en/build/')
        ->and($html)->not->toContain('/en/favicon');

    // Le retour au français retire le préfixe, même après une visite en anglais dans la même session de test.
    $fr = $this->get('/formations')->getContent();
    expect($fr)->not->toContain('href="'.u('/').'/en/actualites')->and($fr)->toContain('href="'.u('/actualites').'"');
});

it('resolves free pages, formations and the home page under a prefix', function () {
    $about = Page::where('key', 'about')->firstOrFail();
    $formation = Formation::first();

    $this->get('/en/'.$about->path)->assertOk();
    $this->get('/mg/'.$about->path)->assertOk();
    $this->get('/en/formations/'.$formation->slug)->assertOk()->assertSee('/en/formations', false);
    $this->get('/en/page-qui-n-existe-pas')->assertNotFound();
});

it('offers the same page in the other languages, keeping the query string', function () {
    $html = $this->get('/en/formations?niveau=licence')->assertOk()->getContent();

    expect($html)->toContain('hreflang="fr" href="'.u('/formations?niveau=licence').'"')
        ->and($html)->toContain('hreflang="en" href="'.u('/en/formations?niveau=licence').'"')
        ->and($html)->toContain('hreflang="mg" href="'.u('/mg/formations?niveau=licence').'"')
        ->and($html)->toContain('hreflang="x-default" href="'.u('/formations?niveau=licence').'"')
        ->and($html)->toContain('<meta property="og:locale" content="en_US">')
        ->and($html)->toContain('aria-current="true"');

    // Le sélecteur de langue pointe vers la page équivalente, l'accueil compris.
    $home = $this->get('/mg')->getContent();
    expect($home)->toContain('hreflang="fr" href="'.u('/').'/"')->and($home)->toContain('hreflang="en" href="'.u('/en').'"');
});

it('keeps technical paths and page slugs apart from the language prefixes', function () {
    expect(Page::RESERVED_SLUGS)->toContain('en', 'mg');

    // Un chemin qui commence par « en » (enseignement) reste une page libre, pas un préfixe.
    Page::create(['title' => 'Enseignement', 'slug' => 'enseignement', 'status' => 'published', 'published_at' => now()->subDay(), 'blocks' => []]);
    $this->get('/enseignement')->assertOk();
    $this->get('/en/enseignement')->assertOk();
});

it('lists every language of every page in the sitemap, with alternates', function () {
    $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

    expect($xml)->toContain('<loc>'.u('/en/formations').'</loc>')
        ->and($xml)->toContain('<loc>'.u('/mg/formations').'</loc>')
        ->and($xml)->toContain('hreflang="en" href="'.u('/en/formations').'"')
        ->and($xml)->toContain('hreflang="x-default" href="'.u('/formations').'"')
        ->and(simplexml_load_string($xml))->not->toBeFalse();
});

it('builds language urls from a bare path and from absolute addresses', function () {
    $this->get('/en/actualites');
    $root = request()->root();

    expect(Locales::barePath())->toBe('actualites')
        ->and(Locales::localize($root.'/formations/x', 'mg'))->toBe($root.'/mg/formations/x')
        ->and(Locales::localize($root.'/', 'en'))->toBe($root.'/en')
        ->and(Locales::localize($root.'/formations', 'fr'))->toBe($root.'/formations');
});

it('accepts the newsletter request on a prefixed url', function () {
    $this->postJson('/en/newsletter', ['email' => 'visiteur@example.test'])->assertSuccessful();

    expect(NewsletterSubscriber::where('email', 'visiteur@example.test')->exists())->toBeTrue();
});

it('translates menu labels with the dictionary of the language and caches menus per language', function () {
    $fr = $this->get('/formations')->getContent();
    $en = $this->get('/en/formations')->getContent();

    expect($fr)->toContain('Formations')->and($en)->not->toBe($fr);
    expect(Cache::has('menu.header.fr'))->toBeTrue()->and(Cache::has('menu.header.en'))->toBeTrue();
});

/** Adresse du site sans préfixe de langue (le préfixe forcé par une requête /en reste actif après elle). */
function u(string $path): string
{
    Locales::reset();

    return url($path);
}
