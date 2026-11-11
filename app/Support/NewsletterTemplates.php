<?php

namespace App\Support;

use App\Models\MediaAsset;

/**
 * Modèles de lettres proposés à la création (et dans l'éditeur) : une mise en page moderne déjà composée, à adapter. Chaque modèle donne un thème,
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

    /** Pose les dernières photos de la médiathèque sur les blocs « image » encore vides (en boucle s'il y en a peu). */
    private static function fillPhotos(array $blocks): array
    {
        $photos = MediaAsset::query()
            ->whereHas('media', fn ($media) => $media->where('mime_type', 'like', 'image/%'))
            ->latest('id')->limit(12)->pluck('id')->all();

        if ($photos === []) {
            return $blocks;
        }

        $next = 0;
        $walk = function (array $list) use (&$walk, &$next, $photos): array {
            foreach ($list as &$block) {
                if ($block['type'] === 'image' && empty($block['data']['media'])) {
                    $block['data']['media'] = $photos[$next++ % count($photos)];
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

    /** @return array<string, array{name: string, description: string, subject: string, theme: array<string, string>, blocks: list<array<string, mixed>>}> */
    private static function catalog(): array
    {
        $month = ucfirst(now()->translatedFormat('F Y'));
        $sans = ['font' => 'sans', 'header_color' => self::NAVY];

        return [
            'news' => [
                'name' => 'Actualités du mois',
                'description' => 'Un grand titre sur fond bleu nuit, une photo, les dernières actualités du site et un agenda.',
                'subject' => 'Les nouvelles de l’UAZ — '.$month,
                'theme' => $sans,
                'blocks' => [
                    self::section(['bg' => self::NAVY, 'padding' => 'lg'], [
                        self::eyebrow('LETTRE D’INFORMATION · '.mb_strtoupper($month), self::GOLD, 'center'),
                        self::h('Les nouvelles de l’UAZ', ['size' => 38, 'level' => 'h1', 'align' => 'center']),
                        self::t('<p>Des nouvelles du campus, de nos étudiants et de nos enseignants, racontées en quelques lignes.</p>', ['align' => 'center', 'size' => 17]),
                        self::spacer(8),
                        self::btn('Lire toutes les actualités', '/actualites', ['bg' => self::GOLD, 'color' => self::NAVY, 'radius' => 999, 'size' => 'lg']),
                    ]),
                    self::img(['radius' => 0]),
                    self::spacer(8),
                    self::h('À la une', ['size' => 28]),
                    NewsletterBlocks::node('news', ['title' => 'Les dernières actualités', 'count' => 3]),
                    self::section(['bg' => '#f4f1ea', 'padding' => 'md'], [
                        self::cols([
                            [self::eyebrow('AGENDA', '#8a724b'), self::h('À retenir', ['size' => 22]), self::t('<ul><li>Date et événement à remplacer</li><li>Date et événement à remplacer</li><li>Date et événement à remplacer</li></ul>', ['size' => 15])],
                            [self::eyebrow('VIE ÉTUDIANTE', '#8a724b'), self::h('Sur le campus', ['size' => 22]), self::t('<p>Un témoignage, un club, une initiative d’étudiants : racontez-le ici.</p>', ['size' => 15])],
                        ], ['gap' => 40]),
                    ]),
                ],
            ],

            'event' => [
                'name' => 'Événement',
                'description' => 'Une grande affiche, la date, le lieu et l’entrée en trois colonnes, un programme et un bouton d’inscription.',
                'subject' => 'Vous êtes invité(e) : titre de l’événement',
                'theme' => $sans,
                'blocks' => [
                    self::section(['bg' => '', 'padding' => 'none'], [self::img(['radius' => 0])]),
                    self::section(['bg' => '#f4f1ea', 'padding' => 'lg'], [
                        self::eyebrow('ÉVÉNEMENT', '#8a724b', 'center'),
                        self::h('Le titre de l’événement', ['size' => 38, 'level' => 'h1', 'align' => 'center']),
                        self::t('<p>Deux ou trois phrases qui donnent envie de venir : le thème, les invités, ce que le public y trouvera.</p>', ['align' => 'center', 'size' => 17]),
                        self::spacer(6),
                        self::cols([
                            [self::eyebrow('DATE', '#8a724b', 'center'), self::t('<p><strong>Samedi 14 mars</strong><br>de 9 h à 17 h</p>', ['align' => 'center'])],
                            [self::eyebrow('LIEU', '#8a724b', 'center'), self::t('<p><strong>Campus de l’UAZ</strong><br>Antsirabe</p>', ['align' => 'center'])],
                            [self::eyebrow('ENTRÉE', '#8a724b', 'center'), self::t('<p><strong>Libre</strong><br>sur inscription</p>', ['align' => 'center'])],
                        ], ['gap' => 16]),
                        self::spacer(10),
                        self::btn('Je m’inscris', '/evenements', ['radius' => 999, 'size' => 'lg']),
                    ]),
                    self::h('Au programme', ['size' => 28]),
                    self::cols([
                        [self::img()],
                        [self::t('<p><strong>9 h</strong> — Accueil</p><p><strong>10 h</strong> — Première intervention</p><p><strong>12 h</strong> — Déjeuner partagé</p><p><strong>14 h</strong> — Ateliers</p>', ['size' => 16])],
                    ], ['ratio' => '1:1', 'valign' => 'middle']),
                    NewsletterBlocks::node('divider', ['width' => 'short']),
                    self::t('<p>Une question ? Écrivez-nous en répondant simplement à ce message.</p>', ['align' => 'center', 'size' => 14]),
                ],
            ],

            'rector' => [
                'name' => 'Mot du recteur',
                'description' => 'Un courrier éditorial : portrait et texte en deux colonnes, une citation, une signature.',
                'subject' => 'Le mot du recteur — '.$month,
                'theme' => ['font' => 'serif'],
                'blocks' => [
                    self::spacer(6),
                    self::eyebrow('LE MOT DU RECTEUR', '#8a724b'),
                    self::h('Chers amis de l’UAZ,', ['size' => 36, 'level' => 'h1']),
                    self::cols([
                        [self::img(['radius' => 12])],
                        [
                            self::t('<p>Commencez ici votre message : ce qui a marqué le mois, ce que vous attendez de la communauté universitaire.</p><p>Un deuxième paragraphe pour développer, remercier ou annoncer un projet.</p>', ['size' => 17]),
                        ],
                    ], ['ratio' => '1:2', 'gap' => 24]),
                    NewsletterBlocks::node('quote', ['text' => 'Une phrase forte que vous voulez que chacun retienne.', 'author' => 'Le recteur', 'accent' => '#8a724b']),
                    self::t('<p>Que Dieu vous bénisse,</p><p><strong>Prénom Nom</strong><br>Recteur de l’Université Adventiste Zurcher</p>'),
                    NewsletterBlocks::node('divider', ['width' => 'short']),
                    self::btn('Découvrir l’UAZ', '/a-propos', ['bg' => '#8a724b', 'radius' => 4]),
                ],
            ],

            'admissions' => [
                'name' => 'Admissions',
                'description' => 'Un appel à candidater : chiffres clés, trois étapes pour postuler et un bouton pleine largeur.',
                'subject' => 'Les admissions sont ouvertes à l’UAZ',
                'theme' => $sans + ['accent' => '#232e66'],
                'blocks' => [
                    self::section(['bg' => '#232e66', 'padding' => 'lg'], [
                        self::eyebrow('ADMISSIONS '.now()->format('Y'), self::GOLD, 'center'),
                        self::h('Votre avenir commence ici', ['size' => 40, 'level' => 'h1', 'align' => 'center']),
                        self::t('<p>Licences, masters et formations professionnelles : trouvez le parcours qui vous ressemble.</p>', ['align' => 'center', 'size' => 17]),
                        self::spacer(8),
                        self::btn('Voir les formations', '/formations', ['bg' => self::GOLD, 'color' => self::NAVY, 'radius' => 999, 'size' => 'lg']),
                    ]),
                    self::spacer(10),
                    NewsletterBlocks::presets()['figures']['block'],
                    self::spacer(10),
                    self::img(['radius' => 0]),
                    self::section(['bg' => '#e1ebff', 'padding' => 'md'], [
                        self::h('Comment postuler', ['size' => 28, 'align' => 'center']),
                        self::cols([
                            [self::h('1', ['size' => 36, 'color' => '#8a724b', 'align' => 'center']), self::h('Le dossier', ['size' => 18, 'align' => 'center']), self::t('<p>Rassemblez vos pièces et choisissez votre formation.</p>', ['size' => 14, 'align' => 'center'])],
                            [self::h('2', ['size' => 36, 'color' => '#8a724b', 'align' => 'center']), self::h('L’entretien', ['size' => 18, 'align' => 'center']), self::t('<p>Rencontrez l’équipe pédagogique, sur place ou en ligne.</p>', ['size' => 14, 'align' => 'center'])],
                            [self::h('3', ['size' => 36, 'color' => '#8a724b', 'align' => 'center']), self::h('L’inscription', ['size' => 18, 'align' => 'center']), self::t('<p>Confirmez votre place et préparez la rentrée.</p>', ['size' => 14, 'align' => 'center'])],
                        ], ['gap' => 16]),
                        self::spacer(6),
                        self::btn('Commencer ma candidature', '/admissions', ['full' => '1', 'radius' => 6, 'size' => 'lg']),
                    ]),
                ],
            ],

            'gallery' => [
                'name' => 'Galerie souvenirs',
                'description' => 'Surtout des photos : deux rangées en colonnes avec légendes, et un bouton vers la galerie.',
                'subject' => 'En images : le campus ce mois-ci',
                'theme' => $sans,
                'blocks' => [
                    self::spacer(6),
                    self::eyebrow('EN IMAGES', '#8a724b', 'center'),
                    self::h('Souvenirs de campus', ['size' => 36, 'level' => 'h1', 'align' => 'center']),
                    self::t('<p>Les moments qui ont rythmé le mois, vus par ceux qui les ont vécus.</p>', ['align' => 'center']),
                    self::img(['radius' => 12]),
                    NewsletterBlocks::presets()['two-photos']['block'],
                    NewsletterBlocks::presets()['three-photos']['block'],
                    self::spacer(6),
                    self::btn('Voir toute la galerie', '/galerie', ['radius' => 999, 'size' => 'lg']),
                    self::spacer(6),
                ],
            ],

            'support' => [
                'name' => 'Soutenir l’UAZ',
                'description' => 'Un appel à la générosité : bandeau chaleureux, citation d’un bénéficiaire et projets en cours.',
                'subject' => 'Ensemble, construisons l’avenir de l’UAZ',
                'theme' => ['font' => 'serif'],
                'blocks' => [
                    self::section(['bg' => '#8a724b', 'padding' => 'lg'], [
                        self::eyebrow('SOUTENIR L’UAZ', '#ffffff', 'center'),
                        self::h('Ensemble, bâtissons l’avenir', ['size' => 38, 'level' => 'h1', 'align' => 'center']),
                        self::t('<p>Chaque don aide un étudiant à poursuivre ses études et fait grandir le campus.</p>', ['align' => 'center', 'size' => 17]),
                        self::spacer(8),
                        self::btn('Faire un don', '/soutenir', ['bg' => '#ffffff', 'color' => self::NAVY, 'radius' => 999, 'size' => 'lg']),
                    ]),
                    self::spacer(8),
                    self::cols([
                        [self::img(['radius' => 12])],
                        [self::eyebrow('UN PROJET EN COURS', '#8a724b'), self::h('Le nom du projet', ['size' => 26]), self::t('<p>Expliquez en deux phrases ce que le projet change pour les étudiants et ce qu’il reste à réunir.</p>'), self::btn('Découvrir le projet', '/soutenir', ['align' => 'left', 'bg' => '#8a724b'])],
                    ], ['valign' => 'middle', 'gap' => 24]),
                    NewsletterBlocks::node('quote', ['text' => 'Grâce à vous, j’ai pu rester à l’université et réaliser mon rêve.', 'author' => 'Un étudiant de l’UAZ', 'accent' => '#8a724b']),
                    self::t('<p>Merci de votre confiance et de votre générosité.</p>', ['align' => 'center']),
                ],
            ],

            'simple' => [
                'name' => 'Lettre simple',
                'description' => 'Un titre, un texte, un bouton : l’essentiel, sans fioritures.',
                'subject' => 'Lettre d’information de l’UAZ — '.$month,
                'theme' => [],
                'blocks' => [
                    self::h('Un titre pour votre lettre', ['size' => 30]),
                    self::t('<p>Écrivez votre message ici. Sélectionnez des mots pour les mettre en gras, en italique ou en faire un lien.</p>'),
                    NewsletterBlocks::node('divider', ['width' => 'short']),
                    self::t('<p>Un second paragraphe, une annonce, un remerciement.</p>'),
                    self::btn('En savoir plus', '/'),
                ],
            ],
        ];
    }
}
