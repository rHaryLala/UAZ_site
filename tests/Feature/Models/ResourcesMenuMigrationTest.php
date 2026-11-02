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
