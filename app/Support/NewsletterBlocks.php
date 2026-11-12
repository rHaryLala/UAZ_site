<?php

namespace App\Support;

/**
 * Les blocs d'une newsletter et leur thème. Cette classe est la seule source de vérité :
 *  - l'éditeur visuel en tire la liste des blocs à ajouter et le panneau de réglages de chaque bloc (champs décrits ici) ;
 *  - le serveur s'en sert pour valider tout ce que le navigateur envoie (normalize) : types connus, champs connus, couleurs au format
 *    #rrggbb, liens http(s)/mailto/tel, longueurs bornées, texte enrichi assaini. Le navigateur n'est jamais cru sur parole.
 *
 * Deux blocs sont des CONTENEURS et portent d'autres blocs (clé « children », une liste par colonne) : « columns » (2 ou 3 colonnes) et
 * « section » (un bandeau qui empile ses blocs, avec fond et marges). Un conteneur peut en contenir un autre, une seule fois (MAX_DEPTH) :
 * un bandeau qui porte des colonnes, par exemple. Un bloc = ['id' => ..., 'type' => ..., 'data' => [...], 'children' => [[...], ...]].
 */
final class NewsletterBlocks
{
    /** Nombre maximal de blocs dans la newsletter, conteneurs et blocs imbriqués compris. */
    public const MAX_BLOCKS = 100;

    /** Un conteneur n'est accepté qu'aux niveaux 0 et 1 : au niveau 2, il n'y a plus que des blocs simples. */
    public const MAX_DEPTH = 2;

    /** Couleurs proposées d'office : celles du sceau de l'UAZ (docs/DESIGN-SYSTEM.md) et des neutres. */
    public const PALETTE = ['#232e66', '#131a3f', '#8a724b', '#e0b957', '#1d2233', '#ffffff', '#fbf9f4', '#f4f1ea', '#e1ebff', '#eef0f6', '#9aa1b5'];

    /** Proportions des colonnes, selon leur nombre. */
    public const RATIOS = [
        2 => ['1:1' => 'Égales', '1:2' => 'Étroite + large', '2:1' => 'Large + étroite', '2:3' => '40 / 60', '3:2' => '60 / 40'],
        3 => ['1:1:1' => 'Égales', '1:2:1' => 'Centre large', '2:1:1' => 'Première large', '1:1:2' => 'Dernière large'],
    ];

    private const ALIGN = ['left' => 'Gauche', 'center' => 'Centré', 'right' => 'Droite'];

    /** « Photo en fond » : où se place le texte. */
    public const HERO_LAYOUTS = ['left' => 'Panneau à gauche', 'right' => 'Panneau à droite', 'center' => 'Panneau au centre', 'bottom' => 'Bandeau en bas', 'text' => 'Texte sur la photo'];

    /** Mosaïque (« bento ») : dispositions proposées et nombre de photos de chacune. */
    public const MOSAIC_LAYOUTS = ['1-2' => 'Une grande + deux', '2-1' => 'Deux + une grande', '3' => 'Trois égales', '2x2' => 'Quatre en grille', '1-3' => 'Une large + trois'];

    public const MOSAIC_TILES = ['1-2' => 3, '2-1' => 3, '3' => 3, '2x2' => 4, '1-3' => 4];

    /** Champs « photo » de la mosaïque, dans l'ordre. */
    public const MOSAIC_MEDIA = ['media', 'media2', 'media3', 'media4'];

    /** Emplacements possibles d'un élément de l'en-tête (vide = masqué). */
    public const SLOTS = ['left' => 'Gauche', 'center' => 'Centre', 'right' => 'Droite', '' => 'Masqué'];

    /** Éléments de l'en-tête : leur clé dans le thème (header_xxx) et leur nom. */
    public const HEADER_ITEMS = ['logo' => 'Logo', 'name' => 'Nom de l\'université', 'tag' => 'Hashtag', 'social' => 'Réseaux sociaux'];

    /** Réseaux sociaux de l'en-tête (les mêmes que dans les réglages du site). */
    public const SOCIALS = ['facebook' => 'Facebook', 'instagram' => 'Instagram', 'youtube' => 'YouTube', 'linkedin' => 'LinkedIn', 'tiktok' => 'TikTok'];

