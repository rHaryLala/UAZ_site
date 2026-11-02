<?php

use App\Enums\PublicationStatus;
use App\Models\Event;
use App\Support\EventCalendar;
use Carbon\Carbon;
use Database\Seeders\ContentTranslationsSeeder;

beforeEach(function () {
    // Midi, le 2 novembre 2026 (heure de Madagascar) : tous les calculs de dates partent de là.
    Carbon::setTestNow(Carbon::parse('2026-11-02 12:00:00', config('app.timezone')));
});

afterEach(fn () => Carbon::setTestNow());

function showEvent(string $title, string $start, ?string $end = '+3 hours', array $extra = []): Event
{
    $startsAt = Carbon::parse($start, config('app.timezone'));

    return Event::factory()->create($extra + [
        'title' => $title, 'starts_at' => $startsAt,
        'ends_at' => $end === null ? null : (str_starts_with($end, '+') ? $startsAt->copy()->modify($end) : Carbon::parse($end, config('app.timezone'))),
        'is_all_day' => false, 'location' => 'Campus', 'registration_url' => null, 'description' => '<p>Le programme de la journée.</p>',
    ]);
}

it('opens an upcoming event on its ticket, with a countdown from the real date and the way to register and add it to a calendar', function () {
    $event = showEvent('Portes ouvertes', '2026-11-04 16:30:05', '+2 hours', ['registration_url' => 'https://example.test/inscription']);

    $html = $this->get($event->url())->assertOk()
        ->assertSee('data-evs-hero', false)->assertSee('data-evs-ticket', false)->assertSee('data-evs-when', false)
        ->assertSee('Événement · Dans 2 jours')->assertSee('Campus')->assertSee('16h30 – 18h30')
        ->getContent();

    expect($html)->toContain('data-evt-countdown="'.$event->starts_at->toIso8601String().'"')
        ->and($html)->toMatch('/data-unit="d">02</')->toMatch('/data-unit="h">04</')->toMatch('/data-unit="m">30</')->toMatch('/data-unit="s">05</')
        ->and($html)->toContain('href="https://example.test/inscription"')->toContain('rel="noopener noreferrer"')
        ->and($html)->toContain('href="'.$event->url().'/agenda.ics"')->toContain('https://calendar.google.com/calendar/render?')
        ->and($html)->toContain('"@type":"Event"');
});

it('says an event is over, without a countdown, a registration button or calendar links', function () {
    $event = showEvent('Concours passé', '2026-09-12 09:00', '+3 hours', ['registration_url' => 'https://example.test/inscription']);

    $html = $this->get($event->url())->assertOk()->assertSee('Événement terminé')->getContent();

    expect($html)->not->toContain('data-evt-countdown')->not->toContain('example.test/inscription')->not->toContain('agenda.ics')
        ->and($html)->not->toContain('Événement · Événement terminé');
});

it('says an event is happening now instead of counting down', function () {
    $event = showEvent('Conférence', '2026-11-02 10:00', '2026-11-02 17:00');

    $html = $this->get($event->url())->assertOk()->assertSee('Événement · En cours')->getContent();

    expect($html)->not->toContain('data-evt-countdown')->toContain('agenda.ics');
});

it('draws the schedule from the real hours: a track for one day, the days for several, a dot without an end time', function () {
    // 14 h 30 – 17 h : 870 min sur 1440 = 60,42 % ; 150 min = 10,42 %
    $html = $this->get(showEvent('Demi-journée', '2026-11-10 14:30', '+2 hours 30 minutes')->url())->assertOk()->getContent();
    expect($html)->toContain('data-evs-track')->toContain('left: 60.42%; width: 10.42%')->toContain('12h00');

    $this->get(showEvent('Toute la journée', '2026-11-11 00:00', null, ['is_all_day' => true])->url())->assertOk()
        ->assertSee('left: 0; width: 100%', false)->assertSee('Toute la journée');

    $days = $this->get(showEvent('Semaine', '2026-11-16 08:00', '2026-11-19 18:00')->url())->assertOk()->getContent();
    expect(substr_count($days, '<li class="evs-day"'))->toBe(4)->and($days)->not->toContain('data-evs-track');

    $long = $this->get(showEvent('Session longue', '2026-12-01 08:00', '2026-12-20 18:00')->url())->assertOk()->getContent();
    expect($long)->toContain('evs-long')->toContain('20 jours')->not->toContain('data-evs-day');

    $dot = $this->get(showEvent('Sans fin', '2026-11-12 09:00', null)->url())->assertOk()->getContent();
    expect($dot)->toContain('evs-track__dot')->toContain('left: 37.5%');
});

