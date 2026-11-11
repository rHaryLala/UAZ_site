<?php

/*
 * Données d'essai pour tests/e2e/newsletter-editor.mjs : « up » crée quatre abonnés (deux actifs, un en attente de confirmation, un désinscrit)
 * et une lettre en brouillon « E2E lettre » ; « down » les supprime (et les envois liés). Usage : php tests/e2e/fixtures/newsletter.php up|down
 */

use App\Models\Newsletter;
use App\Models\NewsletterSubscriber;
use App\Support\NewsletterBlocks;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// Nettoyage direct en base : une lettre envoyée ne peut pas être supprimée par le modèle (c'est voulu), mais ces lettres d'essai doivent disparaître.
$ids = DB::table('newsletters')->where('subject', 'like', 'E2E %')->pluck('id');
DB::table('newsletter_deliveries')->whereIn('newsletter_id', $ids)->delete();
DB::table('newsletters')->whereIn('id', $ids)->delete();
NewsletterSubscriber::where('email', 'like', 'e2e-nl-%')->delete();

if (($argv[1] ?? '') !== 'up') {
    echo "données d'essai supprimées\n";

    return;
}

NewsletterSubscriber::create(['email' => 'e2e-nl-actif1@example.test', 'confirmed_at' => now()]);
NewsletterSubscriber::create(['email' => 'e2e-nl-actif2@example.test', 'confirmed_at' => now()]);
NewsletterSubscriber::create(['email' => 'e2e-nl-attente@example.test', 'confirmation_sent_at' => now()]);
NewsletterSubscriber::create(['email' => 'e2e-nl-parti@example.test', 'confirmed_at' => now()->subDay(), 'unsubscribed_at' => now()]);

$newsletter = Newsletter::create([
    'subject' => 'E2E lettre', 'blocks' => NewsletterBlocks::starter(), 'theme' => NewsletterBlocks::defaultTheme(), 'status' => Newsletter::DRAFT,
]);

echo $newsletter->id."\n";