    /**
     * Réglages de l'en-tête (en plus du thème) : emplacements, tailles, hashtag, adresses des réseaux sociaux. Les valeurs d'origine reproduisent
     * l'ancien en-tête (logo et nom à gauche) : une newsletter déjà écrite ou envoyée ne change pas d'aspect.
     *
     * @return array<string, string|int>
     */
    public static function headerDefaults(): array
    {
        return [
            'header_logo' => 'left', 'header_name' => 'left', 'header_tag' => '', 'header_social' => '',
            'header_tag_text' => '#UAZINFOS', 'header_height' => 88, 'header_logo_size' => 44, 'header_rule' => '1', // filet doré sous l'en-tête et petit blanc avant le contenu
        ] + array_fill_keys(array_map(fn (string $network) => 'social_'.$network, array_keys(self::SOCIALS)), '');
    }

    /**
     * La disposition de l'UAZ : hashtag à gauche, sceau au centre, réseaux sociaux à droite, sur un grand bandeau bleu (celle des modèles).
     * Les adresses des réseaux viennent des réglages du site.
     *
     * @param  array<string, string|null>  $socials  adresses par réseau
     * @return array<string, string|int>
     */
    public static function modernHeader(array $socials = []): array
    {
        $theme = ['header' => '1', 'header_color' => '#232e66', 'header_logo' => 'center', 'header_name' => '', 'header_tag' => 'left', 'header_social' => 'right', 'header_height' => 150, 'header_logo_size' => 110, 'header_rule' => ''];

        foreach (array_keys(self::SOCIALS) as $network) {
            $theme['social_'.$network] = self::socialUrl((string) ($socials[$network] ?? ''));
        }

        return $theme;
    }

    /** Une adresse de réseau social : http(s) seulement. */
    private static function socialUrl(string $value): string
    {
        $url = self::url($value);

        return str_starts_with($url, 'http') ? $url : '';
    }

    /** @return array<string, string|int> */
    public static function defaultTheme(): array
    {
        return self::headerDefaults() + [
            'background' => '#eef0f6', // fond autour de la newsletter
            'card' => '#ffffff', // fond de la newsletter
            'text' => '#1d2233',
            'accent' => '#232e66', // titres, liens
            'gold' => '#8a724b', // filets, détails
            'font' => 'serif', // titres : serif | sans
            'header' => '1', // bandeau du haut avec le logo
            'header_color' => '#131a3f',
        ];
    }

    /** @param  array<string, mixed>|null  $theme */
    public static function theme(?array $theme): array
    {
        $default = self::defaultTheme();
        $theme ??= [];
        $out = [];

        foreach (['background', 'card', 'text', 'accent', 'gold', 'header_color'] as $key) {
            $out[$key] = self::color($theme[$key] ?? null) ?? $default[$key];
        }

        $out['font'] = in_array($theme['font'] ?? null, ['serif', 'sans'], true) ? $theme['font'] : $default['font'];
        // Une chaîne vide arrive « null » (le middleware de Laravel convertit les chaînes vides en null) : c'est une VRAIE valeur (masqué, désactivé),
        // qui ne doit pas être confondue avec une clé absente (newsletter ancienne) qui garde la valeur d'origine.
        $has = fn (string $key): bool => array_key_exists($key, $theme);
        $out['header'] = $has('header') ? ($theme['header'] ? '1' : '') : $default['header'];

        foreach (array_keys(self::HEADER_ITEMS) as $item) {
            $key = 'header_'.$item;
            $slot = $has($key) ? (string) ($theme[$key] ?? '') : $default[$key];
            $out[$key] = array_key_exists($slot, self::SLOTS) ? $slot : $default[$key];
        }

        $out['header_rule'] = $has('header_rule') ? ($theme['header_rule'] ? '1' : '') : $default['header_rule'];
        $out['header_tag_text'] = array_key_exists('header_tag_text', $theme) ? trim(mb_substr(strip_tags((string) $theme['header_tag_text']), 0, 40)) : $default['header_tag_text'];
        $out['header_height'] = max(60, min(260, (int) ($theme['header_height'] ?? $default['header_height'])));
        $out['header_logo_size'] = max(32, min(160, (int) ($theme['header_logo_size'] ?? $default['header_logo_size'])));

        foreach (array_keys(self::SOCIALS) as $network) {
            $out['social_'.$network] = self::socialUrl((string) ($theme['social_'.$network] ?? ''));
        }

        return $out;
    }

