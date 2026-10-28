<?php

use App\Models\Menu;
use Illuminate\Database\Migrations\Migration;

/** Ajoute « S'abonner » juste après « Yearbook » dans la barre du haut des sites déjà installés (le menu est éditable : on ne touche à rien s'il existe déjà). */
return new class extends Migration
{
    public function up(): void
    {
        $menu = Menu::query()->where('handle', 'topbar')->first();

        if (! $menu || collect($menu->items)->contains(fn ($item) => ($item['url'] ?? null) === '#newsletter')) {
            return;
        }

        $items = array_values($menu->items);
        $position = collect($items)->search(fn ($item) => ($item['url'] ?? null) === '/yearbook');
        $entry = ['label' => 'S\'abonner', 'type' => 'url', 'url' => '#newsletter', 'children' => []];

        array_splice($items, $position === false ? max(count($items) - 1, 0) : $position + 1, 0, [$entry]);
        $menu->update(['items' => $items]);
    }

    public function down(): void
    {
        $menu = Menu::query()->where('handle', 'topbar')->first();

        $menu?->update(['items' => collect($menu->items)->reject(fn ($item) => ($item['url'] ?? null) === '#newsletter')->values()->all()]);
    }
};
