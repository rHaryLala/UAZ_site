<?php

use App\Models\Event;
use App\Models\MediaAsset;
use App\Models\NewsPost;
use App\Models\Page;
use App\Models\Partner;
use App\Models\Testimonial;
use Carbon\Carbon;
use Database\Seeders\ContentTranslationsSeeder;
use Database\Seeders\StructureSeeder;

beforeEach(fn () => $this->seed(StructureSeeder::class));
afterEach(fn () => Carbon::setTestNow());

it('keeps the hero and the "Votre futur commence ici" video section exactly as they were', function () {
    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('data-hero')->toContain('data-hero-title')->toContain('data-hero-ticker')->toContain('data-reveal')
        ->and($html)->toContain('data-future-title')->toContain('glitch-title')->toContain('data-future-video')->toContain('videos/futur-960.mp4')
        ->and($html)->toContain('Préparer aujourd’hui les leaders de demain');

    // l'ordre de la page est inchangé : héros, « La différence », vidéo, puis les autres sections redessinées
    expect(strpos($html, 'data-hero-title'))->toBeLessThan(strpos($html, 'data-home="diff"'))
        ->and(strpos($html, 'data-home="diff"'))->toBeLessThan(strpos($html, 'data-future-title'))
        ->and(strpos($html, 'data-future-title'))->toBeLessThan(strpos($html, 'data-home="dom"'));
});

it('opens with the statement and the key figures, whose final value is already in the page', function () {
    $html = $this->get('/')->assertOk()->assertSee('data-home-light', false)->getContent();

    expect($html)->toContain('La différence UAZ')->toContain('Mentions de licence')
        ->and(substr_count($html, 'class="home-fig"'))->toBe(4)
        ->and($html)->toContain('data-value="1995"')->toContain('data-value="200"')
        ->and($html)->toMatch('/class="sr-only">200\+</'); // lecteurs d'écran : la valeur finale, sans le compteur
});

it('offers one panel per training domain, with its programmes as links', function () {
    $html = $this->get('/')->assertOk()->assertSee('data-home-dom', false)->getContent();

    expect(substr_count($html, 'data-home-panel'))->toBe(4)
        ->and($html)->toContain('Arts &amp; lettres')->toContain('Sciences sociales')->toContain('/formations/licence-en-droit')
        ->and($html)->toContain('>01<')->toContain('>04<');
});

it('keeps the admissions call to action and the seal ring', function () {
    $html = $this->get('/')->assertOk()->assertSee('data-home-seal', false)->getContent();

    expect($html)->toContain('href="'.config('uaz.registration_url').'"')->toContain('SPIRITUS · MANUS · MENS')
        ->and($html)->toContain('data-home-magnet');
});

it('stacks the three student-life places with their real photos', function () {
    $cover = MediaAsset::factory()->withImage(800, 600)->create();
    Page::where('key', 'student-life-library')->update(['cover_id' => $cover->id]);

    $html = $this->get('/')->assertOk()->assertSee('data-home-stack', false)->getContent();

    expect(substr_count($html, 'data-home-card-inner'))->toBe(3)
        ->and($html)->toContain('Bibliothèque')->toContain('Cafétéria')->toContain('Dortoirs')
        ->and(preg_match_all('/<img[^>]*data-home-card-img/', $html))->toBe(1); // seule la bibliothèque a une photo dans ce jeu d'essai
});

it('hides the student-life stack when none of its pages is published', function () {
    Page::whereIn('key', ['student-life-library', 'student-life-cafeteria', 'student-life-dorms'])->update(['status' => 'draft']);

    $this->get('/')->assertOk()->assertDontSee('data-home-stack', false);
});

it('moves the carousel role to the content so the list only holds list items', function () {
    Testimonial::factory()->count(2)->create();

    $html = $this->get('/')->assertOk()->assertSee('x-data="carousel"', false)->getContent();

    expect($html)->not->toMatch('/<li[^>]*role="group"/')
        ->and(substr_count($html, 'aria-roledescription="diapositive"'))->toBe(2);
});