    /**
     * Description des blocs. 'fields' : réglages du panneau (kind : text, select, color, media, range, toggle, url) ;
     * 'inline' : champs modifiés directement sur le plan de travail (rich = texte enrichi, sinon texte brut d'une ligne) ;
     * 'group' : rubrique du panneau « Ajouter » ; 'container' : le bloc porte d'autres blocs ; 'bleed' : à pleine largeur de la newsletter.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function types(): array
    {
        return [
            'heading' => [
                'label' => 'Titre', 'hint' => 'Un titre de section.', 'group' => 'content',
                'defaults' => ['text' => 'Un nouveau titre', 'level' => 'h2', 'align' => 'left', 'color' => '', 'size' => 28, 'upper' => ''],
                'inline' => ['text' => false],
                'fields' => [
                    ['key' => 'level', 'label' => 'Importance', 'kind' => 'select', 'options' => ['h1' => 'Grand titre', 'h2' => 'Titre', 'h3' => 'Sous-titre']],
                    ['key' => 'size', 'label' => 'Taille du texte', 'kind' => 'range', 'min' => 12, 'max' => 64, 'unit' => 'px'],
                    ['key' => 'upper', 'label' => 'Petites capitales espacées (surtitre)', 'kind' => 'toggle'],
                    ['key' => 'align', 'label' => 'Alignement', 'kind' => 'select', 'options' => self::ALIGN, 'segmented' => true],
                    ['key' => 'color', 'label' => 'Couleur', 'kind' => 'color', 'empty' => 'Couleur du thème'],
                ],
            ],
            'text' => [
                'label' => 'Texte', 'hint' => 'Un paragraphe, des listes, des liens.', 'group' => 'content',
                'defaults' => ['html' => '<p>Écrivez votre texte ici. Sélectionnez des mots pour les mettre en gras, en italique ou en faire un lien.</p>', 'align' => 'left', 'color' => '', 'size' => 16],
                'inline' => ['html' => true],
                'fields' => [
                    ['key' => 'size', 'label' => 'Taille du texte', 'kind' => 'range', 'min' => 13, 'max' => 24, 'unit' => 'px'],
                    ['key' => 'align', 'label' => 'Alignement', 'kind' => 'select', 'options' => self::ALIGN, 'segmented' => true],
                    ['key' => 'color', 'label' => 'Couleur', 'kind' => 'color', 'empty' => 'Couleur du thème'],
                ],
            ],
            'image' => [
                'label' => 'Image', 'hint' => 'Une photo de la médiathèque, n’importe où.', 'group' => 'content',
                'defaults' => ['media' => 0, 'link' => '', 'width' => 100, 'align' => 'center', 'radius' => 0, 'shadow' => ''],
                'inline' => [],
                'fields' => [
                    ['key' => 'media', 'label' => 'Image', 'kind' => 'media'],
                    ['key' => 'width', 'label' => 'Largeur', 'kind' => 'select', 'options' => [100 => 'Pleine', 75 => '3/4', 50 => 'Moitié', 33 => 'Tiers'], 'segmented' => true],
                    ['key' => 'align', 'label' => 'Alignement', 'kind' => 'select', 'options' => self::ALIGN, 'segmented' => true],
                    ['key' => 'radius', 'label' => 'Coins', 'kind' => 'select', 'options' => [0 => 'Droits', 8 => 'Arrondis', 24 => 'Très arrondis'], 'segmented' => true],
                    ['key' => 'shadow', 'label' => 'Ombre colorée décalée (rectangle derrière la photo)', 'kind' => 'color', 'empty' => 'Aucune'],
                    ['key' => 'link', 'label' => 'Lien au clic (facultatif)', 'kind' => 'url'],
                ],
            ],
            'button' => [
                'label' => 'Bouton', 'hint' => 'Un appel à l’action.', 'group' => 'content',
                'defaults' => ['label' => 'En savoir plus', 'url' => '', 'bg' => '#232e66', 'color' => '#ffffff', 'align' => 'center', 'radius' => 4, 'size' => 'md', 'full' => ''],
                'inline' => ['label' => false],
                'fields' => [
                    ['key' => 'url', 'label' => 'Adresse du lien', 'kind' => 'url'],
                    ['key' => 'bg', 'label' => 'Couleur du bouton', 'kind' => 'color'],
                    ['key' => 'color', 'label' => 'Couleur du texte', 'kind' => 'color'],
                    ['key' => 'size', 'label' => 'Taille', 'kind' => 'select', 'options' => ['md' => 'Normal', 'lg' => 'Grand'], 'segmented' => true],
                    ['key' => 'radius', 'label' => 'Coins', 'kind' => 'select', 'options' => [0 => 'Droits', 4 => 'Légers', 999 => 'Pilule'], 'segmented' => true],
                    ['key' => 'full', 'label' => 'Sur toute la largeur', 'kind' => 'toggle'],
                    ['key' => 'align', 'label' => 'Alignement', 'kind' => 'select', 'options' => self::ALIGN, 'segmented' => true],
                ],
            ],
            'quote' => [
                'label' => 'Citation', 'hint' => 'Une phrase mise en valeur.', 'group' => 'content',
                'defaults' => ['text' => 'Une phrase qui donne envie de lire la suite.', 'author' => '', 'accent' => '#8a724b'],
                'inline' => ['text' => false, 'author' => false],
                'fields' => [['key' => 'accent', 'label' => 'Couleur du filet', 'kind' => 'color']],
            ],
            'feature' => [
                'label' => 'Image et texte', 'hint' => 'Une image à côté d’un titre et d’un texte (bloc simple).', 'group' => 'content',
                'defaults' => ['media' => 0, 'side' => 'left', 'title' => 'Un titre', 'html' => '<p>Quelques lignes pour présenter le sujet.</p>', 'link' => ''],
                'inline' => ['title' => false, 'html' => true],
                'fields' => [
                    ['key' => 'media', 'label' => 'Image', 'kind' => 'media'],
                    ['key' => 'side', 'label' => 'Image à', 'kind' => 'select', 'options' => ['left' => 'Gauche', 'right' => 'Droite'], 'segmented' => true],
                    ['key' => 'link', 'label' => 'Lien (facultatif)', 'kind' => 'url'],
                ],
            ],
            'news' => [
                'label' => 'Actualités du site', 'hint' => 'Les derniers articles publiés, ajoutés tout seuls.', 'group' => 'content',
                'defaults' => ['title' => 'Les actualités de l’UAZ', 'count' => 3, 'excerpt' => '1'],
                'inline' => ['title' => false],
                'fields' => [
                    ['key' => 'count', 'label' => 'Nombre d’articles', 'kind' => 'range', 'min' => 1, 'max' => 6, 'unit' => ''],
                    ['key' => 'excerpt', 'label' => 'Afficher le résumé', 'kind' => 'toggle'],
                ],
            ],
            'hero' => [
                'label' => 'Photo en fond', 'hint' => 'Une grande photo avec du texte par-dessus : panneau coloré, badge rond, effet parallax.', 'group' => 'effects', 'bleed' => true,
                'defaults' => ['media' => 0, 'height' => 400, 'layout' => 'left', 'tone' => 'navy', 'opacity' => 88, 'parallax' => '', 'eyebrow' => 'SURTITRE', 'title' => 'Un grand titre qui accroche', 'text' => 'Deux phrases pour donner envie de lire la suite.', 'button_label' => 'En savoir plus', 'button_url' => '', 'badge' => ''],
                'inline' => ['eyebrow' => false, 'title' => false, 'text' => false, 'button_label' => false],
                'fields' => [
                    ['key' => 'media', 'label' => 'Photo de fond', 'kind' => 'media'],
                    ['key' => 'height', 'label' => 'Hauteur', 'kind' => 'range', 'min' => 240, 'max' => 560, 'unit' => 'px'],
                    ['key' => 'layout', 'label' => 'Texte', 'kind' => 'select', 'options' => self::HERO_LAYOUTS],
                    ['key' => 'tone', 'label' => 'Couleur du panneau', 'kind' => 'select', 'options' => ['navy' => 'Bleu nuit', 'blue' => 'Bleu UAZ', 'gold' => 'Or', 'paper' => 'Crème'], 'segmented' => true],
                    ['key' => 'opacity', 'label' => 'Transparence du panneau (selon la messagerie)', 'kind' => 'select', 'options' => [100 => 'Opaque', 88 => 'Légère', 72 => 'Moyenne', 55 => 'Forte'], 'segmented' => true],
                    ['key' => 'parallax', 'label' => 'Effet parallax (la photo reste fixe quand on fait défiler)', 'kind' => 'toggle'],
                    ['key' => 'badge', 'label' => 'Badge rond (texte court, facultatif)', 'kind' => 'text', 'max' => 24],
                    ['key' => 'button_url', 'label' => 'Adresse du bouton', 'kind' => 'url'],
                ],
            ],
            'mosaic' => [
                'label' => 'Mosaïque (bento)', 'hint' => 'Des photos de tailles différentes, assemblées comme une grille de magazine.', 'group' => 'effects',
                'defaults' => ['layout' => '1-2', 'height' => 340, 'gap' => 8, 'radius' => 0, 'frame' => '', 'tile_bg' => '#e1ebff', 'link' => '', 'media' => 0, 'media2' => 0, 'media3' => 0, 'media4' => 0],
                'inline' => [],
                'fields' => [
                    ['key' => 'layout', 'label' => 'Disposition', 'kind' => 'select', 'options' => self::MOSAIC_LAYOUTS],
                    ['key' => 'height', 'label' => 'Hauteur', 'kind' => 'select', 'options' => [260 => 'Compacte', 340 => 'Standard', 440 => 'Haute'], 'segmented' => true],
                    ['key' => 'gap', 'label' => 'Écart entre les photos', 'kind' => 'select', 'options' => [4 => 'Fin', 8 => 'Moyen', 14 => 'Large'], 'segmented' => true],
                    ['key' => 'radius', 'label' => 'Coins', 'kind' => 'select', 'options' => [0 => 'Droits', 8 => 'Arrondis', 18 => 'Très arrondis'], 'segmented' => true],
                    ['key' => 'frame', 'label' => 'Cadre autour de chaque photo', 'kind' => 'color', 'empty' => 'Sans cadre'],
                    ['key' => 'tile_bg', 'label' => 'Couleur d\'une case sans photo', 'kind' => 'color'],
                    ['key' => 'media', 'label' => 'Photo 1', 'kind' => 'media'],
                    ['key' => 'media2', 'label' => 'Photo 2', 'kind' => 'media'],
                    ['key' => 'media3', 'label' => 'Photo 3', 'kind' => 'media'],
                    ['key' => 'media4', 'label' => 'Photo 4 (selon la disposition)', 'kind' => 'media'],
                    ['key' => 'link', 'label' => 'Lien au clic, sur toutes les photos (facultatif)', 'kind' => 'url'],
                ],
            ],
            'columns' => [
                'label' => 'Colonnes', 'hint' => 'Deux ou trois colonnes qui reçoivent chacune leurs blocs (photo et texte côte à côte…).', 'group' => 'layout', 'container' => true,
                'defaults' => ['count' => 2, 'ratio' => '1:1', 'gap' => 24, 'valign' => 'top'],
                'inline' => [],
                'fields' => [
                    ['key' => 'count', 'label' => 'Nombre de colonnes', 'kind' => 'select', 'options' => [2 => '2', 3 => '3'], 'segmented' => true],
                    ['key' => 'ratio', 'label' => 'Proportions', 'kind' => 'select', 'options' => self::RATIOS[2] + self::RATIOS[3], 'optionsByCount' => self::RATIOS],
                    ['key' => 'gap', 'label' => 'Écart entre colonnes', 'kind' => 'select', 'options' => [0 => 'Aucun', 16 => 'Petit', 24 => 'Moyen', 40 => 'Grand'], 'segmented' => true],
                    ['key' => 'valign', 'label' => 'Alignement vertical', 'kind' => 'select', 'options' => ['top' => 'Haut', 'middle' => 'Centre'], 'segmented' => true],
                ],
            ],
            'section' => [
                'label' => 'Bandeau', 'hint' => 'Un fond de couleur qui empile ses blocs (titre, photo, texte, bouton…).', 'group' => 'layout', 'container' => true,
                'defaults' => ['bg' => '#f4f1ea', 'padding' => 'md', 'radius' => 0, 'inset' => ''],
                'inline' => [],
                'fields' => [
                    ['key' => 'bg', 'label' => 'Couleur de fond', 'kind' => 'color', 'empty' => 'Sans fond'],
                    ['key' => 'padding', 'label' => 'Marges intérieures', 'kind' => 'select', 'options' => ['none' => 'Aucune', 'sm' => 'Petites', 'md' => 'Moyennes', 'lg' => 'Grandes'], 'segmented' => true],
                    ['key' => 'radius', 'label' => 'Coins', 'kind' => 'select', 'options' => [0 => 'Droits', 8 => 'Arrondis', 20 => 'Très arrondis'], 'segmented' => true],
                    ['key' => 'inset', 'label' => 'Dans les marges de la newsletter (sinon bord à bord)', 'kind' => 'toggle'],
                ],
            ],
            'divider' => [
                'label' => 'Séparateur', 'hint' => 'Un filet horizontal.', 'group' => 'layout',
                'defaults' => ['color' => '#8a724b', 'width' => 'full'],
                'inline' => [],
                'fields' => [
                    ['key' => 'color', 'label' => 'Couleur', 'kind' => 'color'],
                    ['key' => 'width', 'label' => 'Longueur', 'kind' => 'select', 'options' => ['full' => 'Pleine', 'short' => 'Courte'], 'segmented' => true],
                ],
            ],
            'spacer' => [
                'label' => 'Espace', 'hint' => 'Un blanc, ou une bande de couleur, entre deux blocs.', 'group' => 'layout', 'bleed' => true,
                'defaults' => ['height' => 32, 'color' => ''],
                'inline' => [],
                'fields' => [
                    ['key' => 'height', 'label' => 'Hauteur', 'kind' => 'range', 'min' => 4, 'max' => 160, 'unit' => 'px'],
                    ['key' => 'color', 'label' => 'Couleur', 'kind' => 'color', 'empty' => 'Transparent'],
                ],
            ],
        ];
    }

    /** Un bloc simple prêt à poser, avec ses valeurs par défaut (sans identifiant). */
    public static function node(string $type, array $data = [], ?array $children = null): array
    {
        $types = self::types();
        $node = ['type' => $type, 'data' => array_replace($types[$type]['defaults'], $data)];

        if ($children !== null) {
            $node['children'] = $children;
        }

        return $node;
    }

