<?php

use App\Models\Menu;
use App\Models\Page;
use Illuminate\Database\Migrations\Migration;

/**
 * Ajoute « Système LMD » à la fin de la rubrique « Ressources » du menu principal des sites déjà installés (rien n'est touché si la page y figure
 * déjà, si la rubrique ou la page n'existe pas).
 */
return new class extends Migration
{
    public function up(): void
    {
        $menu = Menu::query()->where('handle', 'header')->first();
        $page = Page::query()->where('key', 'lmd')->first();

        if (! $menu || ! $page) {
            return;
        }

        $items = collect($menu->items)->map(function ($item) use ($page) {
            if (($item['label'] ?? null) === 'Ressources' && ! collect($item['children'] ?? [])->contains(fn ($child) => ($child['page_id'] ?? null) === $page->id)) {
                $item['children'] = [...($item['children'] ?? []), ['label' => 'Système LMD', 'type' => 'page', 'page_id' => $page->id, 'children' => []]];
            }

            return $item;
        })->values()->all();

        if ($items !== $menu->items) {
            $menu->update(['items' => $items]);
        }
    }

    public function down(): void
    {
        $menu = Menu::query()->where('handle', 'header')->first();

        $menu?->update(['items' => collect($menu->items)->map(function ($item) {
            if (($item['label'] ?? null) === 'Ressources') {
                $item['children'] = collect($item['children'] ?? [])->reject(fn ($child) => ($child['label'] ?? null) === 'Système LMD')->values()->all();
            }

            return $item;
        })->values()->all()]);
    }
};
