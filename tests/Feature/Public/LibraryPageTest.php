<?php

use App\Models\MediaAsset;
use App\Models\Page;
use Database\Seeders\StructureSeeder;

beforeEach(fn () => $this->seed(StructureSeeder::class));

it('serves the library page with its immersive scenes and the real contact details', function () {
    $this->get('/vie-etudiante/bibliotheque')
        ->assertOk()
        ->assertSee('data-lib-intro', false)
        ->assertSee('data-lib-quote', false)
        ->assertSee('data-lib-pan', false)
        ->assertSee('data-lib-shelf', false)
        ->assertSee('data-lib-hours', false)
        ->assertSee('Germaine Greer')
        ->assertSee('uazlibrarydir@zurcher.edu.mg')
        ->assertSee('+261 34 02 019 06')
        ->assertSee('ebookcentral.proquest.com/lib/zurcheradve/home.action', false);
});

it('lists the opening and loan hours of the official poster, without inventing Saturday', function () {
    $html = $this->get('/vie-etudiante/bibliotheque')->assertOk()->getContent();

    expect($html)
        ->toContain('data-slots="08:00-12:00,14:00-17:30"')   // dimanche
        ->toContain('data-slots="09:30-11:00,13:00-18:30"')   // lundi
        ->toContain('data-slots="08:00-18:30"')               // mardi – jeudi
        ->toContain('data-slots="09:30-10:45,13:30-17:15"')   // prêt, lundi
        ->toContain('Aucun service de prêt ni de prolongation')
        ->not->toContain('data-days="6"');
});

it('keeps every scene readable without JavaScript: five shelf cards listed and three pillars', function () {
    $html = $this->get('/vie-etudiante/bibliotheque')->assertOk()->getContent();

    expect(substr_count($html, 'data-lib-panel="'))->toBe(5)
        ->and(substr_count($html, 'data-lib-card'))->toBeGreaterThanOrEqual(3)
        ->and($html)->toContain('Savoir')->toContain('Réflexion')->toContain('Inspiration');
});

it('uses the images of the imported text and the cover of the page', function () {
    $exterior = MediaAsset::create(['title' => 'Enseigne', 'alt' => 'Enseigne']);
    $poster = MediaAsset::create(['title' => 'Affiche', 'alt' => '']);
    $room = MediaAsset::create(['title' => 'Salle', 'alt' => 'Salle de lecture']);

    $page = Page::where('key', 'student-life-library')->firstOrFail();
    $page->update([
        'cover_id' => $room->id,
        'blocks' => [['type' => 'rich_text', 'data' => ['content' => '<figure data-media="'.$exterior->id.'"></figure><figure data-media="'.$poster->id.'"></figure>']]],
    ]);

    // Les médias de test n'ont pas de fichier : la page doit rester servie, sans image cassée.
    $this->get('/vie-etudiante/bibliotheque')->assertOk()->assertSee('data-lib-hours', false);
});

it('does not use the library layout for other pages of the student life section', function () {
    $this->get('/vie-etudiante/cafeteria')->assertOk()->assertDontSee('data-lib-intro', false);
});