    /**
     * Blocs proposés tout composés dans le panneau « Ajouter » : une mise en page courante en un clic.
     *
     * @return array<string, array{label: string, hint: string, block: array}>
     */
    public static function presets(): array
    {
        $light = ['bg' => '#f4f1ea'];
        $dark = ['bg' => '#131a3f'];

        return [
            'photo-text' => [
                'label' => 'Photo + texte', 'hint' => 'Une photo à gauche, un titre, un texte et un bouton à droite.',
                'block' => self::node('columns', ['ratio' => '1:1', 'valign' => 'middle'], [
                    [self::node('image', ['radius' => 8])],
                    [self::node('heading', ['text' => 'Un titre', 'size' => 24]), self::node('text'), self::node('button', ['align' => 'left'])],
                ]),
            ],
            'text-photo' => [
                'label' => 'Texte + photo', 'hint' => 'Le texte à gauche, la photo à droite.',
                'block' => self::node('columns', ['ratio' => '1:1', 'valign' => 'middle'], [
                    [self::node('heading', ['text' => 'Un titre', 'size' => 24]), self::node('text'), self::node('button', ['align' => 'left'])],
                    [self::node('image', ['radius' => 8])],
                ]),
            ],
            'two-photos' => [
                'label' => 'Deux photos', 'hint' => 'Deux photos côte à côte, chacune avec sa légende.',
                'block' => self::node('columns', ['ratio' => '1:1', 'gap' => 16], [
                    [self::node('image', ['radius' => 8]), self::node('text', ['html' => '<p>Légende de la première photo.</p>', 'size' => 14, 'align' => 'center'])],
                    [self::node('image', ['radius' => 8]), self::node('text', ['html' => '<p>Légende de la seconde photo.</p>', 'size' => 14, 'align' => 'center'])],
                ]),
            ],
            'three-photos' => [
                'label' => 'Trois photos', 'hint' => 'Une rangée de trois photos.',
                'block' => self::node('columns', ['count' => 3, 'ratio' => '1:1:1', 'gap' => 16], [
                    [self::node('image', ['radius' => 8])], [self::node('image', ['radius' => 8])], [self::node('image', ['radius' => 8])],
                ]),
            ],
            'figures' => [
                'label' => 'Chiffres clés', 'hint' => 'Trois grands chiffres, chacun avec sa phrase.',
                'block' => self::node('columns', ['count' => 3, 'ratio' => '1:1:1', 'gap' => 16], array_map(
                    fn (string $number, string $caption) => [self::node('heading', ['text' => $number, 'size' => 40, 'align' => 'center']), self::node('text', ['html' => '<p>'.$caption.'</p>', 'size' => 14, 'align' => 'center'])],
                    ['12', '3 500', '98 %'],
                    ['facultés et écoles', 'étudiants accueillis', 'de satisfaction'],
                )),
            ],
            'banner-light' => [
                'label' => 'Bandeau clair', 'hint' => 'Un fond crème avec titre, texte et bouton.',
                'block' => self::node('section', $light, [[
                    self::node('heading', ['text' => 'Un message important', 'align' => 'center']),
                    self::node('text', ['align' => 'center']),
                    self::node('button'),
                ]]),
            ],
            'banner-dark' => [
                'label' => 'Bandeau foncé', 'hint' => 'Un fond bleu nuit : le texte passe en clair tout seul.',
                'block' => self::node('section', $dark + ['padding' => 'lg'], [[
                    self::node('heading', ['text' => 'NEWSLETTER', 'size' => 12, 'upper' => '1', 'color' => '#e0b957', 'align' => 'center']),
                    self::node('heading', ['text' => 'Un grand titre qui accroche', 'size' => 36, 'level' => 'h1', 'align' => 'center']),
                    self::node('text', ['align' => 'center']),
                    self::node('button', ['bg' => '#e0b957', 'color' => '#131a3f', 'radius' => 999]),
                ]]),
            ],
        ];
    }