it('offers the three next other events, never the current one, and none when there is no other', function () {
    $event = showEvent('Cet événement', '2026-11-05 10:00');
    $this->get($event->url())->assertOk()->assertDontSee('data-evs-others', false);

    foreach (range(1, 4) as $i) {
        showEvent('Autre '.$i, '2026-11-1'.$i.' 10:00');
    }
    showEvent('Déjà passé', '2026-10-01 10:00');

    $html = $this->get($event->url())->assertOk()->assertSee('Autres rendez-vous')->getContent();
    $others = substr($html, strpos($html, 'data-evs-others'));

    expect(substr_count($others, 'data-evt-stamp'))->toBe(3)
        ->and($others)->toContain('Autre 1')->toContain('Autre 3')->not->toContain('Autre 4')->not->toContain('Cet événement')->not->toContain('Déjà passé');
});

it('skips the description section when there is no text', function () {
    $event = showEvent('Sans texte', '2026-11-05 10:00', '+1 hour', ['description' => '<p> </p>']);

    $this->get($event->url())->assertOk()->assertDontSee('data-evs-about', false);
    $event->update(['description' => '<p>Un vrai texte.</p>']);
    $this->get($event->url())->assertOk()->assertSee('data-evs-about', false)->assertSee('Un vrai texte.');
});

it('serves the calendar file of an event, with real dates only', function () {
    $event = showEvent('Portes ouvertes, accueil; visites', '2026-11-04 14:30', '+2 hours 30 minutes', ['summary' => "Un résumé\nsur deux lignes"]);

    $response = $this->get($event->url().'/agenda.ics')->assertOk();
    $ics = $response->getContent();

    expect($response->headers->get('Content-Type'))->toStartWith('text/calendar')
        ->and($response->headers->get('Content-Disposition'))->toContain($event->slug.'.ics')
        ->and($ics)->toStartWith("BEGIN:VCALENDAR\r\n")->toEndWith("END:VCALENDAR\r\n")
        ->toContain("DTSTART:20261104T113000Z\r\n")->toContain("DTEND:20261104T140000Z\r\n") // 14 h 30 – 17 h à Madagascar (UTC+3)
        ->toContain('SUMMARY:Portes ouvertes\, accueil\; visites')->toContain('DESCRIPTION:Un résumé\nsur deux lignes')
        ->toContain("LOCATION:Campus\r\n")->toContain('URL:'.$event->url());
});

it('writes all-day events as dates and omits an end that nobody entered', function () {
    $allDay = showEvent('Journée', '2026-11-20 00:00', null, ['is_all_day' => true]);
    $open = showEvent('Sans fin', '2026-11-21 09:00', null);

    expect(EventCalendar::ics($allDay))->toContain("DTSTART;VALUE=DATE:20261120\r\n")->toContain("DTEND;VALUE=DATE:20261121\r\n")
        ->and(EventCalendar::ics($open))->toContain("DTSTART:20261121T060000Z\r\n")->not->toContain('DTEND')
        ->and(EventCalendar::googleUrl($allDay))->toContain('dates=20261120%2F20261121');
});

it('folds long calendar lines at 75 octets without cutting a character', function () {
    $event = showEvent(str_repeat('é', 120), '2026-11-04 14:30');

    foreach (explode("\r\n", EventCalendar::ics($event)) as $line) {
        expect(strlen($line))->toBeLessThanOrEqual(75)->and(mb_check_encoding($line, 'UTF-8'))->toBeTrue();
    }
});

it('never serves the calendar of a draft event', function () {
    $draft = showEvent('Brouillon', '2026-11-05 10:00', '+1 hour', ['status' => PublicationStatus::Draft]);

    $this->get($draft->url())->assertNotFound();
    $this->get($draft->url().'/agenda.ics')->assertNotFound();
});

it('is served in English and Malagasy with translated scenes', function () {
    $this->seed(ContentTranslationsSeeder::class);
    $event = showEvent('Portes ouvertes', '2026-11-12 08:00', '+2 hours');
    showEvent('Un autre', '2026-11-20 08:00');

    $this->get('/en/evenements/'.$event->slug)->assertOk()->assertSee('<html lang="en"', false)
        ->assertSee('Event · In 10 days')->assertSee('Add to my calendar')->assertSee('The schedule')->assertSee('Other events');
    $this->get('/en/evenements/'.$event->slug.'/agenda.ics')->assertOk();

    $this->get('/mg/evenements/'.$event->slug)->assertOk()->assertSee('<html lang="mg"', false)->assertSee('Ny fandehanana');
});
