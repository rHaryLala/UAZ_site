<?php

use App\Enums\PublicationStatus;
use App\Models\MediaAsset;
use App\Models\PhotoAlbum;
use Database\Seeders\ContentTranslationsSeeder;

/** Un album publié ; $photos images de démonstration (la première sert de couverture). */
function galleryAlbum(string $title, string $date, int $photos = 0, array $extra = []): PhotoAlbum
{
    $album = PhotoAlbum::factory()->create(['title' => $title, 'taken_on' => $date] + $extra);

    if ($photos > 0) {
        $assets = MediaAsset::factory()->count($photos)->withImage(800, 600)->create();
        $album->update(['cover_id' => $assets->first()->id]);
        $assets->each(fn ($asset, $i) => $album->photos()->attach($asset->id, ['position' => $i]));
    }

    return $album->fresh();
}

it('serves the gallery with its scenes and one frame per album', function () {
    galleryAlbum('Remise des diplômes', '2026-10-05', 2);
    galleryAlbum('Journée sportive', '2025-05-12', 1);

    $html = $this->get('/galerie')->assertOk()
        ->assertSee('data-gal-hero', false)->assertSee('data-gal-expo', false)->assertSee('data-gal-more', false)
        ->assertSee('La vie à l&#039;UAZ en images', false)->getContent();

    expect(substr_count($html, 'data-gal-frame'))->toBe(2)
        ->and($html)->toContain('Remise des diplômes')->toContain('Journée sportive')
        ->and($html)->toContain('data-gal-count="2"')->toContain('data-gal-count="3"'); // 2 albums, 3 photos : comptés, jamais inventés
});

it('builds the hero wall only from the covers of the albums, and only with three or more', function () {
    galleryAlbum('Un', '2026-01-01', 1);
    galleryAlbum('Deux', '2026-02-01', 1);
    $this->get('/galerie')->assertOk()->assertDontSee('data-gal-col', false);

    galleryAlbum('Trois', '2026-03-01', 1);
    $html = $this->get('/galerie')->assertOk()->getContent();

    expect(substr_count($html, 'data-gal-col'))->toBe(3);
});

it('draws each frame at the real ratio of its cover and rotates the hanging rhythm', function () {
    foreach (range(1, 5) as $i) {
        galleryAlbum('Album '.$i, '2026-0'.$i.'-01', 1);
    }

    $html = $this->get('/galerie')->assertOk()->getContent();

    // Les images de démonstration sont en 800 × 600 : 1,333. Le rythme d'accrochage tourne sur quatre positions.
    expect($html)->toContain('--ratio: 1.333')
        ->and(preg_match_all('/data-slot="(\d)"/', $html, $found))->toBe(5)
        ->and($found[1])->toBe(['0', '1', '2', '3', '0']);
});

it('offers the year filter only with several years and a single page', function () {
    galleryAlbum('Vieux', '2024-03-01');
    $this->get('/galerie')->assertOk()->assertDontSee('data-gal-filter', false);

    galleryAlbum('Récent', '2026-03-01');
    $html = $this->get('/galerie')->assertOk()->assertSee('data-gal-filter', false)->getContent();

    expect($html)->toContain('data-gal-year="2026"')->toContain('data-gal-year="2024"')
        ->and(strpos($html, 'data-gal-year="2026"'))->toBeLessThan(strpos($html, 'data-gal-year="2024"')) // plus récente d'abord
        ->and($html)->toContain('data-gal-filter hidden'); // masqué tant que le JavaScript ne l'a pas activé
});

it('drops the year filter when the albums span several pages', function () {
    foreach (range(1, 25) as $i) {
        galleryAlbum('Album numéro '.$i, ($i % 2 ? '2025' : '2026').'-01-'.str_pad((string) (($i % 27) + 1), 2, '0', STR_PAD_LEFT));
    }

    $this->get('/galerie')->assertOk()->assertDontSee('data-gal-filter', false)->assertSee('page=2', false);
});

it('never shows unpublished albums and says so plainly when there is none', function () {
    galleryAlbum('Brouillon', '2026-01-01', 0, ['status' => PublicationStatus::Draft]);

    $this->get('/galerie')->assertOk()
        ->assertSee('Aucun album pour le moment.')->assertSee('Les albums de la vie du campus seront bientôt publiés ici.')
        ->assertDontSee('Brouillon')->assertDontSee('data-gal-frame', false)->assertDontSee('data-gal-count', false);
});

it('hangs the photos of an album at their own ratio, keeps captions and the lightbox', function () {
    $album = galleryAlbum('Remise des diplômes', '2026-10-05', 3);
    $album->photos->first()->update(['caption' => 'La procession', 'alt' => 'Les diplômés en procession']);

    $html = $this->get($album->url())->assertOk()
        ->assertSee('data-gal-cover', false)->assertSee('x-data="lightbox"', false)->assertSee('La procession')
        ->getContent();

    expect(substr_count($html, 'data-lightbox-item'))->toBe(3)
        ->and(substr_count($html, 'data-gal-frame'))->toBe(3)
        ->and($html)->toContain('data-alt="Les diplômés en procession"')
        ->and($html)->toContain('<dialog');
});

it('says so plainly when an album has no photo yet', function () {
    $album = galleryAlbum('Vide', '2026-01-01');

    $this->get($album->url())->assertOk()->assertSee('Cet album ne contient pas encore de photos.')->assertDontSee('data-gal-frame', false);
});

it('is served in English and Malagasy with translated scenes', function () {
    $this->seed(ContentTranslationsSeeder::class);
    galleryAlbum('Remise des diplômes', '2026-10-05', 1);
    galleryAlbum('Journée sportive', '2025-05-12', 1);

    $this->get('/en/galerie')->assertOk()->assertSee('<html lang="en"', false)
        ->assertSee('An exhibition, one album after another')->assertSee('>All<', false)->assertSee('albums');

    $this->get('/mg/galerie')->assertOk()->assertSee('<html lang="mg"', false)->assertSee('Fampirantiana iray');
});