    /** Newsletter de départ : un titre, un texte et un bouton. */
    public static function starter(): array
    {
        return self::withIds(array_map(fn (string $type) => self::node($type), ['heading', 'text', 'button']));
    }

    /** Un bloc neuf, avec ses valeurs par défaut et, pour un conteneur, ses blocs de départ. */
    public static function make(string $type): array
    {
        $types = self::types();
        abort_unless(isset($types[$type]), 422);

        $children = match ($type) {
            'columns' => [[self::node('image', ['radius' => 8])], [self::node('heading', ['text' => 'Un titre', 'size' => 24]), self::node('text')]],
            'section' => [[self::node('heading', ['text' => 'Un titre', 'align' => 'center']), self::node('text', ['align' => 'center'])]],
            default => null,
        };

        return self::withIds([self::node($type, [], $children)])[0];
    }

    /**
     * Pose des identifiants à une liste de blocs (et à leurs enfants).
     *
     * @param  list<array<string, mixed>>  $blocks
     * @return list<array<string, mixed>>
     */
    public static function withIds(array $blocks): array
    {
        return array_map(function (array $block): array {
            $block = ['id' => self::newId()] + $block; // l'identifiant en premier, comme dans un bloc validé par normalize()

            if (isset($block['children'])) {
                $block['children'] = array_map(fn (array $column) => self::withIds($column), $block['children']);
            }

            return $block;
        }, $blocks);
    }

