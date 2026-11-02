<?php

namespace App\Support;

use App\Models\Formation;
use App\Models\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Les emplois du temps de la page « Emplois du temps » : un PDF par mention, saisi comme lien dans le texte de la page (back-office).
 * On ne fabrique aucune donnée : le nom vient du texte du lien, la faculté de la formation qui porte ce nom (si elle existe),
 * la taille du fichier du fichier lui-même. Ajouter ou retirer un lien dans la page ajoute ou retire une fiche.
 */
final class TimetableSheets
{
    /** Un lien vers un PDF : 1 = adresse, 2 = texte du lien. */
    private const LINK = '<a\s[^>]*href="([^"]+\.pdf(?:\?[^"]*)?)"[^>]*>(.*?)</a>';

    /**
     * @return Collection<int, array{key: string, name: string, faculty: ?string, formationUrl: ?string, url: string, size: ?int, art: list<array{0: int, 1: int, 2: int, 3: int}>}>
     */
    public static function from(Page $page): Collection
    {
        $formations = Formation::query()->visible()->with('faculty')->get();
        $seen = [];

        return collect($page->blocks)
            ->where('type', 'rich_text')
            ->flatMap(function (array $block) {
                preg_match_all('#'.self::LINK.'#isu', (string) ($block['data']['content'] ?? ''), $found, PREG_SET_ORDER);

                return $found;
            })
            ->map(function (array $link) use ($formations, &$seen) {
                $label = trim(html_entity_decode(strip_tags($link[2]), ENT_QUOTES | ENT_HTML5));
                // « Télécharger : EDT Mention Droit (PDF) » → « Mention Droit » → « Droit ».
                $name = trim(preg_replace(['/^\s*t[ée]l[ée]charger\s*:?\s*/iu', '/^\s*EDT\s+/iu', '/\s*\(PDF\)\s*$/iu', '/^\s*mention\s+/iu'], '', $label));
                $formation = self::formationNamed($formations, $name);
                $key = Str::slug($formation?->getRawOriginal('title') ?? $name) ?: 'mention';
                $seen[$key] = ($seen[$key] ?? 0) + 1;

                return [
                    'key' => $seen[$key] > 1 ? $key.'-'.$seen[$key] : $key,
                    'name' => $formation?->title ?? ($name !== '' ? Str::ucfirst($name) : $label),
                    'faculty' => $formation?->faculty?->name,
                    'formationUrl' => $formation?->url(),
                    'url' => html_entity_decode($link[1]),
                    'size' => self::sizeOf($link[1]),
                    'art' => self::art($key),
                ];
            })
            ->unique('url')
            ->values();
    }

    /** Le contenu de la page sans les liens PDF repris en fiches (ce qui reste, titre ou texte, s'affiche à la suite). */
    public static function remainder(Page $page): array
    {
        return collect($page->blocks)->map(function (array $block) {
            if (($block['type'] ?? null) === 'rich_text') {
                $content = preg_replace('#<p>\s*'.self::LINK.'\s*</p>#isu', '', (string) ($block['data']['content'] ?? ''));
                $block['data']['content'] = $content;

                if (trim(strip_tags($content)) === '' && empty($block['data']['heading'])) {
                    return null;
                }
            }

            return $block;
        })->filter()->values()->all();
    }

    /** @param  Collection<int, Formation>  $formations */
    private static function formationNamed(Collection $formations, string $name): ?Formation
    {
        $needle = self::plain($name);

        if ($needle === '') {
            return null;
        }

        return $formations->first(function (Formation $formation) use ($needle) {
            $title = self::plain($formation->getRawOriginal('title'));

            return $title === $needle || str_ends_with($title, ' '.$needle);
        });
    }

    private static function plain(string $text): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', strtolower(Str::ascii($text))));
    }

    /** Taille du fichier hébergé sur le site (en Ko) ; inconnue pour un lien externe ou un fichier absent. */
    private static function sizeOf(string $href): ?int
    {
        if (! str_starts_with($href, '/')) {
            return null;
        }

        $path = public_path(ltrim(urldecode(strtok($href, '?')), '/'));

        return is_file($path) ? max(1, (int) round(filesize($path) / 1024)) : null;
    }

    /**
     * Le motif dessiné de la fiche : cinq colonnes de huit lignes, des blocs [colonne, ligne, durée, ton] tirés de la clé de la fiche
     * (décor : un motif propre à chaque mention, toujours le même, qui ne représente aucun horaire réel).
     *
     * @return list<array{0: int, 1: int, 2: int, 3: int}>
     */
    private static function art(string $seed): array
    {
        $state = crc32($seed) ?: 1;
        $next = function (int $max) use (&$state): int {
            $state = ($state * 1103515245 + 12345) & 0x7fffffff;

            return ($state >> 8) % $max;
        };
        $blocks = [];

        foreach (range(0, 4) as $column) {
            $row = $next(2);
            while ($row < 8) {
                $span = 1 + $next(3);
                $span = min($span, 8 - $row);
                if ($next(5) > 0) {
                    $blocks[] = [$column, $row, $span, $next(3)];
                }
                $row += $span + 1 + $next(2);
            }
        }

        return $blocks;
    }
}