it('shows the latest article large and the next two beside it, with the same tiles as the news page', function () {
    foreach (['A', 'B', 'C'] as $i => $letter) {
        NewsPost::factory()->create(['title' => 'Article '.$letter, 'published_at' => now()->subDays(3 - $i)]);
    }

    $html = $this->get('/')->assertOk()->assertSee('data-news="home"', false)->getContent();
    $section = substr($html, strpos($html, 'class="home-news"'));

    expect($section)->toContain('home-news__lead')->and(substr_count($section, 'nws-card__title'))->toBe(2)
        ->and(strpos($section, 'Article C'))->toBeLessThan(strpos($section, 'Article B'));
});

it('turns the next events into tickets, the first one with a countdown from its real date', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-02 12:00:00', config('app.timezone')));
    Event::factory()->create(['title' => 'Premier', 'starts_at' => '2026-11-04 16:30:05', 'ends_at' => '2026-11-04 18:00:00']);
    Event::factory()->create(['title' => 'Second', 'starts_at' => '2026-11-12 09:00:00', 'ends_at' => '2026-11-12 12:00:00']);

    $html = $this->get('/')->assertOk()->assertSee('data-events="home"', false)->getContent();

    expect(substr_count($html, 'data-home-ticket'))->toBe(2)
        ->and(substr_count($html, 'data-evt-countdown'))->toBe(1)
        ->and($html)->toMatch('/data-unit="d">02</')->toMatch('/data-unit="h">04</')->toMatch('/data-unit="m">30</')->toMatch('/data-unit="s">05</')
        ->and($html)->toContain('Dans 2 jours')->toContain('Dans 10 jours');
});

it('leaves out the events and partners sections when there is nothing to show', function () {
    $this->get('/')->assertOk()->assertDontSee('data-home="evt"', false)->assertDontSee('data-home="partners"', false);
});

it('runs the partners as a belt of logos, each one with its name for screen readers', function () {
    Partner::factory()->count(2)->create();

    $html = $this->get('/')->assertOk()->assertSee('data-home="partners"', false)->getContent();

    expect(substr_count($html, 'data-home-partners-track'))->toBe(1);
});

it('lists the resource pages as a table of contents and previews the ones that have an image', function () {
    $cover = MediaAsset::factory()->withImage(800, 600)->create();
    Page::where('key', 'student-life-library')->update(['cover_id' => $cover->id]);

    $html = $this->get('/')->assertOk()->assertSee('data-home-res', false)->getContent();

    expect(substr_count($html, 'data-home-res-item'))->toBe(4)->and(substr_count($html, 'data-home-peek'))->toBe(1);
});

it('closes on the campus photo, with the same calls to action as before', function () {
    $html = $this->get('/')->assertOk()->assertSee('data-home-close-title', false)->getContent();

    expect($html)->toContain('home-close')->toContain('href="'.config('uaz.registration_url').'"')->toContain('/admissions/licence');
});

it('is served in English and Malagasy', function () {
    $this->seed(ContentTranslationsSeeder::class);

    $this->get('/en')->assertOk()->assertSee('<html lang="en"', false)->assertSee('data-home="diff"', false)->assertSee('The UAZ difference');
    $this->get('/mg')->assertOk()->assertSee('<html lang="mg"', false)->assertSee('data-home="dom"', false);
});

it('offers "Soutenir l\'UAZ" next to "Découvrir l\'UAZ" in the hero, so donors find it at once', function () {
    $html = $this->get('/')->assertOk()->getContent();
    $hero = substr($html, strpos($html, 'data-hero-title'), 2600);

    expect($hero)->toContain('Découvrir l&#039;UAZ')->toContain('Soutenir l&#039;UAZ')->toContain('href="'.config('uaz.donation_url').'"')
        ->and($hero)->not->toContain('Planifier votre visite');

    $this->seed(ContentTranslationsSeeder::class);
    $this->get('/en')->assertOk()->assertSee('Support UAZ')->assertDontSee('Plan your visit');
});

it('puts the library catalogue in the top bar of every page, in a new tab', function () {
    foreach (['/' => 'Catalogue de la bibliothèque', '/formations' => 'Catalogue de la bibliothèque', '/en' => 'Library catalog'] as $path => $label) {
        $html = $this->get($path)->assertOk()->getContent();
        $bar = substr($html, 0, strpos($html, '</header>'));

        expect($bar)->toContain('href="https://library.zurcher.edu.mg"')->toContain($label);
    }
});