    /**
     * Valide et nettoie une liste de blocs reçue du navigateur (récursif).
     *
     * @return list<array<string, mixed>>
     */
    public static function normalize(mixed $blocks): array
    {
        $budget = self::MAX_BLOCKS;
        $seen = [];

        return self::normalizeList($blocks, 0, $budget, $seen);
    }

    /**
     * @param  array<string, true>  $seen
     * @return list<array<string, mixed>>
     */
    private static function normalizeList(mixed $blocks, int $depth, int &$budget, array &$seen): array
    {
        if (! is_array($blocks)) {
            return [];
        }

        $types = self::types();
        $out = [];

        foreach (array_values($blocks) as $block) {
            if ($budget <= 0) {
                break;
            }

            $type = is_array($block) ? ($block['type'] ?? null) : null;

            if (! is_string($type) || ! isset($types[$type])) {
                continue;
            }

            $container = ! empty($types[$type]['container']);

            if ($container && $depth >= self::MAX_DEPTH) {
                continue;
            }

            $budget--;
            $raw = is_array($block['data'] ?? null) ? $block['data'] : [];
            $data = [];

            foreach ($types[$type]['defaults'] as $key => $default) {
                // Laravel transforme les champs vides d'une requête JSON en null : pour un champ texte, null signifie « vidé » (un bouton sans libellé,
                // un surtitre supprimé…), pas « valeur absente » (qui, elle, reprend la valeur par défaut).
                $value = array_key_exists($key, $raw) && $raw[$key] === null && is_string($default) ? '' : ($raw[$key] ?? $default);
                $data[$key] = self::field($type, $key, $value, $default);
            }

            $id = is_string($block['id'] ?? null) && preg_match('/^[a-z0-9]{4,24}$/', $block['id']) && ! isset($seen[$block['id']]) ? $block['id'] : self::newId();
            $seen[$id] = true;
            $node = ['id' => $id, 'type' => $type, 'data' => $data];

            if ($container) {
                $node['data'] = self::fixContainer($type, $node['data']);
                $count = $type === 'columns' ? (int) $node['data']['count'] : 1;
                $given = is_array($block['children'] ?? null) ? array_values($block['children']) : [];
                $columns = [];

                for ($i = 0; $i < $count; $i++) {
                    $columns[$i] = is_array($given[$i] ?? null) ? array_values($given[$i]) : [];
                }

                // Moins de colonnes qu'avant : les blocs des colonnes en trop passent dans la dernière, rien n'est perdu.
                foreach (array_slice($given, $count) as $extra) {
                    $columns[$count - 1] = array_merge($columns[$count - 1], is_array($extra) ? array_values($extra) : []);
                }

                // Une boucle et non « array_map » : le compteur de blocs et les identifiants déjà vus doivent être partagés par toutes les colonnes.
                $node['children'] = [];

                foreach ($columns as $column) {
                    $node['children'][] = self::normalizeList($column, $depth + 1, $budget, $seen);
                }
            }

            $out[] = $node;
        }

        return $out;
    }

