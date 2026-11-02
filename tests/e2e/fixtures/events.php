<?php

/*
 * Événements d'essai pour tests/e2e/events.mjs : « up » crée cinq événements à venir et sept passés « E2E … » (dates relatives à maintenant,
 * images prises dans la médiathèque de développement), « ongoing » ajoute un événement en cours (à la place du prochain), « down » les supprime.
 * Usage : php tests/e2e/fixtures/events.php up|ongoing|down
 */

use App\Enums\PublicationStatus;
use App\Models\Event;
use App\Models\MediaAsset;

require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$action = $argv[1] ?? 'down';

if ($action !== 'ongoing') {
    Event::where('title', 'like', 'E2E %')->get()->each(fn ($event) => $event->forceDelete());
}

if ($action === 'down') {
    echo "événements d'essai supprimés\n";

    return;
}

$ids = MediaAsset::query()->whereHas('media', fn ($q) => $q->where('mime_type', 'like', 'image/%'))->orderBy('id')->limit(40)->pluck('id')->values();
$make = fn (string $title, $start, array $extra = []) => Event::create([
    'title' => 'E2E '.$title, 'summary' => 'Événement d\'essai.', 'description' => '<p>Description.</p>', 'starts_at' => $start,
    'status' => PublicationStatus::Published, 'published_at' => now()->subYear(),
] + $extra);

if ($action === 'ongoing') {
    $make('En cours', now()->subHour(), ['ends_at' => now()->addHours(5), 'location' => 'Amphithéâtre']);
    echo "événement en cours créé\n";

    return;
}

$make('Prochain', now()->addDays(2)->setTime(14, 30), ['ends_at' => now()->addDays(2)->setTime(17, 0), 'location' => 'Campus', 'cover_id' => $ids[0]]);
$make('Dixième jour', now()->addDays(10)->setTime(9, 0), ['ends_at' => now()->addDays(10)->setTime(12, 0), 'cover_id' => $ids[1], 'registration_url' => 'https://example.test/inscription']);
$make('Plusieurs jours', now()->addDays(40)->setTime(8, 0), ['ends_at' => now()->addDays(43)->setTime(18, 0), 'location' => 'Antsirabe', 'cover_id' => $ids[2]]);
$make('Toute la journée', now()->addDays(120)->startOfDay(), ['is_all_day' => true]);
$make('Plus tard', now()->addDays(400)->setTime(10, 0), ['cover_id' => $ids[3]]);

foreach ([5, 20, 45, 80, 130, 200, 300] as $i => $days) {
    $make('Passé '.($i + 1), now()->subDays($days)->setTime(10, 0), ['ends_at' => now()->subDays($days)->setTime(12, 0), 'location' => $i % 2 ? 'Campus' : null, 'cover_id' => $i === 5 ? null : $ids[4 + $i]]);
}

echo "événements d'essai créés\n";
