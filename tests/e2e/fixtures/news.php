<?php

/*
 * Actualités d'essai pour tests/e2e/news.mjs : « up » crée une rubrique « E2E Rubrique » et trois articles « E2E … » (les plus récents,
 * dont un long texte avec intertitres, citation et photo), « down » les supprime. Usage : php tests/e2e/fixtures/news.php up|down
 */

use App\Enums\PublicationStatus;
use App\Models\MediaAsset;
use App\Models\NewsCategory;
use App\Models\NewsPost;

require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

NewsPost::where('title', 'like', 'E2E %')->get()->each(fn ($post) => $post->forceDelete());
NewsCategory::where('name', 'E2E Rubrique')->get()->each(fn ($category) => $category->forceDelete());

if (($argv[1] ?? '') !== 'up') {
    echo "actualités d'essai supprimées\n";

    return;
}

$category = NewsCategory::create(['name' => 'E2E Rubrique', 'slug' => 'e2e-rubrique']);
$ids = MediaAsset::query()->whereHas('media', fn ($q) => $q->where('mime_type', 'like', 'image/%'))->orderBy('id')->limit(10)->pluck('id')->values();
$paragraph = '<p>'.implode(' ', array_fill(0, 60, 'Un texte d\'essai assez long pour occuper plusieurs lignes de lecture.')).'</p>';
$body = $paragraph.'<h2>Premier intertitre</h2>'.$paragraph.'<blockquote><p>Une citation à mettre en valeur.</p></blockquote>'
    .'<figure><img src="/images/library-site-800.webp" alt="Image d\'essai" width="800" height="365"></figure>'
    .'<h2>Second intertitre</h2>'.$paragraph.$paragraph;

foreach ([['E2E Alpha', '2026-10-27', 0], ['E2E Bêta', '2026-10-26', 1], ['E2E Gamma', '2026-10-25', 2]] as [$title, $date, $i]) {
    NewsPost::create([
        'title' => $title, 'excerpt' => 'Chapeau d\'essai.', 'body' => $body, 'news_category_id' => $category->id, 'cover_id' => $ids[$i],
        'status' => PublicationStatus::Published, 'published_at' => $date.' 09:00:00',
    ]);
}

echo "actualités d'essai créées\n";