    /** Cohérence propre aux conteneurs : des proportions qui conviennent au nombre de colonnes. */
    private static function fixContainer(string $type, array $data): array
    {
        if ($type === 'columns' && ! isset(self::RATIOS[$data['count']][$data['ratio']])) {
            $data['ratio'] = array_key_first(self::RATIOS[$data['count']]);
        }

        return $data;
    }

    private static function field(string $type, string $key, mixed $value, mixed $default): mixed
    {
        $spec = collect(self::types()[$type]['fields'])->firstWhere('key', $key);
        $kind = $spec['kind'] ?? null;

        // Champs modifiés sur le plan de travail (pas dans le panneau) : texte brut ou texte enrichi.
        if ($kind === null) {
            if (in_array($key, ['html'], true)) {
                return (string) Html::clean(mb_substr((string) $value, 0, 8000));
            }

            return trim(mb_substr(strip_tags((string) $value), 0, 300));
        }

        return match ($kind) {
            'color' => self::color($value) ?? (($default === '') ? '' : $default),
            'url' => self::url((string) $value),
            'media' => max(0, (int) $value),
            'text' => trim(mb_substr(strip_tags((string) $value), 0, (int) ($spec['max'] ?? 60))),
            'range' => max($spec['min'], min($spec['max'], (int) $value)),
            'toggle' => $value ? '1' : '',
            'select' => is_scalar($value) && array_key_exists($value, $spec['options']) ? (is_int($default) ? (int) $value : $value) : $default,
            default => $default,
        };
    }

