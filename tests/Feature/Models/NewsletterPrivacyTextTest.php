<?php

use App\Models\Page;
use Database\Seeders\StructureSeeder;

function privacyMigration(): void
{
    (require database_path('migrations/2026_11_16_100000_update_newsletter_privacy_text.php'))->up();
}

it('no longer promises a confirmation e-mail on the privacy page (seed and migration)', function () {
    $this->seed(StructureSeeder::class);

    $text = json_encode(Page::where('key', 'privacy')->firstOrFail()->blocks, JSON_UNESCAPED_UNICODE);

    expect($text)->toContain('L\'abonnement est immédiat')->not->toContain('personne ne peut vous inscrire à votre place')->not->toContain('Nous vous envoyons d\'abord un e-mail de confirmation');
});

it('rewrites the old newsletter paragraph already stored, in French and English, and leaves any other text alone', function () {
    $this->seed(StructureSeeder::class);
    $page = Page::where('key', 'privacy')->firstOrFail();

    $oldFr = '<p>Si vous vous abonnez, nous enregistrons <strong>votre adresse e-mail</strong>, la date de votre demande et celle de votre confirmation. Nous vous envoyons d\'abord un e-mail de confirmation : sans clic sur le lien, vous ne recevez rien et personne ne peut vous inscrire à votre place. Chaque message contient un lien pour vous désinscrire.</p>';
    $oldEn = '<p>If you subscribe, we record <strong>your email address</strong>, the date of your request and the date of your confirmation. We first send you a confirmation email: without a click on the link, you receive nothing and nobody can sign you up in your place. Every message contains a link.</p>';

    $page->update([
        'blocks' => [['type' => 'rich_text', 'data' => ['heading' => 'La lettre d\'information', 'content' => $oldFr]], ['type' => 'rich_text', 'data' => ['heading' => 'Texte réécrit', 'content' => '<p>Notre propre texte sur la lettre.</p>']]],
        'translations' => ['en' => ['blocks' => [['type' => 'rich_text', 'data' => ['heading' => 'The newsletter', 'content' => $oldEn]]]]],
    ]);

    privacyMigration();
    privacyMigration(); // rejouée : même résultat

    $page->refresh();
    $fr = json_encode($page->blocks, JSON_UNESCAPED_UNICODE);
    $en = json_encode($page->translations, JSON_UNESCAPED_UNICODE);

    expect($fr)->toContain('et la date de votre abonnement. L\'abonnement est immédiat : nous ne vous envoyons pas d\'e-mail de confirmation.')->toContain('Chaque message contient un lien pour vous désinscrire.')
        ->not->toContain('personne ne peut vous inscrire à votre place')->toContain('Notre propre texte sur la lettre.')
        ->and($en)->toContain('and the date of your subscription. Your subscription is immediate: we do not send you a confirmation email.')->not->toContain('nobody can sign you up');
});
