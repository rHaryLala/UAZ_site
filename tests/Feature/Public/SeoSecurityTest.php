<?php

use App\Models\Event;
use App\Models\Formation;
use App\Models\NewsPost;
use App\Models\Page;
use Database\Seeders\StructureSeeder;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->seed(StructureSeeder::class);
    Cache::flush();
});

it('serves a sitemap with published content only', function () {
    $live = NewsPost::factory()->create(['title' => 'Article en ligne']);
    $draft = NewsPost::factory()->draft()->create(['title' => 'Article brouillon']);
    $scheduled = Event::factory()->create(['title' => 'Événement futur', 'status' => 'draft']);

    $response = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
    $xml = $response->getContent();

    expect($xml)->toContain('<urlset')
        ->and($xml)->toContain('<loc>'.url('/').'</loc>')
        ->and($xml)->toContain($live->url())
        ->and($xml)->toContain(Formation::first()->url())
        ->and($xml)->toContain(url('/admissions/licence'))
        ->and($xml)->not->toContain($draft->url())
        ->and($xml)->not->toContain($scheduled->url())
        ->and($xml)->not->toContain('/accueil');

    expect(simplexml_load_string($xml))->not->toBeFalse();
});

it('closes robots.txt outside production and opens it with a sitemap in production', function () {
    $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /', false)->assertDontSee('Sitemap:');

    app()->detectEnvironment(fn () => 'production');

    $this->get('/robots.txt')->assertOk()
        ->assertSee('Disallow: /admin', false)
        ->assertSee('Sitemap: '.url('/sitemap.xml'), false)
        ->assertDontSee("Disallow: /\n", false);
});

it('sends a strict CSP with a per-request nonce that matches the inline scripts', function () {
    $response = $this->get('/contact')->assertOk();
    $csp = $response->headers->get('Content-Security-Policy');

    expect($csp)->toContain("default-src 'self'")
        ->and($csp)->toContain("object-src 'none'")
        ->and($csp)->toContain("frame-ancestors 'self'")
        ->and($csp)->toContain('frame-src https://www.openstreetmap.org')
        ->and($csp)->not->toContain('unsafe-eval')
        ->and($csp)->not->toContain("'unsafe-inline' ")
        ->and($csp)->toContain("style-src-attr 'unsafe-inline'");

    preg_match("/'nonce-([^']+)'/", $csp, $m);
    $html = $response->getContent();

    expect($m[1] ?? '')->not->toBe('')
        ->and($html)->toContain('nonce="'.$m[1].'"');

    // Chaque <script> exécutable (hors données JSON-LD) porte le nonce.
    preg_match_all('/<script(?![^>]*application\/ld\+json)([^>]*)>/', $html, $scripts);
    foreach ($scripts[1] as $attributes) {
        expect($attributes)->toContain('nonce="'.$m[1].'"');
    }

    // Livewire (formulaire de contact) : balises <style> et configuration avec le même nonce.
    expect($html)->toContain('data-livewire-style');
});

it('uses a different nonce on every request', function () {
    $nonce = fn () => preg_match("/'nonce-([^']+)'/", $this->get('/')->headers->get('Content-Security-Policy'), $m) ? $m[1] : '';

    expect($nonce())->not->toBe($nonce());
});

it('adds the generic security headers on public pages', function () {
    $this->get('/')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin')
        ->assertHeader('Permissions-Policy');
});

it('does not apply the strict CSP to the Filament back-office, which needs inline scripts', function () {
    $response = $this->get('/admin/login')->assertOk();

    expect($response->headers->get('Content-Security-Policy'))->toBeNull();
    $response->assertHeader('X-Frame-Options', 'SAMEORIGIN')->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('sets HSTS only in production over HTTPS', function () {
    $this->get('/')->assertHeaderMissing('Strict-Transport-Security');

    app()->detectEnvironment(fn () => 'production');

    $this->get('https://localhost/')->assertHeader('Strict-Transport-Security');
});

it('publishes University and WebSite structured data on the home page', function () {
    $html = $this->get('/')->getContent();

    expect($html)->toContain('"@type":"CollegeOrUniversity"')
        ->and($html)->toContain('"@type":"WebSite"')
        ->and($html)->toContain('"@type":"SearchAction"')
        ->and($html)->toContain('Antsirabe')
        ->and($html)->toContain('https://www.facebook.com/uazsambaina');
});

it('publishes BreadcrumbList structured data on inner pages', function () {
    $html = $this->get('/admissions/licence')->getContent();

    expect($html)->toContain('"@type":"BreadcrumbList"')->and($html)->toContain('"position":1')->and($html)->toContain('Licence');
});

it('keeps JSON-LD safe even with hostile titles', function () {
    $post = NewsPost::factory()->create(['title' => 'Titre </script><script>alert(1)</script>']);

    $html = $this->get($post->url())->getContent();

    expect($html)->not->toContain('</script><script>alert(1)')->and($html)->toContain('</script>');
});

it('noindexes the search page and unpublished previews but not regular pages', function () {
    $this->get('/recherche')->assertSee('noindex', false);
    $this->get('/formations')->assertDontSee('noindex', false);
});

it('serves canonical URLs without tracking parameters', function () {
    $this->get('/formations?niveau=licence&utm_source=x')->assertSee('<link rel="canonical" href="'.url('/formations').'">', false);
});

it('gives every system page a unique title and meta description', function () {
    $titles = collect(['/a-propos', '/admissions/licence', '/vie-etudiante/cafeteria', '/formations', '/contact'])
        ->map(fn ($path) => preg_match('#<title>(.*?)</title>#', $this->get($path)->getContent(), $m) ? $m[1] : null);

    expect($titles->filter()->unique()->count())->toBe(5);
});

it('does not list pages that are drafts in the sitemap', function () {
    Page::where('key', 'about')->update(['status' => 'draft']);

    expect($this->get('/sitemap.xml')->getContent())->not->toContain(url('/a-propos'));
});
