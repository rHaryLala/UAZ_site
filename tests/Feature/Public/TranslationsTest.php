<?php

use App\Support\Locales;
use App\Support\TranslationKeys;

it('has a translation for every French sentence used by the site, in every language', function (string $locale) {
    expect(TranslationKeys::missing($locale))->toBe([], "Phrases sans traduction ($locale) : lancez « php artisan i18n:check $locale --list ».");
})->with(['en', 'mg']);

it('keeps the :placeholders of every sentence in its translations', function (string $locale) {
    $broken = [];
    foreach (TranslationKeys::dictionary($locale) as $french => $translated) {
        if (TranslationKeys::placeholders($french) !== TranslationKeys::placeholders($translated)) {
            $broken[] = $french;
        }
    }

    expect($broken)->toBe([]);
})->with(['en', 'mg']);

it('has the same editorial texts in every language', function (string $locale) {
    foreach (TranslationKeys::groups() as $group) {
        $missing = array_diff_key(TranslationKeys::group('fr', $group), TranslationKeys::group($locale, $group));

        expect(array_keys($missing))->toBe([], "$locale/$group.php : textes manquants");
    }
})->with(['en', 'mg']);

it('serves translated texts on the prefixed pages and French on the others', function () {
    $this->seed(Database\Seeders\StructureSeeder::class);

    $this->get('/en/contact')->assertOk()->assertSee('Skip to main content')->assertSee('Contact us');
    $this->get('/mg/contact')->assertOk()->assertSee('Mankanesa amin\'ny votoaty fototra');
    $this->get('/contact')->assertOk()->assertSee('Aller au contenu principal')->assertSee('Contactez-nous');
});

it('restores French typography (non-breaking spaces) from lang/fr.json', function () {
    app()->setLocale('fr');
    expect(__('Une question ?'))->toBe("Une question\u{a0}?");

    app()->setLocale('en');
    expect(__('Une question ?'))->toBe('A question?');
    app()->setLocale('fr');
});
