<?php

use App\Models\MediaAsset;
use App\Models\NewsCategory;
use App\Models\NewsPost;
use Database\Seeders\ContentTranslationsSeeder;

/** Une actualité publiée à la date donnée (la plus récente d'abord dans la liste). */
function newsPost(string $title, string $date, array $extra = []): NewsPost
{
    return NewsPost::factory()->create($extra + ['title' => $title, 'published_at' => $date.' 10:00:00']);
}

function newsWithCover(string $title, string $date, array $extra = []): NewsPost
{
    $asset = MediaAsset::factory()->withImage(800, 600)->create();

    return newsPost($title, $date, ['cover_id' => $asset->id] + $extra);
}

it('serves the news list with its scenes, the latest article as the top story', function () {
    newsWithCover('Premier article', '2026-10-01');
    newsPost('Deuxième article', '2026-10-05');
    newsPost('Troisième article', '2026-10-09');

    $html = $this->get('/actualites')->assertOk()
        ->assertSee('data-nws-hero', false)->assertSee('data-nws-ticker', false)->assertSee('data-nws-wall', false)->assertSee('data-nws-more', false)
        ->assertSee('À la une')->assertSee('Dernière publication : 9 octobre 2026')->getContent();

    // « À la une » est le plus récent ; les deux autres sont dans le fil.
    expect(substr_count($html, 'data-nws-lead'))->toBe(1)
        ->and(substr_count($html, 'data-nws-cell'))->toBe(2)
        ->and(strpos($html, 'nws-lead__title'))->toBeLessThan(strpos($html, 'Deuxième article', strpos($html, 'data-nws-wall')))
        ->and($html)->toMatch('/nws-lead__title"><a[^>]*><span class="nws-mark">Troisième article</');
});

it('hangs the other articles on a five-step rhythm and numbers no invented data', function () {
    foreach (range(1, 7) as $i) {
        newsPost('Article '.$i, '2026-09-0'.$i);
    }

    $html = $this->get('/actualites')->assertOk()->getContent();

    preg_match_all('/data-nws-cell data-slot="(\d)"/', $html, $found);
    expect($found[1])->toBe(['0', '1', '2', '3', '4', '0']);
});

