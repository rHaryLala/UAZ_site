<?php

use App\Models\NewsletterSubscriber;
use App\Models\Page;
use Database\Seeders\StructureSeeder;
use Symfony\Component\Finder\Finder;

function wordingMigration(string $file): void
{
    (require database_path("migrations/$file"))->up();
}

it('says « newsletter » and never « lettre d\'information » in the code, the views, the scripts and the translations', function () {
    $finder = Finder::create()->files()->in([base_path('app'), base_path('resources/views'), base_path('resources/js'), base_path('public/js/admin'), base_path('lang'), base_path('config'), base_path('routes'), base_path('database/seeders')])
        ->name(['*.php', '*.js', '*.json', '*.mjs']);

    $offenders = [];
    foreach ($finder as $file) {
        if (preg_match('/lettres?\s+d.{0,2}information/iu', $file->getContents())) {
            $offenders[] = $file->getRelativePathname();
        }
    }

    expect($offenders)->toBe([]);
});

it('says « newsletter » on the public pages', function () {
    $this->seed(StructureSeeder::class);

    foreach (['/', '/actualites', '/evenements', '/formations', '/contact', '/vie-etudiante/cafeteria'] as $path) {
        $html = $this->get($path)->assertOk()->getContent();

        expect($html)->not->toMatch('/lettres?\s+d(?:&#0?39;|\'|’)information/iu');
    }

    $this->get('/')->assertSee('Newsletter');
});

it('rewrites « lettre d\'information » on the privacy page already stored, and leaves a text written by hand alone', function () {
    $this->seed(StructureSeeder::class);
    $page = Page::where('key', 'privacy')->firstOrFail();

    $page->update(['blocks' => [
        ['type' => 'rich_text', 'data' => ['heading' => 'La lettre d\'information', 'content' => '<p>Si la fenêtre d\'abonnement à la lettre d\'information vous a déjà été proposée. Votre adresse n\'est utilisée que pour cette lettre.</p>']],
        ['type' => 'rich_text', 'data' => ['heading' => 'Texte réécrit', 'content' => '<p>Notre propre texte sur la lettre de la présidence.</p>']],
    ]]);

    wordingMigration('2026_11_16_110000_use_newsletter_wording_on_privacy_page.php');
    wordingMigration('2026_11_16_110000_use_newsletter_wording_on_privacy_page.php'); // rejouée : même résultat

    $text = json_encode($page->fresh()->blocks, JSON_UNESCAPED_UNICODE);

    expect($text)->toContain('La newsletter')->toContain('à la newsletter vous a déjà été proposée')->toContain('que pour cette newsletter.')
        ->not->toContain('lettre d\'information')->toContain('Notre propre texte sur la lettre de la présidence.');
});

it('subscribes the addresses still waiting for a confirmation that no longer exists, and leaves the unsubscribed alone', function () {
    $waiting = NewsletterSubscriber::create(['email' => 'attente@exemple.test']);
    $waiting->forceFill(['created_at' => '2026-10-05 10:00:00'])->save();
    $left = NewsletterSubscriber::create(['email' => 'parti@exemple.test', 'unsubscribed_at' => now()->subDay()]);
    $active = NewsletterSubscriber::create(['email' => 'actif@exemple.test', 'confirmed_at' => '2026-09-01 08:00:00']);

    wordingMigration('2026_11_16_120000_subscribe_unconfirmed_addresses.php');
    wordingMigration('2026_11_16_120000_subscribe_unconfirmed_addresses.php');

    expect($waiting->fresh()->status())->toBe('active')->and($waiting->fresh()->confirmed_at->format('Y-m-d'))->toBe('2026-10-05')
        ->and($left->fresh()->status())->toBe('unsubscribed')->and($left->fresh()->confirmed_at)->toBeNull()
        ->and($active->fresh()->confirmed_at->format('Y-m-d H:i'))->toBe('2026-09-01 08:00');
});
