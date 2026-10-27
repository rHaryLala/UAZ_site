<?php

use App\Enums\PublicationStatus;
use App\Models\Formation;
use App\Models\Menu;
use App\Models\Page;
use App\Models\Redirect;
use App\Models\SiteSetting;
use App\Support\RedirectCsvImporter;
use Database\Seeders\StructureSeeder;

it('seeds the site structure idempotently', function () {
    $this->seed(StructureSeeder::class);
    $this->seed(StructureSeeder::class);

    expect(Formation::count())->toBe(7)
        ->and(Page::where('key', 'home')->count())->toBe(1)
        ->and(Page::where('key', 'admissions-licence')->value('path'))->toBe('admissions/licence')
        ->and(Menu::count())->toBe(3)
        ->and(SiteSetting::current()->address)->toContain('Antsirabe');
});

it('never publishes invented facts: every unknown field is flagged TODO-CONTENU', function () {
    $this->seed(StructureSeeder::class);

    expect(Formation::first()->description)->toContain('TODO-CONTENU')
        ->and(SiteSetting::current()->phone)->toBe('+261 34 44 931 07');
});

it('keeps the seeded header menu resolvable with sub-menus', function () {
    $this->seed(StructureSeeder::class);

    $tree = Menu::resolved('header');
    $admissions = collect($tree)->firstWhere('label', 'Admissions');

    expect($tree)->toHaveCount(5)
        ->and($admissions['url'])->toBe('#')
        ->and(collect($admissions['children'])->pluck('label')->all())
        ->toBe(['Licence', 'Master', 'Frais académiques', 'Téléchargements']);
});

it('imports URL-MAP.csv idempotently and skips pattern rows', function () {
    $importer = app(RedirectCsvImporter::class);
    $path = base_path('docs/URL-MAP.csv');

    $first = $importer->import($path);
    $second = $importer->import($path);

    expect($first['created'])->toBeGreaterThan(30)
        ->and($first['skipped'])->toBeGreaterThan(0)
        ->and($second['created'])->toBe(0)
        ->and($second['updated'])->toBe($first['created'])
        ->and(Redirect::where('from_path', 'like', '%{%')->exists())->toBeFalse()
        ->and(Redirect::where('from_path', '/blog')->value('to_path'))->toBe('/actualites');
});

it('redirects an old WordPress article URL once imported', function () {
    app(RedirectCsvImporter::class)->import(base_path('docs/URL-MAP.csv'));

    $this->get('/licence/')->assertStatus(301)->assertRedirect('/admissions/licence');
    $this->get('/academique/emplois-du-temps/')->assertStatus(301)->assertRedirect('/formations/emplois-du-temps');
});

it('only exposes published structure pages', function () {
    $this->seed(StructureSeeder::class);
    Page::where('key', 'about')->update(['status' => PublicationStatus::Draft]);

    expect(Page::byKey('about'))->toBeNull()->and(Page::byKey('admissions-licence'))->not->toBeNull();
});
