<?php

namespace App\Http\Controllers;

use App\Enums\FormationLevel;
use App\Models\Event;
use App\Models\Formation;
use App\Models\NewsCategory;
use App\Models\NewsPost;
use App\Models\TeamMember;
use App\Models\Testimonial;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;

/** Catalogue visuel du design system (couleurs, typographies, composants). Désactivé en production. */
class DesignSystemController extends Controller
{
    public function __invoke(): View
    {
        $post = new NewsPost([
            'title' => 'Culte d\'ouverture de l\'année académique : une nouvelle page s\'ouvre',
            'excerpt' => 'Exemple d\'extrait affiché sous le titre d\'une actualité, limité à trois lignes dans la carte.',
            'published_at' => now()->subDays(3),
        ]);
        $post->setRelation('category', new NewsCategory(['name' => 'Communauté']));
        $post->slug = 'exemple';

        $formation = new Formation([
            'title' => 'Licence en Informatique',
            'summary' => 'Système d\'information et génie logiciel · Maintenance et administration de réseau.',
            'level' => FormationLevel::Licence,
            'duration' => '3 ans',
            'credits' => 180,
        ]);
        $formation->slug = 'exemple';

        $member = new TeamMember(['name' => 'Prénom Nom', 'job_title' => 'Fonction', 'email' => 'prenom.nom@exemple.org']);

        $event = new Event(['title' => 'Journée portes ouvertes', 'location' => 'Campus de l\'UAZ', 'starts_at' => now()->addWeek()->setTime(9, 0), 'ends_at' => now()->addWeek()->setTime(12, 0)]);
        $event->slug = 'exemple';

        $testimonial = new Testimonial(['author_name' => 'Prénom Nom', 'author_role' => 'Étudiant(e)', 'quote' => 'Un cadre d\'étude idéal pour la concentration et le bien-être.']);

        return view('design-system', [
            'post' => $post,
            'formation' => $formation,
            'member' => $member,
            'event' => $event,
            'testimonial' => $testimonial,
            'paginator' => new LengthAwarePaginator([], 120, 12, 4, ['path' => url('/design-system')]),
            'palette' => $this->palette(),
            'contrasts' => $this->contrasts(),
        ]);
    }

    /** @return array<string, array<int, string>> */
    private function palette(): array
    {
        $css = file_get_contents(resource_path('css/app.css'));
        preg_match_all('/--color-(navy|gold|ink|paper)-(\d+):\s*(#[0-9a-f]{6});/i', $css, $m, PREG_SET_ORDER);

        $palette = [];
        foreach ($m as [, $name, $step, $hex]) {
            $palette[$name][(int) $step] = strtoupper($hex);
        }

        return $palette;
    }

    /**
     * Paires texte/fond autorisées, avec leur ratio de contraste WCAG calculé.
     *
     * @return list<array{label: string, fg: string, bg: string, ratio: float}>
     */
    private function contrasts(): array
    {
        $pairs = [
            ['Texte courant', '#30353D', '#FBF9F4'],
            ['Texte secondaire', '#4B5160', '#FBF9F4'],
            ['Titres (marine) sur papier', '#232E66', '#FBF9F4'],
            ['Blanc sur marine', '#FFFFFF', '#232E66'],
            ['Blanc sur marine foncé', '#FFFFFF', '#131A3F'],
            ['Texte or sur papier (or 700)', '#695638', '#FBF9F4'],
            ['Or clair sur marine (or 300)', '#C5AC85', '#232E66'],
            ['Bouton or : marine foncé sur or 300', '#131A3F', '#C5AC85'],
            ['Or du sceau sur blanc (grands textes, graphismes)', '#8A724B', '#FFFFFF'],
        ];

        return array_map(fn ($p) => ['label' => $p[0], 'fg' => $p[1], 'bg' => $p[2], 'ratio' => self::ratio($p[1], $p[2])], $pairs);
    }

    private static function luminance(string $hex): float
    {
        [$r, $g, $b] = array_map(function ($c) {
            $c = hexdec($c) / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, str_split(ltrim($hex, '#'), 2));

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    private static function ratio(string $a, string $b): float
    {
        [$hi, $lo] = [max(self::luminance($a), self::luminance($b)), min(self::luminance($a), self::luminance($b))];

        return round(($hi + 0.05) / ($lo + 0.05), 2);
    }
}
