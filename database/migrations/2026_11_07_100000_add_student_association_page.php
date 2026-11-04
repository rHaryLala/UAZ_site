<?php

use App\Enums\PublicationStatus;
use App\Models\Menu;
use App\Models\Page;
use Database\Seeders\StructureSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Ajoute la page « Association des étudiants » (texte du Manuel de l'étudiant, section 3.3) sous « Vie étudiante », et son entrée à la fin du
 * sous-menu « Vie étudiante » du menu principal, sur les sites déjà installés. Rien n'est touché si la page ou l'entrée existent déjà, ni si la
 * page « Vie étudiante » est absente. Les titres anglais et malagasy sont posés d'emblée (le malagasy est à faire relire).
 */
return new class extends Migration
{
    private const SUMMARY = 'L\'Association des étudiants (SA) coordonne toutes les activités prévues de l\'année, sous la supervision du Vice-Recteur chargé des affaires estudiantines.';

    public function up(): void
    {
        $parent = Page::query()->where('key', 'student-life')->first();

        if (! $parent) {
            return;
        }

        $page = Page::query()->where('key', 'student-life-association')->first() ?? Page::create([
            'key' => 'student-life-association',
            'title' => 'Association des étudiants',
            'slug' => 'association-des-etudiants',
            'parent_id' => $parent->id,
            'summary' => self::SUMMARY,
            'sort_order' => 4,
            'blocks' => [['type' => 'rich_text', 'data' => ['heading' => null, 'content' => StructureSeeder::ASSOCIATION_TEXT, 'width' => 'normal']]],
            'translations' => [
                'en' => ['title' => 'Student association', 'summary' => 'The Student Association (SA) coordinates all the activities planned for the year, under the supervision of the Vice-Rector for Student Affairs.'],
                'mg' => ['title' => 'Fikambanan\'ny mpianatra', 'summary' => 'Ny Fikambanan\'ny mpianatra (SA) no mandrindra ny hetsika rehetra voalahatra amin\'ny taona, eo ambany fanaraha-mason\'ny Lefitra Filohan\'ny Oniversite miandraikitra ny raharaha momba ny mpianatra.'],
            ],
            'status' => PublicationStatus::Published,
            'published_at' => now(),
        ]);

        $menu = Menu::query()->where('handle', 'header')->first();

        if (! $menu) {
            return;
        }

        $items = collect($menu->items)->map(function ($item) use ($page) {
            if (($item['label'] ?? null) === 'Vie étudiante' && ! collect($item['children'] ?? [])->contains(fn ($child) => ($child['page_id'] ?? null) === $page->id)) {
                $item['children'] = [...($item['children'] ?? []), ['label' => 'Association des étudiants', 'type' => 'page', 'page_id' => $page->id, 'children' => []]];
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
            if (($item['label'] ?? null) === 'Vie étudiante') {
                $item['children'] = collect($item['children'] ?? [])->reject(fn ($child) => ($child['label'] ?? null) === 'Association des étudiants')->values()->all();
            }

            return $item;
        })->values()->all()]);

        Page::query()->where('key', 'student-life-association')->first()?->delete();
    }
};
