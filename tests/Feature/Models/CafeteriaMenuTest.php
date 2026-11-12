<?php

use App\Models\CafeteriaMenu;
use App\Support\CafeteriaMenuData;
use App\Support\Locales;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

// La migration a installé trois semaines : ces tests partent d'une table vide (ce que la migration installe est vérifié dans CafeteriaMenuMigrationTest).
beforeEach(fn () => CafeteriaMenu::query()->delete());

afterEach(function () {
    Carbon::setTestNow();
    Locales::reset();
});

it('supplies three weeks of seven days with every meal filled, from Tuesday 10 November 2026', function () {
    $weeks = CafeteriaMenuData::weeks();

    expect($weeks)->toHaveCount(3)
        ->and(collect($weeks)->pluck('starts_on')->all())->toBe(['2026-11-10', '2026-11-17', '2026-11-24'])
        ->and(collect($weeks)->every(fn ($week) => Carbon::parse($week['starts_on'])->isTuesday()))->toBeTrue();

    foreach ($weeks as $week) {
        $days = (new CafeteriaMenu(['starts_on' => $week['starts_on'], 'meals' => $week['meals']]))->days();

        expect($days)->toHaveCount(7)
            ->and(collect($days)->every(fn ($day) => collect($day['meals'])->every(fn ($dishes) => count($dishes) >= 2)))->toBeTrue();
    }

    $first = (new CafeteriaMenu(['starts_on' => '2026-11-10', 'meals' => $weeks[0]['meals']]))->days();
    $last = (new CafeteriaMenu(['starts_on' => '2026-11-24', 'meals' => $weeks[2]['meals']]))->days();

    expect($first[0]['meals']['morning'])->toContain('Potatoes and boiled leafy greens')->toContain('Hot chocolate')
        ->and($first[6]['meals']['evening'])->toBe(['Omelette au poireau', 'Bouillon d\'aubergine amère', 'Banane'])
        ->and($last[0]['meals']['noon'])->toContain('Chinese fried rice')
        ->and($last[6]['meals']['evening'])->toContain('Crêpe au chocolat');
});

it('never replaces a week an editor already saved when the supplied data is installed again', function () {
    foreach (CafeteriaMenuData::weeks() as $week) {
        CafeteriaMenu::query()->firstOrCreate(['starts_on' => $week['starts_on']], ['meals' => $week['meals'], 'is_active' => true]);
    }
    CafeteriaMenu::whereDate('starts_on', '2026-11-10')->firstOrFail()->update(['meals' => [['morning' => ['Plat modifié'], 'noon' => [], 'evening' => []]]]);

    foreach (CafeteriaMenuData::weeks() as $week) {
        CafeteriaMenu::query()->firstOrCreate(['starts_on' => $week['starts_on']], ['meals' => $week['meals'], 'is_active' => true]);
    }

    expect(CafeteriaMenu::count())->toBe(3)
        ->and(CafeteriaMenu::whereDate('starts_on', '2026-11-10')->first()->days()[0]['meals']['morning'])->toBe(['Plat modifié']);
});

it('lists the seven days from the first day, with their names and dates in the language of the site', function () {
    $menu = CafeteriaMenu::factory()->startingOn('2026-11-10')->create();

    $days = $menu->days();
    expect(collect($days)->pluck('name')->all())->toBe(['mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche', 'lundi'])
        ->and($days[0]['short'])->toBe('10 nov.')->and($days[6]['date']->toDateString())->toBe('2026-11-16');

    Locales::apply('en');
    expect(collect($menu->days())->pluck('name')->first())->toBe('Tuesday');
});

it('writes the week range with the month only once when the week stays in one month', function () {
    $same = CafeteriaMenu::factory()->startingOn('2026-11-10')->create();
    $end = CafeteriaMenu::factory()->startingOn('2026-11-24')->create();
    $overlap = CafeteriaMenu::factory()->startingOn('2026-12-29')->create();

    expect($same->rangeLabel())->toBe('Semaine du 10 au 16 novembre 2026')
        ->and($end->rangeLabel())->toBe('Semaine du 24 au 30 novembre 2026')
        ->and($overlap->rangeLabel())->toBe('Semaine du 29 décembre au 4 janvier 2027')
        ->and($same->shortRange())->toBe('10 – 16 nov.')
        ->and($overlap->shortRange())->toBe('29 déc. – 4 janv.');

    Locales::apply('en');
    expect($same->rangeLabel())->toBe('Week of 10 to 16 November 2026');
});

it('trims the dishes and ignores blank lines and missing days', function () {
    $menu = CafeteriaMenu::factory()->startingOn('2026-11-10')->create(['meals' => [['morning' => ['  Riz  ', '', '   ', 'Thé'], 'noon' => 'Soupe']]]);

    $days = $menu->days();
    expect($days[0]['meals'])->toBe(['morning' => ['Riz', 'Thé'], 'noon' => ['Soupe'], 'evening' => []])
        ->and($days[3]['meals'])->toBe(['morning' => [], 'noon' => [], 'evening' => []]);
});

it('knows when a week has no dish at all', function () {
    expect(CafeteriaMenu::factory()->create(['meals' => []])->isEmpty())->toBeTrue()
        ->and(CafeteriaMenu::factory()->create(['meals' => [['morning' => [' '], 'noon' => [], 'evening' => []]]])->isEmpty())->toBeTrue()
        ->and(CafeteriaMenu::factory()->create()->isEmpty())->toBeFalse();
});

it('shows the current week until its last day and the weeks to come, but not past or inactive weeks', function () {
    Carbon::setTestNow('2026-12-02 10:00:00'); // mercredi

    CafeteriaMenu::factory()->startingOn('2026-11-17')->create(); // terminée le 23 nov.
    CafeteriaMenu::factory()->startingOn('2026-11-24')->create(); // terminée le 30 nov.
    $current = CafeteriaMenu::factory()->startingOn('2026-12-01')->create(); // en cours (1er au 7 déc.)
    $next = CafeteriaMenu::factory()->startingOn('2026-12-08')->create();
    CafeteriaMenu::factory()->startingOn('2026-12-15')->inactive()->create();

    expect(CafeteriaMenu::currentAndUpcoming()->pluck('id')->all())->toBe([$current->id, $next->id])
        ->and($current->isCurrent())->toBeTrue()->and($next->isCurrent())->toBeFalse();

    // Le dernier jour d'une semaine, elle est encore affichée ; le lendemain, plus.
    Carbon::setTestNow('2026-12-07 23:00:00');
    expect(CafeteriaMenu::currentAndUpcoming()->pluck('id')->first())->toBe($current->id);

    Carbon::setTestNow('2026-12-08 00:30:00');
    expect(CafeteriaMenu::currentAndUpcoming()->pluck('id')->first())->toBe($next->id);
});

it('marks today in the week', function () {
    Carbon::setTestNow('2026-11-12 09:00:00'); // jeudi
    $days = CafeteriaMenu::factory()->startingOn('2026-11-10')->create()->days();

    expect(collect($days)->pluck('today')->all())->toBe([false, false, true, false, false, false, false]);
});

it('does not allow two menus for the same week', function () {
    CafeteriaMenu::factory()->startingOn('2026-12-01')->create();

    expect(fn () => CafeteriaMenu::factory()->startingOn('2026-12-01')->create())->toThrow(QueryException::class);
});
