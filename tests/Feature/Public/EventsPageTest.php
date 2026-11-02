<?php

use App\Enums\PublicationStatus;
use App\Models\Event;
use App\Support\EventDial;
use Carbon\Carbon;
use Database\Seeders\ContentTranslationsSeeder;

beforeEach(function () {
    // Midi, le 2 novembre 2026 (heure de Madagascar) : tous les calculs de dates partent de là.
    Carbon::setTestNow(Carbon::parse('2026-11-02 12:00:00', config('app.timezone')));
});

afterEach(fn () => Carbon::setTestNow());

function eventAt(string $title, string $start, array $extra = []): Event
{
    return Event::factory()->create($extra + ['title' => $title, 'starts_at' => $start, 'ends_at' => Carbon::parse($start)->addHours(3)]);
}

it('serves the events page with its scenes', function () {
    eventAt('Portes ouvertes', '2026-11-05 09:00');
    eventAt('Concours passé', '2026-09-12 09:00');

    $this->get('/evenements')->assertOk()
        ->assertSee('data-evt-hero', false)->assertSee('data-evt-upcoming', false)
        ->assertSee('data-evt-past', false)->assertSee('data-evt-more', false)
        ->assertSee('Portes ouvertes')->assertSee('Concours passé');
});

it('counts down to the next event from its real date', function () {
    eventAt('Plus tard', '2026-12-20 09:00');
    $next = eventAt('Portes ouvertes', '2026-11-04 16:30:05');

    $html = $this->get('/evenements')->assertOk()->getContent();

    // De midi le 2 novembre à 16 h 30 min 5 s le 4 novembre : 2 jours, 4 h, 30 min, 5 s. Le prochain est le premier par date.
    expect($html)->toContain('data-evt-countdown="'.$next->starts_at->toIso8601String().'"')
        ->and($html)->toMatch('/data-unit="d">02</')->toMatch('/data-unit="h">04</')
        ->toMatch('/data-unit="m">30</')->toMatch('/data-unit="s">05</')
        ->and($html)->toContain('Prochain rendez-vous');
});

it('says an event is happening now instead of counting down', function () {
    eventAt('Conférence', '2026-11-02 10:00', ['ends_at' => '2026-11-02 17:00']);

    $this->get('/evenements')->assertOk()->assertSee('En cours')->assertDontSee('data-evt-countdown', false);
});

it('labels each upcoming event with how far away it is', function () {
    eventAt('Aujourd\'hui même', '2026-11-02 18:00');
    eventAt('Demain matin', '2026-11-03 08:00');
    eventAt('Dans dix jours', '2026-11-12 08:00');

    $this->get('/evenements')->assertOk()
        ->assertSee('Aujourd&#039;hui', false)->assertSee('Demain')->assertSee('Dans 10 jours');
});

it('shows the dates of a multi-day event, the registration link and the hours', function () {
    eventAt('Semaine culturelle', '2026-11-10 08:00', ['ends_at' => '2026-11-13 18:00', 'registration_url' => 'https://example.test/inscription']);
    eventAt('Journée entière', '2026-11-20 00:00', ['is_all_day' => true, 'ends_at' => null]);

    $html = $this->get('/evenements')->assertOk()->assertSee('au 13 novembre')->assertSee('Toute la journée')->getContent();

    expect($html)->toContain('href="https://example.test/inscription"')->toContain('rel="noopener noreferrer"')
        ->and($html)->toContain('08h00 – 18h00');
});

it('draws the year dial from real dates only', function () {
    $next = eventAt('Prochain', '2026-11-04 10:00');
    eventAt('Dans six mois', '2027-04-02 10:00');
    eventAt('Dans dix-huit mois', '2028-05-02 10:00'); // au-delà de douze mois : pas sur le cadran

    $html = $this->get('/evenements')->assertOk()->getContent();

    // aujourd'hui + le prochain + un autre événement ; l'événement lointain n'est pas dessiné
    expect(substr_count($html, 'data-evt-turn='))->toBe(3)
        ->and($html)->toContain('data-evt-today')->toContain('data-evt-next-dot')->toContain('data-evt-arc')
        ->and($html)->toContain('data-evt-turn="'.EventDial::angle($next->starts_at).'"')
        ->and(substr_count($html, 'data-evt-month'))->toBe(12);
});

it('lists upcoming events in order, and never shows drafts', function () {
    eventAt('Troisième', '2026-12-01 09:00');
    eventAt('Premier', '2026-11-03 09:00');
    eventAt('Deuxième', '2026-11-20 09:00');
    eventAt('Brouillon', '2026-11-04 09:00', ['status' => PublicationStatus::Draft]);

    $html = $this->get('/evenements')->assertOk()->assertDontSee('Brouillon')->getContent();

    expect(substr_count($html, 'data-evt-item'))->toBe(3)
        ->and(strpos($html, 'Premier'))->toBeLessThan(strpos($html, 'Deuxième'))
        ->and(strpos($html, 'Deuxième'))->toBeLessThan(strpos($html, 'Troisième'));
});

it('says so plainly when no event is planned, without a countdown or a line', function () {
    $html = $this->get('/evenements')->assertOk()
        ->assertSee('Aucun événement programmé pour le moment.')
        ->assertDontSee('data-evt-countdown', false)->assertDontSee('data-evt-item', false)->assertDontSee('data-evt-past', false)
        ->getContent();

    // le cadran montre quand même aujourd'hui, sans prochain rendez-vous
    expect($html)->toContain('data-evt-today')->not->toContain('data-evt-next-dot')->not->toContain('data-evt-arc');
});

it('lists past events as an archive, nine per page', function () {
    foreach (range(1, 11) as $i) {
        eventAt('Archive '.$i, Carbon::now()->subDays($i + 2)->toDateTimeString());
    }

    $html = $this->get('/evenements')->assertOk()->assertSee('page=2', false)->getContent();

    expect(substr_count($html, 'data-evt-stamp'))->toBe(9);
});

it('computes the dial angles and arcs', function () {
    expect(EventDial::angle(Carbon::parse('2026-01-01 00:00')))->toBe(0.0)
        ->and(EventDial::angle(Carbon::parse('2026-07-02 12:00')))->toBe(180.0) // 182,5 jours sur 365
        ->and(EventDial::sweep(300, 20))->toBe(80.0)  // vers l'avant, à travers le 1er janvier
        ->and(EventDial::sweep(20, 300))->toBe(280.0)
        ->and(EventDial::arc(100, 100.2, 128))->toBe('')
        ->and(EventDial::arc(0, 90, 128))->toBe('M200 72 A128 128 0 0 1 328 200')
        ->and(EventDial::arc(0, 270, 128))->toContain('A128 128 0 1 1'); // plus d'un demi-tour : grand arc
});

it('is served in English and Malagasy with translated scenes', function () {
    $this->seed(ContentTranslationsSeeder::class);
    eventAt('Portes ouvertes', '2026-11-12 08:00');

    $this->get('/en/evenements')->assertOk()->assertSee('<html lang="en"', false)
        ->assertSee('Next up')->assertSee('In 10 days')->assertSee('Never miss a moment on campus.');

    $this->get('/mg/evenements')->assertOk()->assertSee('<html lang="mg"', false)->assertSee('Ny fihaonana manaraka');
});