it('keeps the top story on the first page only', function () {
    foreach (range(1, 11) as $i) {
        newsPost('Article '.str_pad((string) $i, 2, '0', STR_PAD_LEFT), '2026-08-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT));
    }

    $first = $this->get('/actualites')->assertOk()->assertSee('page=2', false)->getContent();
    $second = $this->get('/actualites?page=2')->assertOk()->getContent();

    // page 1 : une à la une + 8 dans le fil (9 articles) ; page 2 : 2 articles, tous dans le fil, titres de niveau 2
    expect(substr_count($first, 'data-nws-lead'))->toBe(1)->and(substr_count($first, 'data-nws-cell'))->toBe(8)
        ->and(substr_count($second, 'data-nws-lead'))->toBe(0)->and(substr_count($second, 'data-nws-cell'))->toBe(2)
        ->and($second)->toContain('<h2 class="nws-card__title">');
});

it('runs a ticker of the six latest headlines, only when there are at least three', function () {
    newsPost('Un', '2026-10-01');
    newsPost('Deux', '2026-10-02');
    $this->get('/actualites')->assertOk()->assertDontSee('data-nws-ticker', false);

    foreach (range(3, 9) as $i) {
        newsPost('Titre '.$i, '2026-10-0'.$i);
    }
    $html = $this->get('/actualites')->assertOk()->getContent();

    // six titres, répétés deux fois pour que le ruban boucle sans coupure
    expect(substr_count($html, 'data-nws-ticker-track'))->toBe(1)->and(substr_count($html, '<i></i>'))->toBe(12);
});

it('filters by category and shows the tabs only when there are several', function () {
    $this->get('/actualites')->assertOk()->assertDontSee('nws-tabs', false);

    $vie = NewsCategory::factory()->create(['name' => 'Vie du campus', 'slug' => 'vie-du-campus']);
    $formation = NewsCategory::factory()->create(['name' => 'Formation', 'slug' => 'formation']);
    newsPost('Sortie au parc', '2026-10-03', ['news_category_id' => $vie->id]);
    newsPost('Nouveau master', '2026-10-04', ['news_category_id' => $formation->id]);

    $this->get('/actualites')->assertOk()->assertSee('nws-tabs', false)->assertSee('Vie du campus')->assertSee('Sortie au parc')->assertSee('Nouveau master');

    $this->get('/actualites?categorie=formation')->assertOk()
        ->assertSee('Nouveau master')->assertDontSee('Sortie au parc')->assertSee('aria-current="true"', false);
});

it('never shows drafts or scheduled articles, and says so plainly when there is none', function () {
    NewsPost::factory()->draft()->create(['title' => 'Brouillon']);
    NewsPost::factory()->scheduled()->create(['title' => 'Programmé']);

    $this->get('/actualites')->assertOk()
        ->assertSee('Aucune actualité pour le moment.')->assertDontSee('Brouillon')->assertDontSee('Programmé')
        ->assertDontSee('data-nws-lead', false)->assertDontSee('data-nws-ticker', false)->assertDontSee('Dernière publication');
});

it('opens an article on its cover, with progress bar, share links and a reading time computed from the text', function () {
    $words = implode(' ', array_fill(0, 450, 'mot'));
    $post = newsWithCover('Inauguration du centre', '2026-09-23', ['body' => '<p>'.$words.'</p>', 'excerpt' => 'Un chapeau.']);

    $html = $this->get($post->url())->assertOk()
        ->assertSee('data-nws-progress', false)->assertSee('data-nws-cover', false)->assertSee('has-cover on-dark', false)
        ->assertSee('Un chapeau.')->assertSee('3 min de lecture')
        ->getContent();

    expect($html)->toContain('https://www.facebook.com/sharer/sharer.php?u='.urlencode($post->url()))
        ->and($html)->toContain('https://wa.me/?text='.urlencode('Inauguration du centre '.$post->url()))
        ->and($html)->toContain('data-nws-copy="'.$post->url().'"')->toContain('rel="noopener noreferrer"');
});

it('opens an article without a cover on paper, and counts at least one minute', function () {
    $post = newsPost('Court message', '2026-09-23', ['body' => '<p>Quelques mots.</p>']);

    $this->get($post->url())->assertOk()
        ->assertDontSee('data-nws-cover', false)->assertDontSee('has-cover', false)->assertSee('1 min de lecture');
});

it('links each article to the one before and the one after it', function () {
    newsPost('Plus ancien', '2026-08-01');
    $middle = newsPost('Au milieu', '2026-09-01');
    newsPost('Plus récent', '2026-10-01');

    $html = $this->get($middle->url())->assertOk()->assertSee('Article précédent')->assertSee('Article suivant')->getContent();
    expect($html)->toMatch('/is-older"[^>]*>.*Plus ancien/s')->toMatch('/is-newer"[^>]*>.*Plus récent/s');

    // le plus récent n'a pas de « suivant », le plus ancien pas de « précédent »
    $this->get(NewsPost::where('title', 'Plus récent')->first()->url())->assertOk()->assertSee('Article précédent')->assertDontSee('Article suivant');
    $this->get(NewsPost::where('title', 'Plus ancien')->first()->url())->assertOk()->assertSee('Article suivant')->assertDontSee('Article précédent');
});

it('offers three other articles to read, never the current one', function () {
    $post = newsPost('Article lu', '2026-09-01');
    foreach (range(1, 4) as $i) {
        newsPost('Autre '.$i, '2026-10-0'.$i);
    }

    $html = $this->get($post->url())->assertOk()->assertSee('À lire aussi')->getContent();
    $related = substr($html, strpos($html, 'nws-related'));

    expect(substr_count($related, 'data-nws-cell'))->toBe(3)->and($related)->not->toContain('Article lu');
});

it('is served in English and Malagasy with translated scenes', function () {
    $this->seed(ContentTranslationsSeeder::class);
    $post = newsPost('Un article', '2026-10-01', ['body' => '<p>Texte.</p>']);
    newsPost('Un autre', '2026-10-02');

    $this->get('/en/actualites')->assertOk()->assertSee('<html lang="en"', false)->assertSee('Top story')->assertSee('Last published: 2 October 2026')->assertSee('Get the news from UAZ');
    $this->get('/en/actualites/'.$post->slug)->assertOk()->assertSee('1 min read')->assertSee('Share')->assertSee('Next article');
    $this->get('/mg/actualites')->assertOk()->assertSee('<html lang="mg"', false)->assertSee('Lohalaharana');
});
