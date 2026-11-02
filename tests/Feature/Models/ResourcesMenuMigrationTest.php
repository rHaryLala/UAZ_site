<?php

use App\Models\Menu;
use App\Models\Page;
use Database\Seeders\StructureSeeder;

function resourcesMigration(): object
{
    return require database_path('migrations/2026_11_05_100000_add_resources_to_header_menu.php');
}

/** Menu tel qu'il était avant la rubrique : « Téléchargements » sous « Admissions ». */
function legacyHeaderMenu(): Menu
{
    test()->seed(StructureSeeder::class);
    $downloads = Page::where('key', 'admissions-downloads')->first();
    $menu = Menu::where('handle', 'header')->first();
    $items = collect($menu->items)->reject(fn ($i) => $i['label'] === 'Ressources')->map(function ($item) use ($downloads) {
        if ($item['label'] === 'Admissions') {
            $item['children'][] = ['label' => 'Téléchargements', 'type' => 'page', 'page_id' => $downloads->id, 'children' => []];
        }

        return $item;
    })->values()->all();
    $menu->update(['items' => $items]);

    return $menu->fresh();
}

it('adds the Ressources section after Vie étudiante on an existing site, moving Téléchargements out of Admissions', function () {
    legacyHeaderMenu();

    resourcesMigration()->up();

    $tree = Menu::resolved('header');
    $labels = collect($tree)->pluck('label')->all();

    expect($labels)->toBe(['Formations', 'Admissions', 'Vie étudiante', 'Ressources', 'Centre de recherche', 'À propos'])
        ->and(collect(collect($tree)->firstWhere('label', 'Admissions')['children'])->pluck('label')->all())->toBe(['Licence', 'Master', 'Frais académiques'])
        ->and(collect(collect($tree)->firstWhere('label', 'Ressources')['children'])->pluck('label')->all())
        ->toBe(['Téléchargements', 'Galerie', 'Emplois du temps', 'Événements']);
});

it('leaves a menu that already has Ressources untouched, and can be rolled back', function () {
    $this->seed(StructureSeeder::class);
    $before = Menu::where('handle', 'header')->first()->items;

    resourcesMigration()->up();
    expect(Menu::where('handle', 'header')->first()->items)->toBe($before);

    resourcesMigration()->down();
    expect(collect(Menu::where('handle', 'header')->first()->items)->pluck('label')->all())->not->toContain('Ressources');
});

function lmdMigration(): object
{
    return require database_path('migrations/2026_11_06_100000_add_lmd_to_resources_menu.php');
}

it('adds Système LMD at the end of Ressources on an existing site, once, and can be rolled back', function () {
    legacyHeaderMenu();
    resourcesMigration()->up(); // la rubrique d'origine : quatre pages, sans « Système LMD »

    $labels = fn () => collect(collect(Menu::resolved('header'))->firstWhere('label', 'Ressources')['children'])->pluck('label')->all();
    expect($labels())->toBe(['Téléchargements', 'Galerie', 'Emplois du temps', 'Événements']);

    lmdMigration()->up();
    expect($labels())->toBe(['Téléchargements', 'Galerie', 'Emplois du temps', 'Événements', 'Système LMD']);

    lmdMigration()->up(); // idempotente
    expect($labels())->toHaveCount(5);

    lmdMigration()->down();
    expect($labels())->not->toContain('Système LMD');
});
