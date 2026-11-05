<?php

use App\Models\Menu;
use Illuminate\Database\Migrations\Migration;

/**
 * Ajoute « Catalogue de la bibliothèque » (site de la bibliothèque, nouvel onglet) dans la barre du haut des sites déjà installés, juste après
 * « Actualités » (ou avant la dernière entrée si « Actualités » n'y est pas). Rien n'est touché si une entrée vers ce site existe déjà.
 */
return new class extends Migration
{
    private const URL = 'https://library.zurcher.edu.mg';

    public function up(): void
    {
        $menu = Menu::query()->where('handle', 'topbar')->first();

        if (! $menu || collect($menu->items)->contains(fn ($item) => ($item['url'] ?? null) === self::URL)) {
            return;
        }

        $items = array_values($menu->items);
        $position = collect($items)->search(fn ($item) => ($item['url'] ?? null) === '/actualites');
        $entry = ['label' => 'Catalogue de la bibliothèque', 'type' => 'url', 'url' => self::URL, 'new_tab' => true, 'children' => []];

        array_splice($items, $position === false ? max(count($items) - 1, 0) : $position + 1, 0, [$entry]);
        $menu->update(['items' => $items]);
    }

    public function down(): void
    {
        $menu = Menu::query()->where('handle', 'topbar')->first();

        $menu?->update(['items' => collect($menu->items)->reject(fn ($item) => ($item['url'] ?? null) === self::URL)->values()->all()]);
    }
};
