<?php

namespace App\Support;

use App\Models\MediaAsset;
use App\Models\SiteSetting;

/**
 * Modèles de newsletters proposés à la création (et dans l'éditeur) : une mise en page moderne déjà composée, à adapter. Chaque modèle donne un thème,
 * un objet et des blocs ; les images sont posées automatiquement avec les dernières photos de la médiathèque (s'il y en a), sinon elles
 * restent à choisir. Les textes sont des exemples en français à remplacer.
 */
final class NewsletterTemplates
{
    private const NAVY = '#131a3f';

    private const GOLD = '#e0b957';

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::catalog());
    }

    /** L'objet proposé par chaque modèle. @return array<string, string> */
    public static function subjects(): array
    {
        return array_map(fn (array $t) => $t['subject'], self::catalog());
    }

    /** Nom et description de chaque modèle, sans construire les blocs. @return array<string, array{name: string, description: string}> */
    public static function list(): array
    {
        return array_map(fn (array $t) => ['name' => $t['name'], 'description' => $t['description']], self::catalog());
    }

    /**
     * Le modèle prêt à poser : objet, thème et blocs avec leurs identifiants, photos de la médiathèque comprises.
     *
     * @return array{subject: string, theme: array<string, string>, blocks: list<array<string, mixed>>}|null
     */
    public static function build(string $key): ?array
    {
        $template = self::catalog()[$key] ?? null;

        if (! $template) {
            return null;
        }

        $blocks = self::fillPhotos(NewsletterBlocks::withIds($template['blocks']));

        return ['subject' => $template['subject'], 'theme' => NewsletterBlocks::theme($template['theme']), 'blocks' => NewsletterBlocks::normalize($blocks)];
    }

    /**
     * Pose les dernières photos de la médiathèque sur tous les emplacements photo encore vides (bloc « image », « image et texte », « photo en fond »,
     * cases de la mosaïque… — seulement celles que la disposition affiche), en boucle s'il y en a peu.
     */
    private static function fillPhotos(array $blocks): array
    {
        $photos = MediaAsset::query()
            ->whereHas('media', fn ($media) => $media->where('mime_type', 'like', 'image/%'))
            ->latest('id')->limit(12)->pluck('id')->all();

        if ($photos === []) {
            return $blocks;
        }

        $types = NewsletterBlocks::types();
        $next = 0;
        $walk = function (array $list) use (&$walk, &$next, $photos, $types): array {
            foreach ($list as &$block) {
                $keys = collect($types[$block['type']]['fields'] ?? [])->where('kind', 'media')->pluck('key')->all();

                if ($block['type'] === 'mosaic') {
                    $keys = array_slice(NewsletterBlocks::MOSAIC_MEDIA, 0, NewsletterBlocks::MOSAIC_TILES[$block['data']['layout'] ?? '1-2'] ?? 3);
                }

                foreach ($keys as $key) {
                    if (empty($block['data'][$key])) {
                        $block['data'][$key] = $photos[$next++ % count($photos)];
                    }
                }

                if (isset($block['children'])) {
                    $block['children'] = array_map($walk, $block['children']);
                }
            }

            return $list;
        };

        return $walk($blocks);
    }

    // --- Petits constructeurs, pour lire les modèles d'un coup d'œil ----------------------------------------------------------------------

    private static function h(string $text, array $data = []): array
    {
        return NewsletterBlocks::node('heading', ['text' => $text] + $data);
    }

    private static function eyebrow(string $text, string $color = self::GOLD, string $align = 'left'): array
    {
        return self::h($text, ['size' => 12, 'upper' => '1', 'color' => $color, 'align' => $align]);
    }

    private static function t(string $html, array $data = []): array
    {
        return NewsletterBlocks::node('text', ['html' => $html] + $data);
    }

    private static function img(array $data = []): array
    {
        return NewsletterBlocks::node('image', ['radius' => 8] + $data);
    }

    private static function btn(string $label, string $path, array $data = []): array
    {
        return NewsletterBlocks::node('button', ['label' => $label, 'url' => url($path)] + $data);
    }

    private static function section(array $data, array $children): array
    {
        return NewsletterBlocks::node('section', $data, [$children]);
    }

    private static function cols(array $columns, array $data = []): array
    {
        return NewsletterBlocks::node('columns', ['count' => count($columns)] + $data, $columns);
    }

    private static function spacer(int $height = 24, string $color = ''): array
    {
        return NewsletterBlocks::node('spacer', ['height' => $height, 'color' => $color]);
    }

    private static function hero(array $data): array
    {
        return NewsletterBlocks::node('hero', $data);
    }

    private static function mosaic(array $data): array
    {
        return NewsletterBlocks::node('mosaic', $data);
    }

    /** @return array<string, array{name: string, description: string, subject: string, theme: array<string, string>, blocks: list<array<string, mixed>>}> */
    private static function catalog(): array
    {
        $month = ucfirst(now()->translatedFormat('F Y'));
        // Tous les modèles reprennent l'en-tête de l'UAZ (hashtag, sceau au centre, réseaux sociaux du site), sur un grand bandeau bleu.
        $header = NewsletterBlocks::modernHeader(SiteSetting::current()->social_links ?? []);
        $sans = ['font' => 'sans'] + $header;
        $serif = ['font' => 'serif'] + $header;
        $paper = '#f4f1ea';
        $bronze = '#8a724b';

        return [
            // Style « vitrine » : une grande photo, un panneau translucide aux couleurs de l'UAZ et un tampon rond.
            'une' => [
                'name' => 'À la une',
                'description' => 'Une grande photo en parallax avec un panneau bleu nuit translucide et un tampon rond, une mosaïque de trois photos et les dernières actualités.',
                'subject' => 'À la une à l’UAZ — '.$month,
                'theme' => $sans,
                'blocks' => [
                    self::hero(['height' => 420, 'layout' => 'left', 'tone' => 'navy', 'opacity' => 88, 'parallax' => '1', 'eyebrow' => 'À LA UNE', 'title' => 'Le titre de la grande nouvelle', 'text' => 'Deux phrases pour donner envie de lire la suite : ce qui se passe, pour qui, et pourquoi c’est important.', 'button_label' => 'Lire l’article', 'button_url' => url('/actualites'), 'badge' => 'Nouveau']),
                    self::spacer(14),
                    self::eyebrow('EN IMAGES', $bronze, 'center'),
                    self::h('Le mois sur le campus', ['size' => 28, 'align' => 'center']),
                    self::mosaic(['layout' => '3', 'gap' => 8, 'radius' => 0]),
                    self::spacer(10),
                    self::h('Les dernières actualités', ['size' => 26]),
                    NewsletterBlocks::node('news', ['title' => '', 'count' => 3]),
                    self::hero(['height' => 320, 'layout' => 'right', 'tone' => 'gold', 'opacity' => 100, 'eyebrow' => 'ADMISSIONS', 'title' => 'Rejoignez-nous à la rentrée', 'text' => 'Licences, masters et formations professionnelles.', 'button_label' => 'Voir les formations', 'button_url' => url('/formations')]),
                ],
            ],

            // Style « récit » : des rangées photo / texte, la photo posée sur un rectangle de couleur décalé.
            'recit' => [
                'name' => 'Récit en rangées',
                'description' => 'Des rangées photo et texte en alternance, chaque photo posée sur un rectangle doré décalé, comme une histoire à lire de haut en bas.',
                'subject' => 'Notre histoire ce mois-ci — '.$month,
                'theme' => $serif,
                'blocks' => [
                    self::section(['bg' => $paper, 'padding' => 'lg'], [
                        self::eyebrow('NOTRE HISTOIRE', $bronze, 'center'),
                        self::h('Une histoire à partager', ['size' => 38, 'level' => 'h1', 'align' => 'center']),
                        self::t('<p>Une introduction chaleureuse en deux phrases : le fil rouge de ce numéro.</p>', ['align' => 'center', 'size' => 17]),
                    ]),
                    self::spacer(14),
                    self::cols([
                        [self::img(['radius' => 0, 'shadow' => self::GOLD])],
                        [self::eyebrow('CHAPITRE 1', $bronze), self::h('Le premier moment', ['size' => 24]), self::t('<p>Racontez ici ce qui s’est passé, avec les mots de ceux qui l’ont vécu.</p>', ['size' => 16]), self::btn('Lire la suite', '/actualites', ['align' => 'left', 'bg' => $bronze, 'radius' => 4])],
                    ], ['ratio' => '1:1', 'valign' => 'middle', 'gap' => 28]),
                    self::spacer(18),
                    self::cols([
                        [self::eyebrow('CHAPITRE 2', $bronze), self::h('Le deuxième moment', ['size' => 24]), self::t('<p>Un second épisode, un témoignage, une rencontre qui a marqué le mois.</p>', ['size' => 16]), self::btn('Lire la suite', '/actualites', ['align' => 'left', 'bg' => $bronze, 'radius' => 4])],
                        [self::img(['radius' => 0, 'shadow' => '#232e66'])],
                    ], ['ratio' => '1:1', 'valign' => 'middle', 'gap' => 28]),
                    self::spacer(18),
                    NewsletterBlocks::node('quote', ['text' => 'Une phrase forte, celle que chacun retiendra de ce récit.', 'author' => 'Un membre de la communauté', 'accent' => $bronze]),
                    NewsletterBlocks::node('divider', ['width' => 'short']),
                    self::btn('Découvrir l’UAZ', '/a-propos', ['bg' => '#232e66', 'radius' => 999, 'size' => 'lg']),
                ],
            ],

            // Style « magazine » : une citation sur une photo, des bandes bleues, des rangées de trois photos, un grand bouton.
            'magazine' => [
                'name' => 'Magazine',
                'description' => 'Une citation posée sur une photo assombrie, des bandes bleu nuit, une rangée de trois photos encadrées et un bouton pleine largeur.',
                'subject' => 'Le magazine de l’UAZ — '.$month,
                'theme' => $sans,
                'blocks' => [
                    self::hero(['height' => 440, 'layout' => 'text', 'parallax' => '1', 'eyebrow' => 'ÉDITO', 'title' => '« Une phrase d’ouverture qui marque »', 'text' => 'Le recteur, un enseignant ou un étudiant : celui qui parle ce mois-ci.', 'button_label' => '', 'button_url' => '']),
                    self::section(['bg' => '#232e66', 'padding' => 'md'], [
                        self::h('Cette sélection est pour vous', ['size' => 28, 'align' => 'center']),
                        self::t('<p>Trois sujets à ne pas manquer, choisis par la rédaction.</p>', ['align' => 'center', 'size' => 16]),
                    ]),
                    self::spacer(10),
                    self::mosaic(['layout' => '3', 'gap' => 10, 'radius' => 0, 'frame' => '#e1ebff']),
                    self::cols([
                        [self::h('Sujet un', ['size' => 18, 'align' => 'center']), self::t('<p>Une ligne pour le présenter.</p>', ['size' => 14, 'align' => 'center'])],
                        [self::h('Sujet deux', ['size' => 18, 'align' => 'center']), self::t('<p>Une ligne pour le présenter.</p>', ['size' => 14, 'align' => 'center'])],
                        [self::h('Sujet trois', ['size' => 18, 'align' => 'center']), self::t('<p>Une ligne pour le présenter.</p>', ['size' => 14, 'align' => 'center'])],
                    ], ['gap' => 10]),
                    self::section(['bg' => '#e1ebff', 'padding' => 'md'], [
                        self::eyebrow('À RETENIR', $bronze, 'center'),
                        self::h('Ce qu’il faut savoir', ['size' => 26, 'align' => 'center']),
                        self::t('<ul><li>Un fait ou une date à retenir</li><li>Un deuxième rendez-vous</li><li>Un dernier rappel</li></ul>', ['size' => 15]),
                    ]),
                    self::btn('Tout voir sur le site', '/actualites', ['full' => '1', 'bg' => self::NAVY, 'radius' => 0, 'size' => 'lg']),
                ],
            ],

            // Style « affiche » : papier beige, très grand titre, mosaïque de photos encadrées, bouton arrondi.
            'affiche' => [
                'name' => 'Affiche',
                'description' => 'Un fond papier, un très grand titre, une mosaïque de photos encadrées (effet « bento ») et un bouton arrondi.',
                'subject' => 'Ne manquez pas ça — '.$month,
                'theme' => $serif,
                'blocks' => [
                    self::section(['bg' => $paper, 'padding' => 'lg'], [
                        self::eyebrow('DU '.now()->format('d/m').' AU '.now()->addDays(14)->format('d/m'), $bronze, 'center'),
                        self::h('GRANDE OFFRE', ['size' => 56, 'level' => 'h1', 'align' => 'center', 'upper' => '1']),
                        self::h('Un sous-titre qui précise l’idée', ['size' => 20, 'align' => 'center', 'color' => $bronze]),
                        self::spacer(8),
                        self::mosaic(['layout' => '1-2', 'height' => 340, 'gap' => 8, 'radius' => 0, 'frame' => '#ffffff', 'tile_bg' => '#e1ebff']),
                        self::spacer(10),
                        self::t('<p>Quelques mots pour expliquer l’offre, l’événement ou l’appel à lancer.</p>', ['align' => 'center', 'size' => 16]),
                        self::spacer(6),
                        self::btn('J’en profite', '/', ['bg' => self::NAVY, 'radius' => 999, 'size' => 'lg']),
                    ]),
                ],
            ],

            'actualites' => [
                'name' => 'Actualités du mois',
                'description' => 'Un grand titre sur fond bleu nuit, une photo, les dernières actualités du site et un agenda.',
                'subject' => 'Les nouvelles de l’UAZ — '.$month,
                'theme' => $sans,
                'blocks' => [
                    self::section(['bg' => '#232e66', 'padding' => 'lg'], [
                        self::eyebrow('NEWSLETTER · '.mb_strtoupper($month), self::GOLD, 'center'),
                        self::h('Les nouvelles de l’UAZ', ['size' => 38, 'level' => 'h1', 'align' => 'center']),
                        self::t('<p>Des nouvelles du campus, de nos étudiants et de nos enseignants, racontées en quelques lignes.</p>', ['align' => 'center', 'size' => 17]),
                        self::spacer(8),
                        self::btn('Lire toutes les actualités', '/actualites', ['bg' => self::GOLD, 'color' => self::NAVY, 'radius' => 999, 'size' => 'lg']),
                    ]),
                    self::img(['radius' => 0]),
                    self::spacer(8),
                    self::h('À la une', ['size' => 28]),
                    NewsletterBlocks::node('news', ['title' => 'Les dernières actualités', 'count' => 3]),
                    self::section(['bg' => $paper, 'padding' => 'md'], [
                        self::cols([
                            [self::eyebrow('AGENDA', $bronze), self::h('À retenir', ['size' => 22]), self::t('<ul><li>Date et événement à remplacer</li><li>Date et événement à remplacer</li><li>Date et événement à remplacer</li></ul>', ['size' => 15])],
                            [self::eyebrow('VIE ÉTUDIANTE', $bronze), self::h('Sur le campus', ['size' => 22]), self::t('<p>Un témoignage, un club, une initiative d’étudiants : racontez-le ici.</p>', ['size' => 15])],
                        ], ['gap' => 40]),
                    ]),
                ],
            ],

            'simple' => [
                'name' => 'Newsletter simple',
                'description' => 'Un titre, un texte, un bouton : l’essentiel, sans fioritures.',
                'subject' => 'Newsletter de l’UAZ — '.$month,
                'theme' => $serif,
                'blocks' => [
                    self::h('Un titre pour votre newsletter', ['size' => 30]),
                    self::t('<p>Écrivez votre message ici. Sélectionnez des mots pour les mettre en gras, en italique ou en faire un lien.</p>'),
                    NewsletterBlocks::node('divider', ['width' => 'short']),
                    self::t('<p>Un second paragraphe, une annonce, un remerciement.</p>'),
                    self::btn('En savoir plus', '/'),
                ],
            ],
        ];
    }
}
