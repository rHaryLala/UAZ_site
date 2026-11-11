<?php

use App\Models\Project;
use Database\Seeders\ContentTranslationsSeeder;
use Database\Seeders\StructureSeeder;

beforeEach(function () {
    $this->seed(StructureSeeder::class);
    config(['uaz.donation_url' => null]);
});

it('serves the Soutenir page with its five scenes', function () {
    $this->get('/soutenir')->assertOk()
        ->assertSee('data-sup-hero', false)
        ->assertSee('data-sup-statement', false)
        ->assertSee('data-sup-projects', false)
        ->assertSee('data-sup-invite', false)
        ->assertSee('Soutenir l&#039;UAZ', false);
});

it('builds the wall of the first scene from decorative bricks only', function () {
    $html = $this->get('/soutenir')->assertOk()->getContent();

    // 12 colonnes de 2 à 10 briques : le mur est un décor, aucun chiffre n'y est attaché.
    expect(substr_count($html, 'data-sup-brick'))->toBe(69)
        ->and($html)->toContain('data-sup-wall');
});

it('draws amount bars proportional to the published budgets, without inventing any total', function () {
    $html = $this->get('/soutenir')->assertOk()->getContent();

    expect($html)->toContain('data-sup-bars')
        ->and($html)->toContain('--w: 100%')->toContain('--w: 58.2%')
        ->and($html)->toContain('275 000 €')->toContain('160 000 €')
        ->not->toContain('435 000')->not->toContain('déjà réuni')->not->toContain('collecté');
});

it('draws no bars with a single project, or with budgets in different units', function () {
    Project::where('slug', 'des-logements-pour-le-personnel')->update(['is_active' => false]);
    $this->get('/soutenir')->assertOk()->assertDontSee('data-sup-bars', false)->assertSee('275 000 €');

    Project::where('slug', 'des-logements-pour-le-personnel')->update(['is_active' => true, 'budget' => '160 000 $']);
    $this->get('/soutenir')->assertOk()->assertDontSee('data-sup-bars', false);

    Project::where('slug', 'des-logements-pour-le-personnel')->update(['budget' => null]);
    $this->get('/soutenir')->assertOk()->assertDontSee('data-sup-bars', false);
});

it('draws a plan for projects without a photo and counts their budget', function () {
    $html = $this->get('/soutenir')->assertOk()->getContent();

    expect(substr_count($html, 'data-sup-plan'))->toBe(2)
        ->and(substr_count($html, 'data-sup-amount'))->toBe(2)
        ->and(substr_count($html, 'data-sup-sheet'))->toBe(2);
});

it('says so plainly when there is no project to present', function () {
    Project::query()->update(['is_active' => false]);

    $this->get('/soutenir')->assertOk()
        ->assertSee('Les projets à soutenir seront bientôt présentés ici.')
        ->assertDontSee('data-sup-bars', false)->assertDontSee('data-sup-sheet', false);
});

it('keeps the honest state of online donation in the hero, the doors and the closing invitation', function () {
    $this->get('/soutenir')->assertOk()
        ->assertSee('Bientôt disponible')->assertSee('Nous contacter')
        ->assertSee('/contact?sujet=Soutenir%20l%27UAZ', false)
        ->assertDontSee('Faire un don');
});

it('redirects to the donation site as soon as its address is set', function () {
    config(['uaz.donation_url' => 'https://pay.example.test/uaz']);

    $this->get('/soutenir')->assertRedirect('https://pay.example.test/uaz');
});

it('lists the donation contacts of the call for donations', function () {
    $this->get('/soutenir')->assertOk()
        ->assertSee('tel:0380773118', false)->assertSee('038 07 731 18')
        ->assertSee('mailto:uaz.rector@zurcher.edu.mg', false);
});

it('is served in English and Malagasy with translated scenes and amounts', function () {
    $this->seed(ContentTranslationsSeeder::class);

    $this->get('/en/soutenir')->assertOk()
        ->assertSee('<html lang="en"', false)
        ->assertSee('Every contribution helps move UAZ&#039;s projects forward.', false)
        ->assertSee('A large dormitory for men')
        ->assertSee('€275,000')->assertSee('€160,000')
        ->assertSee('Coming soon');

    $this->get('/mg/soutenir')->assertOk()->assertSee('<html lang="mg"', false)->assertSee('Antso fanomezana');
});
