<?php

use App\Models\Menu;
use App\Models\Page;
use Database\Seeders\StructureSeeder;

function associationMigration(): object
{
    return require database_path('migrations/2026_11_07_100000_add_student_association_page.php');
}

/** Site tel qu'il était avant la page : ni la page, ni son entrée du menu « Vie étudiante ». */
function siteWithoutAssociation(): void
{
    test()->seed(StructureSeeder::class);
    Page::where('key', 'student-life-association')->delete();
    $menu = Menu::where('handle', 'header')->first();
    $menu->update(['items' => collect($menu->items)->map(function ($item) {
        if ($item['label'] === 'Vie étudiante') {
            $item['children'] = collect($item['children'])->reject(fn ($child) => $child['label'] === 'Association des étudiants')->values()->all();
        }

        return $item;
    })->values()->all()]);
}

$labels = fn () => collect(collect(Menu::resolved('header'))->firstWhere('label', 'Vie étudiante')['children'])->pluck('label')->all();

it('creates the page and its menu entry on an existing site, once', function () use ($labels) {
    siteWithoutAssociation();
    expect($labels())->toBe(['Bibliothèque', 'Cafétéria', 'Dortoirs']);

    associationMigration()->up();

    $page = Page::where('key', 'student-life-association')->first();
    expect($labels())->toBe(['Bibliothèque', 'Cafétéria', 'Dortoirs', 'Association des étudiants'])
        ->and($page->path)->toBe('vie-etudiante/association-des-etudiants')
        ->and($page->translation('title', 'en'))->toBe('Student association')
        ->and(collect($page->blocks)->pluck('data.content')->implode(' '))->toContain('d’au plus 15 noms');

    associationMigration()->up(); // idempotente : ni page ni entrée en double
    expect(Page::where('key', 'student-life-association')->count())->toBe(1)->and($labels())->toHaveCount(4);
});

it('leaves a site that already has the page untouched, and can be rolled back', function () use ($labels) {
    $this->seed(StructureSeeder::class);
    $menuBefore = Menu::where('handle', 'header')->first()->items;

    associationMigration()->up();
    expect(Menu::where('handle', 'header')->first()->items)->toBe($menuBefore);

    associationMigration()->down();
    expect($labels())->not->toContain('Association des étudiants')
        ->and(Page::where('key', 'student-life-association')->exists())->toBeFalse();
});

it('does nothing when the Vie étudiante page is missing', function () {
    $this->seed(StructureSeeder::class);
    Page::where('key', 'student-life-association')->delete();
    Page::where('key', 'student-life')->delete();

    associationMigration()->up();

    expect(Page::where('key', 'student-life-association')->exists())->toBeFalse();
});
