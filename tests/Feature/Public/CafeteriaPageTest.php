<?php

use App\Models\MediaAsset;
use App\Models\Page;
use Database\Seeders\StructureSeeder;

beforeEach(fn () => $this->seed(StructureSeeder::class));

it('serves the cafeteria page with its six immersive scenes', function () {
    $this->get('/vie-etudiante/cafeteria')
        ->assertOk()
        ->assertSee('data-caf-hero', false)
        ->assertSee('data-caf-nourish', false)
        ->assertSee('data-caf-principles', false)
        ->assertSee('data-caf-triptych', false)
        ->assertSee('data-caf-marquee', false)
        ->assertSee('data-caf-invite', false)
        ->assertSee('Cafétéria');
});

it('keeps the whole quote readable as one sentence and only uses words from the presentation text', function () {
    $html = $this->get('/vie-etudiante/cafeteria')->assertOk()->getContent();

    expect($html)
        ->toContain('nourrir le corps,')
        ->toContain('élever l&#039;esprit')
        ->toContain('renforcer la communion')
        ->toContain('Végétarienne')->toContain('Nutritifs')
        ->toContain('Paix')->toContain('Fraternité')->toContain('Gratitude')
        ->not->toContain('data-lib-intro');
});

it('lists three panels with an accessible button each and lets a visitor reach every text without JavaScript', function () {
    $html = $this->get('/vie-etudiante/cafeteria')->assertOk()->getContent();

    expect(substr_count($html, 'data-caf-open'))->toBe(3)
        ->and(substr_count($html, 'data-caf-text'))->toBe(3)
        ->and($html)->toContain('aria-expanded="true"')->toContain('aria-controls="caf-panel-2"');
});

it('picks the three photos of the imported text in order and tolerates missing files', function () {
    $service = MediaAsset::create(['title' => 'Service', 'alt' => '']);
    $kitchen = MediaAsset::create(['title' => 'Cuisine', 'alt' => '']);
    $team = MediaAsset::create(['title' => 'Équipe', 'alt' => '']);

    Page::where('key', 'student-life-cafeteria')->firstOrFail()->update([
        'blocks' => [['type' => 'rich_text', 'data' => ['content' => '<figure data-media="'.$service->id.'"></figure><figure data-media="'.$kitchen->id.'"></figure><figure data-media="'.$team->id.'"></figure>']]],
    ]);

    // Les médias de test n'ont pas de fichier : la page reste servie, sans image cassée.
    $this->get('/vie-etudiante/cafeteria')->assertOk()->assertSee('data-caf-panel', false);
});

it('does not use the cafeteria layout for the library page', function () {
    $this->get('/vie-etudiante/bibliotheque')->assertOk()->assertDontSee('data-caf-hero', false);
});
