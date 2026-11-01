<?php

use App\Models\MediaAsset;
use App\Models\Page;
use Database\Seeders\StructureSeeder;

beforeEach(fn () => $this->seed(StructureSeeder::class));

it('serves the dormitories page with its immersive scenes', function () {
    $this->get('/vie-etudiante/dortoirs')
        ->assertOk()
        ->assertSee('data-dor-hero', false)
        ->assertSee('data-dor-statement', false)
        ->assertSee('data-dor-houses', false)
        ->assertSee('data-dor-plan', false)
        ->assertSee('data-dor-night', false)
        ->assertSee('Votre second foyer');
});

it('keeps the figures and the room plan faithful to the presentation text', function () {
    $html = $this->get('/vie-etudiante/dortoirs')->assertOk()->getContent();

    expect($html)
        ->toContain('data-dor-count="80"')->toContain('data-dor-count="68"')
        ->toContain('3 à 4 étudiants')->toContain('4 à 5 étudiantes')
        ->toContain('Maison du Précepteur')
        ->toContain('schéma illustratif')
        ->and(substr_count($html, 'data-dor-cell'))->toBe(12)
        // 8 chambres de 4 étudiants, 1 réception, 1 salle à manger, 2 chambres du précepteur
        ->and(substr_count($html, 'dor-cell--room'))->toBe(8)
        ->and(substr_count($html, 'dor-cell--hall'))->toBe(2)
        ->and(substr_count($html, 'dor-cell--tutor'))->toBe(2);
});

it('links the project to support and reads the Facebook links from the imported text', function () {
    Page::where('key', 'student-life-dorms')->firstOrFail()->update([
        'blocks' => [['type' => 'rich_text', 'data' => ['content' => '<p><a href="https://www.facebook.com/share/p/abc/">Open House</a><a href="https://www.facebook.com/share/p/def/">MJA</a></p>']]],
    ]);

    $html = $this->get('/vie-etudiante/dortoirs')->assertOk()->getContent();

    expect($html)->toContain('/soutenir/un-grand-dortoir-pour-les-garcons')
        ->toContain('Open House')->toContain('MJA')
        ->toContain('https://www.facebook.com/share/p/');
});

it('hides the extra links section when the imported text has none, and tolerates missing photos', function () {
    $media = MediaAsset::create(['title' => 'Photo', 'alt' => '']);
    Page::where('key', 'student-life-dorms')->firstOrFail()->update([
        'blocks' => [['type' => 'rich_text', 'data' => ['content' => '<figure data-media="'.$media->id.'"></figure>']]],
    ]);

    $html = $this->get('/vie-etudiante/dortoirs')->assertOk()->getContent();

    expect($html)->not->toContain('class="dor-link"')->toContain('data-dor-plan');
});

it('does not use the dormitories layout for the cafeteria', function () {
    $this->get('/vie-etudiante/cafeteria')->assertOk()->assertDontSee('data-dor-hero', false);
});

it('shows the room photos in the hero and on the wall when the text carries nine photos, each with a one-word caption', function () {
    $ids = collect(range(1, 9))->map(fn ($i) => MediaAsset::create(['title' => "Photo $i", 'alt' => "Photo $i"])->id);

    Page::where('key', 'student-life-dorms')->firstOrFail()->update([
        'blocks' => [['type' => 'rich_text', 'data' => ['content' => $ids->map(fn ($id) => '<figure data-media="'.$id.'"></figure>')->implode('')]]],
    ]);

    $html = $this->get('/vie-etudiante/dortoirs')->assertOk()->getContent();

    expect(substr_count($html, 'data-dor-polaroid'))->toBe(5)
        ->and($html)->toContain('Camaraderie')->toContain('Apprentissage')->toContain('Concentration')->toContain('Amitié')->toContain('Croissance personnelle');
});

it('has no photo wall when the text only carries the three outdoor photos', function () {
    $ids = collect(range(1, 3))->map(fn ($i) => MediaAsset::create(['title' => "Photo $i", 'alt' => "Photo $i"])->id);

    Page::where('key', 'student-life-dorms')->firstOrFail()->update([
        'blocks' => [['type' => 'rich_text', 'data' => ['content' => $ids->map(fn ($id) => '<figure data-media="'.$id.'"></figure>')->implode('')]]],
    ]);

    $this->get('/vie-etudiante/dortoirs')->assertOk()->assertDontSee('data-dor-wall', false)->assertSee('data-dor-plan', false);
});
