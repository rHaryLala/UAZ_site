<?php

use App\Models\Page;
use Illuminate\Database\Migrations\Migration;

/**
 * Le site compte désormais ses visiteurs (TrackVisit, tableau de bord) : la page « Confidentialité et cookies » ne doit plus affirmer qu'il n'utilise
 * aucun outil de mesure d'audience, et doit expliquer ce qui est compté. Seules les phrases d'origine (français et anglais) sont remplacées et le
 * paragraphe est ajouté une seule fois, après « Les cookies de ce site » ; un texte réécrit dans le back-office n'est jamais modifié.
 */
return new class extends Migration
{
    private const REPLACEMENTS = [
        "aucun cookie publicitaire, aucun outil de mesure d'audience et aucun traceur de réseau social</strong>. " => 'aucun cookie publicitaire et aucun traceur de réseau social</strong>. Il compte seulement, de façon anonyme, le nombre de visiteurs (voir « Statistiques de fréquentation » plus bas). ',
        'no advertising cookies, no audience-measurement tools and no social-network trackers</strong>. ' => 'no advertising cookies and no social-network trackers</strong>. It only counts visitors anonymously (see “Visit statistics” below). ',
        'Dernière mise à jour : 28 octobre 2026.' => 'Dernière mise à jour : 19 novembre 2026.',
        'Last updated: 28 October 2026.' => 'Last updated: 19 November 2026.',
    ];

    private const FRENCH = [
        'after' => 'Les cookies de ce site',
        'heading' => 'Statistiques de fréquentation',
        'content' => "<p>Pour savoir combien de personnes consultent le site, le serveur compte chaque page vue. <strong>Aucun cookie n'est déposé</strong> et <strong>votre adresse IP n'est pas conservée</strong> : elle sert seulement à calculer, avec le nom de votre navigateur et une clé qui change chaque jour, une empreinte chiffrée et non réversible, qui ne permet ni de vous retrouver ni de vous suivre d'un jour à l'autre.</p>"
            ."<p>Nous enregistrons aussi l'adresse de la page vue (sans ce qui suit le « ? »), la langue et, si vous venez d'un autre site, le nom de ce site. Les visites des robots et de l'espace d'administration ne sont pas comptées. Ces données ne servent qu'aux chiffres du tableau de bord de l'équipe du site et sont supprimées au bout de 400 jours.</p>",
    ];

    private const ENGLISH = [
        'after' => 'The cookies on this site',
        'heading' => 'Visit statistics',
        'content' => '<p>To know how many people visit the site, the server counts each page view. <strong>No cookie is set</strong> and <strong>your IP address is not kept</strong>: it is only used to compute, together with the name of your browser and a key that changes every day, an encrypted, non-reversible fingerprint that can neither identify you nor follow you from one day to the next.</p>'
            .'<p>We also record the address of the page viewed (without anything after the “?”), the language and, if you come from another site, the name of that site. Visits by robots and from the administration area are not counted. This data is only used for the figures on the site team’s dashboard and is deleted after 400 days.</p>',
    ];

    public function up(): void
    {
        $replace = function (mixed $value) use (&$replace): mixed {
            if (is_array($value)) {
                return array_map($replace, $value);
            }

            return is_string($value) ? strtr($value, self::REPLACEMENTS) : $value;
        };

        Page::query()->where('key', 'privacy')->get()->each(function (Page $page) use ($replace) {
            $changed = false;

            foreach (['blocks', 'draft', 'translations'] as $attribute) {
                $current = $page->getAttribute($attribute);

                if (! is_array($current)) {
                    continue;
                }

                $updated = $replace($current);

                if ($attribute === 'blocks') {
                    $updated = $this->insertAfter($updated, self::FRENCH);
                } elseif ($attribute === 'draft' && isset($updated['blocks']) && is_array($updated['blocks'])) {
                    $updated['blocks'] = $this->insertAfter($updated['blocks'], self::FRENCH);
                } elseif ($attribute === 'translations' && isset($updated['en']['blocks']) && is_array($updated['en']['blocks'])) {
                    $updated['en']['blocks'] = $this->insertAfter($updated['en']['blocks'], self::ENGLISH);
                }

                if ($updated !== $current) {
                    $page->setAttribute($attribute, $updated);
                    $changed = true;
                }
            }

            if ($changed) {
                $page->save();
            }
        });
    }

    public function down(): void
    {
        // Le texte d'avant décrivait un fonctionnement qui n'existe plus : on ne le rétablit pas.
    }

    /**
     * @param  array<int, mixed>  $blocks
     * @param  array{after: string, heading: string, content: string}  $new
     * @return array<int, mixed>
     */
    private function insertAfter(array $blocks, array $new): array
    {
        if (collect($blocks)->contains(fn ($block) => ($block['data']['heading'] ?? null) === $new['heading'])) {
            return $blocks;
        }

        $position = collect($blocks)->search(fn ($block) => ($block['data']['heading'] ?? null) === $new['after']);

        if ($position === false) {
            return $blocks;
        }

        array_splice($blocks, $position + 1, 0, [['type' => 'rich_text', 'data' => ['heading' => $new['heading'], 'content' => $new['content'], 'width' => 'normal']]]);

        return array_values($blocks);
    }
};
