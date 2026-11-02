<?php

/*
 * Albums d'essai pour tests/e2e/gallery.mjs : « up » crée six albums « E2E … » (photos prises dans la médiathèque de développement,
 * trois années), « down » les supprime. Usage : php tests/e2e/fixtures/gallery-albums.php up|down
 */

use App\Enums\PublicationStatus;
use App\Models\MediaAsset;
use App\Models\PhotoAlbum;

require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

PhotoAlbum::where('title', 'like', 'E2E %')->get()->each(fn ($album) => $album->forceDelete());

if (($argv[1] ?? '') !== 'up') {
    echo "albums d'essai supprimés\n";

    return;
}

$ids = MediaAsset::query()->whereHas('media', fn ($q) => $q->where('mime_type', 'like', 'image/%'))->orderBy('id')->limit(40)->pluck('id')->values();
$dates = ['2026-10-05', '2026-06-12', '2025-11-20', '2025-03-08', '2026-02-14', '2024-09-01'];

foreach ($dates as $i => $date) {
    $album = PhotoAlbum::create([
        'title' => 'E2E '.chr(65 + $i), 'description' => 'Album d\'essai.', 'taken_on' => $date,
        'status' => PublicationStatus::Published, 'published_at' => now()->subDay(), 'cover_id' => $ids[$i * 5],
    ]);
    foreach ($ids->slice($i * 5, 6)->values() as $position => $id) {
        $album->photos()->attach($id, ['position' => $position]);
    }
}

echo "albums d'essai créés\n";
