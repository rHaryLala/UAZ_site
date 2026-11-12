<?php

use App\Models\CafeteriaMenu;
use Database\Seeders\StructureSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-11-12 09:00:00'); // jeudi ; figée avant les graines : les pages sont publiées à cette heure-là
    $this->seed(StructureSeeder::class);
    CafeteriaMenu::query()->delete(); // les trois semaines installées par la migration ne servent pas ici
});

afterEach(fn () => Carbon::setTestNow());

it('shows one table per week with the meals of every day', function () {
    CafeteriaMenu::factory()->startingOn('2026-11-10')->create(['meals' => collect(range(0, 6))->map(fn ($d) => [
        'morning' => ["Matin du jour $d"], 'noon' => ["Midi du jour $d", 'Riz cantonnais'], 'evening' => ["Soir du jour $d"],
    ])->all()]);
    CafeteriaMenu::factory()->startingOn('2026-11-17')->create();

    $html = $this->get('/vie-etudiante/cafeteria')->assertOk()->assertSee('id="menu"', false)->getContent();

    expect(substr_count($html, 'class="caf-table"'))->toBe(2)
        ->and($html)->toContain('Le menu de la semaine')
        ->toContain('Semaine du 10 au 16 novembre 2026')->toContain('Semaine du 17 au 23 novembre 2026')
        ->toContain('Matin du jour 0')->toContain('Soir du jour 6')->toContain('Riz cantonnais')
        ->toContain('>mardi<')->toContain('>lundi<')
        ->toContain('Voir le menu de la semaine');
});

it('offers week tabs when several weeks are shown, and none for a single week', function () {
    CafeteriaMenu::factory()->startingOn('2026-11-10')->create();
    CafeteriaMenu::factory()->startingOn('2026-11-17')->create();

    $many = $this->get('/vie-etudiante/cafeteria')->assertOk()->getContent();
    expect(substr_count($many, 'role="tab"'))->toBe(2)->and($many)->toContain('role="tablist"')->toContain('En cours');

    CafeteriaMenu::query()->whereDate('starts_on', '2026-11-17')->delete();

    $one = $this->get('/vie-etudiante/cafeteria')->assertOk()->getContent();
    expect($one)->not->toContain('role="tab"')->not->toContain('role="tablist"')->toContain('class="caf-table"');
});

it('marks today and describes the table for screen readers', function () {
    CafeteriaMenu::factory()->startingOn('2026-11-10')->create();

    $html = $this->get('/vie-etudiante/cafeteria')->assertOk()->getContent();

    expect($html)->toContain('aria-current="date"')->toContain('Aujourd&#039;hui')
        ->and(substr_count($html, 'scope="col"'))->toBe(7)
        ->and(substr_count($html, 'scope="row"'))->toBe(3)
        ->and($html)->toContain('<caption class="sr-only">');
});

it('hides the section and the shortcut when no week is current or upcoming', function () {
    CafeteriaMenu::factory()->startingOn('2026-11-02')->create(); // terminée le 8 novembre
    CafeteriaMenu::factory()->startingOn('2026-11-17')->inactive()->create();

    $this->get('/vie-etudiante/cafeteria')->assertOk()
        ->assertDontSee('id="menu"', false)->assertDontSee('Voir le menu de la semaine')->assertDontSee('data-caf-menu', false)
        ->assertSee('data-caf-triptych', false); // le reste de la page est inchangé
});

it('does not show a week with no dish at all', function () {
    CafeteriaMenu::factory()->startingOn('2026-11-10')->create(['meals' => []]);

    $this->get('/vie-etudiante/cafeteria')->assertOk()->assertDontSee('id="menu"', false);
});

it('leaves out the empty meals with a dash and keeps the note under the table', function () {
    CafeteriaMenu::factory()->startingOn('2026-11-10')->create([
        'meals' => [['morning' => ['Riz'], 'noon' => [], 'evening' => []]],
        'note' => 'Menu susceptible de changer.',
    ]);

    $html = $this->get('/vie-etudiante/cafeteria')->assertOk()->getContent();

    expect($html)->toContain('caf-dishes__none')->toContain('Non renseigné')->toContain('Menu susceptible de changer.');
});

it('escapes what an editor types in a dish or a note', function () {
    CafeteriaMenu::factory()->startingOn('2026-11-10')->create([
        'meals' => [['morning' => ['<script>alert(1)</script>'], 'noon' => ['Riz'], 'evening' => []]],
        'note' => '<b>gras</b>',
    ]);

    $html = $this->get('/vie-etudiante/cafeteria')->assertOk()->getContent();

    expect($html)->not->toContain('<script>alert(1)</script>')->toContain('&lt;script&gt;alert(1)&lt;/script&gt;')->not->toContain('<b>gras</b>');
});

it('translates the headings and dates of the table, not the dishes', function () {
    CafeteriaMenu::factory()->startingOn('2026-11-10')->create(['meals' => [['morning' => ['Laisoa saosy'], 'noon' => ['Haricot blanc'], 'evening' => ['Nem']]]]);

    $en = $this->get('/en/vie-etudiante/cafeteria')->assertOk()->getContent();
    expect($en)->toContain('This week&#039;s menu')->toContain('Week of 10 to 16 November 2026')->toContain('>Tuesday<')->toContain('>Morning<')
        ->toContain('Laisoa saosy')->toContain('Haricot blanc');

    $mg = $this->get('/mg/vie-etudiante/cafeteria')->assertOk()->getContent();
    expect($mg)->toContain('Ny sakafo isan-kerinandro')->toContain('>Maraina<')->toContain('Laisoa saosy');
});