    /**
     * Tous les blocs de l'arbre, conteneurs compris, dans l'ordre de lecture.
     *
     * @param  list<array<string, mixed>>  $blocks
     * @return list<array<string, mixed>>
     */
    public static function flatten(array $blocks): array
    {
        $all = [];

        foreach ($blocks as $block) {
            $all[] = $block;

            foreach ($block['children'] ?? [] as $column) {
                array_push($all, ...self::flatten($column));
            }
        }

        return $all;
    }

    /**
     * Identifiants des images citées, partout dans l'arbre.
     *
     * @param  list<array<string, mixed>>  $blocks
     * @return list<int>
     */
    public static function mediaIds(array $blocks): array
    {
        $types = self::types();
        $ids = [];

        // Tous les champs « photo » de chaque bloc (une mosaïque en a plusieurs).
        foreach (self::flatten($blocks) as $block) {
            foreach ($types[$block['type']]['fields'] ?? [] as $spec) {
                if (($spec['kind'] ?? null) === 'media' && ! empty($block['data'][$spec['key']])) {
                    $ids[] = (int) $block['data'][$spec['key']];
                }
            }
        }

        return array_values(array_unique($ids));
    }

    public static function color(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtolower($value) : null;
    }

    /** Lien autorisé : http(s), mailto, tel ; sinon vide. Une adresse du site écrite « /actualites » devient absolue. */
    public static function url(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (str_starts_with($value, '/') && ! str_starts_with($value, '//')) {
            return url($value);
        }

        return preg_match('#^(https?://|mailto:|tel:)[^\s<>"\']{1,480}$#i', $value) ? $value : '';
    }

    public static function newId(): string
    {
        return substr(bin2hex(random_bytes(6)), 0, 10);
    }
}
