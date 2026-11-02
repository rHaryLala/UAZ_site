<?php

use App\Filament\Resources\Formations\Pages\EditFormation;
use App\Models\Formation;
use App\Models\Page;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\Locales;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\StructureSeeder;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(StructureSeeder::class);
    Cache::flush();
});

it('shows the translation of a page in the language of the visitor, and French where there is none', function () {
    $page = Page::where('key', 'about')->firstOrFail();
    $page->update([
        'title' => 'À propos',
        'translations' => ['en' => [
            'title' => 'About us',
            'summary' => 'History, vision and values.',
            'blocks' => [['type' => 'rich_text', 'data' => ['heading' => null, 'content' => '<p>English body</p>', 'width' => 'normal']]],
        ]],
    ]);

    // Le français reste la langue source ; la page À propos a une vue dessinée sur mesure, on regarde donc une page libre.
    $free = Page::where('key', 'privacy')->firstOrFail();
    $free->update(['status' => 'published', 'translations' => ['en' => [
        'title' => 'Privacy and cookies (EN)',
        'blocks' => [['type' => 'rich_text', 'data' => ['heading' => 'In short', 'content' => '<p>English body</p>', 'width' => 'normal']]],
    ]]]);

    $this->get('/confidentialite')->assertOk()->assertSee('Confidentialité et cookies')->assertSee('En bref')->assertDontSee('English body');
    $this->get('/en/confidentialite')->assertOk()->assertSee('Privacy and cookies (EN)')->assertSee('English body')->assertSee('In short');
    // Pas de traduction malagasy : la page s'affiche en français plutôt que vide.
    $this->get('/mg/confidentialite')->assertOk()->assertSee('Confidentialité et cookies')->assertSee('En bref');

    expect($page->fresh()->getRawOriginal('title'))->toBe('À propos');
});

it('keeps the numbers of key figures and opening hours when only their labels are translated', function () {
    $settings = SiteSetting::current();
    $settings->update([
        'key_figures' => [['value' => 1995, 'suffix' => '', 'label' => 'Fondation de l’UAZ'], ['value' => 200, 'suffix' => '+', 'label' => 'Hectares de campus']],
        'opening_hours' => [['days' => 'Lundi – jeudi', 'hours' => '7 h 30 – 17 h 30']],
        'translations' => ['en' => [
            'key_figures' => [['label' => 'UAZ founded'], ['label' => 'Hectares of campus']],
            'opening_hours' => [['days' => 'Monday – Thursday']],
        ]],
    ]);

    $fresh = SiteSetting::query()->first();
    expect($fresh->key_figures[1]['label'])->toBe('Hectares de campus');

    Locales::apply('en');
    expect($fresh->key_figures)->toBe([['value' => 1995, 'suffix' => '', 'label' => 'UAZ founded'], ['value' => 200, 'suffix' => '+', 'label' => 'Hectares of campus']])
        ->and($fresh->opening_hours)->toBe([['days' => 'Monday – Thursday', 'hours' => '7 h 30 – 17 h 30']]);

    Locales::reset();
});

it('cleans the HTML of translations and drops empty ones', function () {
    $formation = Formation::firstOrFail();
    $formation->update(['translations' => [
        'en' => ['description' => '<p onclick="x()">Hello <script>alert(1)</script><strong>world</strong></p>', 'title' => 'Law', 'summary' => ''],
        'mg' => ['title' => ''],
    ]]);

    $stored = $formation->fresh()->translations;

    expect($stored['en']['description'])->toContain('<strong>world</strong>')->not->toContain('script')->not->toContain('onclick')
        ->and($stored['en'])->not->toHaveKey('summary')
        ->and($stored)->not->toHaveKey('mg');
});

it('translates a formation card and its page, and leaves the French slug and search untouched', function () {
    $formation = Formation::where('title', 'Licence en Droit')->firstOrFail();
    $formation->update(['translations' => ['en' => ['title' => 'Bachelor\'s in Law', 'summary' => 'Pathways: public and private.']]]);

    $this->get('/en/formations')->assertOk()->assertSee('Bachelor&#039;s in Law', false);
    $this->get('/en/formations/'.$formation->slug)->assertOk()->assertSee('Bachelor&#039;s in Law', false);
    $this->get('/formations')->assertOk()->assertSee('Licence en Droit')->assertDontSee('Bachelor');

    // La recherche trouve aussi le texte traduit.
    $this->get('/en/recherche?q=Bachelor')->assertOk();
});

it('lets an editor write translations from the formation form', function () {
    $admin = User::factory()->create()->assignRole(RolesAndPermissionsSeeder::SUPER_ADMIN);
    $formation = Formation::where('title', 'Licence en Droit')->firstOrFail();

    Livewire::actingAs($admin)->test(EditFormation::class, ['record' => $formation->getRouteKey()])
        ->fillForm([
            'translations.en.title' => 'Bachelor\'s in Law',
            'translations.mg.title' => 'Licence amin\'ny Lalàna',
            'translations.en.admission_requirements' => '<p>Admission after the baccalaureate.</p>',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $fresh = $formation->fresh();
    expect($fresh->translation('title', 'en'))->toBe('Bachelor\'s in Law')
        ->and($fresh->translation('title', 'mg'))->toBe('Licence amin\'ny Lalàna')
        ->and($fresh->translation('admission_requirements', 'en'))->toContain('Admission after the baccalaureate.')
        ->and($fresh->getRawOriginal('title'))->toBe('Licence en Droit');
});

it('seeds English and Malagasy content for the site structure, without overwriting an editor\'s translation', function () {
    $formation = Formation::where('title', 'Licence en Droit')->firstOrFail();
    $formation->update(['translations' => ['en' => ['title' => 'Law (edited by an editor)']]]);

    $this->seed(Database\Seeders\ContentTranslationsSeeder::class);
    $this->seed(Database\Seeders\ContentTranslationsSeeder::class); // idempotent

    $formation = $formation->fresh();
    expect($formation->translation('title', 'en'))->toBe('Law (edited by an editor)')
        ->and($formation->translation('title', 'mg'))->toBe('Licence amin\'ny Lalàna')
        ->and($formation->translation('summary', 'en'))->toBe('Pathways: Public · Private.')
        ->and(SiteSetting::current()->translation('tagline', 'en'))->toBe('Preparing today the leaders of tomorrow')
        ->and(Page::where('key', 'privacy')->first()->translation('blocks', 'en'))->toHaveCount(9);

    $this->get('/en/formations')->assertOk()->assertSee('Law (edited by an editor)')->assertSee('Health sciences');
    $this->get('/mg/formations')->assertOk()->assertSee('Siansa momba ny fahasalamana');
});
