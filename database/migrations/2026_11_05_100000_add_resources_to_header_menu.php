<?php

use App\Models\Menu;
use App\Models\Page;
use Illuminate\Database\Migrations\Migration;

/**
 * Ajoute la rubrique « Ressources » (Téléchargements, Galerie, Emplois du temps, Événements) au menu principal des sites déjà installés,
 * juste après « Vie étudiante ». « Téléchargements » quitte « Admissions » pour y être rangé. Rien n'est touché si la rubrique existe déjà.
 */
return new class extends Migration
{
    public function up(): void
    {
        $menu = Menu::query()->where('handle', 'header')->first();

        if (! $menu || collect($menu->items)->contains(fn ($item) => ($item['label'] ?? null) === 'Ressources')) {
            return;
        }

        $page = fn (string $label, string $key) => ($id = Page::query()->where('key', $key)->value('id'))
            ? ['label' => $label, 'type' => 'page', 'page_id' => $id, 'children' => []]
            : null;
        $url = fn (string $label, string $path) => ['label' => $label, 'type' => 'url', 'url' => $path, 'children' => []];

        $entry = ['label' => 'Ressources', 'type' => 'url', 'url' => '#', 'children' => array_values(array_filter([
            $page('Téléchargements', 'admissions-downloads'),
            $url('Galerie', '/galerie'),
            $page('Emplois du temps', 'timetables'),
            $url('Événements', '/evenements'),
        ]))];

        $downloads = $entry['children'][0]['page_id'] ?? null;
        $items = collect($menu->items)->map(function ($item) use ($downloads) {
            // Un seul emplacement pour « Téléchargements » : on le retire d'« Admissions ».
            if ($downloads && ($item['label'] ?? null) === 'Admissions') {
                $item['children'] = collect($item['children'] ?? [])->reject(fn ($child) => ($child['page_id'] ?? null) === $downloads)->values()->all();
            }

            return $item;
        })->values()->all();

        $position = collect($items)->search(fn ($item) => ($item['label'] ?? null) === 'Vie étudiante');
        array_splice($items, $position === false ? max(count($items) - 2, 0) : $position + 1, 0, [$entry]);

        $menu->update(['items' => $items]);
    }

    public function down(): void
    {
        $menu = Menu::query()->where('handle', 'header')->first();

        $menu?->update(['items' => collect($menu->items)->reject(fn ($item) => ($item['label'] ?? null) === 'Ressources')->values()->all()]);
    }
};
