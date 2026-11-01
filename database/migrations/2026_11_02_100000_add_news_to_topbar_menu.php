<?php

use App\Models\Menu;
use Illuminate\Database\Migrations\Migration;

/** Ajoute « Actualités » juste après « S'abonner » dans la barre du haut des sites déjà installés (rien n'est touché si l'entrée existe déjà). */
return new class extends Migration
{
    public function up(): void
    {
        $menu = Menu::query()->where('handle', 'topbar')->first();

        if (! $menu || collect($menu->items)->contains(fn ($item) => ($item['url'] ?? null) === '/actualites')) {
            return;
        }

        $items = array_values($menu->items);
        $position = collect($items)->search(fn ($item) => ($item['url'] ?? null) === '#newsletter');
        $entry = ['label' => 'Actualités', 'type' => 'url', 'url' => '/actualites', 'children' => []];

        array_splice($items, $position === false ? max(count($items) - 1, 0) : $position + 1, 0, [$entry]);
        $menu->update(['items' => $items]);
    }

    public function down(): void
    {
        $menu = Menu::query()->where('handle', 'topbar')->first();

        $menu?->update(['items' => collect($menu->items)->reject(fn ($item) => ($item['url'] ?? null) === '/actualites')->values()->all()]);
    }
};
