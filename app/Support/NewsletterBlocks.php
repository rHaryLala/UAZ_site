<?php

namespace App\Support;

/**
 * Les blocs d'une lettre d'information et leur thème. Cette classe est la seule source de vérité :
 *  - l'éditeur visuel en tire la liste des blocs à ajouter et le panneau de réglages de chaque bloc (champs décrits ici) ;
 *  - le serveur s'en sert pour valider tout ce que le navigateur envoie (normalize) : types connus, champs connus, couleurs au format
 *    #rrggbb, liens http(s)/mailto/tel, longueurs bornées, texte enrichi assaini. Le navigateur n'est jamais cru sur parole.
 */
final class NewsletterBlocks
{
    public const MAX_BLOCKS = 60;

    /** Couleurs proposées d'office : celles du sceau de l'UAZ (docs/DESIGN-SYSTEM.md) et des neutres. */
    public const PALETTE = ['#232e66', '#131a3f', '#8a724b', '#e0b957', '#1d2233', '#ffffff', '#fbf9f4', '#e1ebff', '#eef0f6', '#9aa1b5'];

    private const ALIGN = ['left' => 'Gauche', 'center' => 'Centré', 'right' => 'Droite'];

    /** @return array<string, string> */
    public static function defaultTheme(): array
    {
        return [
            'background' => '#eef0f6', // fond autour de la lettre
            'card' => '#ffffff', // fond de la lettre
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
        $out['header'] = ($theme['header'] ?? $default['header']) ? '1' : '';

        return $out;
    }

    /**
     * Description des blocs. 'fields' : réglages du panneau (kind : text, select, color, media, range, toggle, url) ;
     * 'inline' : champs modifiés directement sur le plan de travail (rich = texte enrichi, sinon texte brut d'une ligne).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function types(): array
    {
        return [
            'heading' => [
                'label' => 'Titre', 'hint' => 'Un titre de section.',
                'defaults' => ['text' => 'Un nouveau titre', 'level' => 'h2', 'align' => 'left', 'color' => '', 'size' => 28],
                'inline' => ['text' => false],
                'fields' => [
                    ['key' => 'level', 'label' => 'Importance', 'kind' => 'select', 'options' => ['h1' => 'Grand titre', 'h2' => 'Titre', 'h3' => 'Sous-titre']],
                    ['key' => 'size', 'label' => 'Taille du texte', 'kind' => 'range', 'min' => 16, 'max' => 56, 'unit' => 'px'],
                    ['key' => 'align', 'label' => 'Alignement', 'kind' => 'select', 'options' => self::ALIGN, 'segmented' => true],
                    ['key' => 'color', 'label' => 'Couleur', 'kind' => 'color', 'empty' => 'Couleur du thème'],
                ],
            ],
            'text' => [
                'label' => 'Texte', 'hint' => 'Un paragraphe, des listes, des liens.',
                'defaults' => ['html' => '<p>Écrivez votre texte ici. Sélectionnez des mots pour les mettre en gras, en italique ou en faire un lien.</p>', 'align' => 'left', 'color' => '', 'size' => 16],
                'inline' => ['html' => true],
                'fields' => [
                    ['key' => 'size', 'label' => 'Taille du texte', 'kind' => 'range', 'min' => 13, 'max' => 24, 'unit' => 'px'],
                    ['key' => 'align', 'label' => 'Alignement', 'kind' => 'select', 'options' => self::ALIGN, 'segmented' => true],
                    ['key' => 'color', 'label' => 'Couleur', 'kind' => 'color', 'empty' => 'Couleur du thème'],
                ],
            ],
            'image' => [
                'label' => 'Image', 'hint' => 'Une photo de la médiathèque.',
                'defaults' => ['media' => 0, 'link' => '', 'width' => 100, 'align' => 'center', 'radius' => 0],
                'inline' => [],
                'fields' => [
                    ['key' => 'media', 'label' => 'Image', 'kind' => 'media'],
                    ['key' => 'width', 'label' => 'Largeur', 'kind' => 'select', 'options' => [100 => 'Pleine', 75 => '3/4', 50 => 'Moitié'], 'segmented' => true],
                    ['key' => 'align', 'label' => 'Alignement', 'kind' => 'select', 'options' => self::ALIGN, 'segmented' => true],
                    ['key' => 'radius', 'label' => 'Coins', 'kind' => 'select', 'options' => [0 => 'Droits', 8 => 'Arrondis', 24 => 'Très arrondis'], 'segmented' => true],
                    ['key' => 'link', 'label' => 'Lien au clic (facultatif)', 'kind' => 'url'],
                ],
            ],
            'button' => [
                'label' => 'Bouton', 'hint' => 'Un appel à l’action.',
                'defaults' => ['label' => 'En savoir plus', 'url' => '', 'bg' => '#232e66', 'color' => '#ffffff', 'align' => 'center', 'radius' => 4, 'size' => 'md'],
                'inline' => ['label' => false],
                'fields' => [
                    ['key' => 'url', 'label' => 'Adresse du lien', 'kind' => 'url'],
                    ['key' => 'bg', 'label' => 'Couleur du bouton', 'kind' => 'color'],
                    ['key' => 'color', 'label' => 'Couleur du texte', 'kind' => 'color'],
                    ['key' => 'size', 'label' => 'Taille', 'kind' => 'select', 'options' => ['md' => 'Normal', 'lg' => 'Grand'], 'segmented' => true],
                    ['key' => 'radius', 'label' => 'Coins', 'kind' => 'select', 'options' => [0 => 'Droits', 4 => 'Légers', 999 => 'Pilule'], 'segmented' => true],
                    ['key' => 'align', 'label' => 'Alignement', 'kind' => 'select', 'options' => self::ALIGN, 'segmented' => true],
                ],
            ],
            'quote' => [
                'label' => 'Citation', 'hint' => 'Une phrase mise en valeur.',
                'defaults' => ['text' => 'Une phrase qui donne envie de lire la suite.', 'author' => '', 'accent' => '#8a724b'],
                'inline' => ['text' => false, 'author' => false],
                'fields' => [['key' => 'accent', 'label' => 'Couleur du filet', 'kind' => 'color']],
            ],
            'feature' => [
                'label' => 'Image et texte', 'hint' => 'Une image à côté d’un titre et d’un texte.',
                'defaults' => ['media' => 0, 'side' => 'left', 'title' => 'Un titre', 'html' => '<p>Quelques lignes pour présenter le sujet.</p>', 'link' => ''],
                'inline' => ['title' => false, 'html' => true],
                'fields' => [
                    ['key' => 'media', 'label' => 'Image', 'kind' => 'media'],
                    ['key' => 'side', 'label' => 'Image à', 'kind' => 'select', 'options' => ['left' => 'Gauche', 'right' => 'Droite'], 'segmented' => true],
                    ['key' => 'link', 'label' => 'Lien (facultatif)', 'kind' => 'url'],
                ],
            ],
            'news' => [
                'label' => 'Actualités du site', 'hint' => 'Les derniers articles publiés, ajoutés tout seuls.',
                'defaults' => ['title' => 'Les actualités de l’UAZ', 'count' => 3, 'excerpt' => '1'],
                'inline' => ['title' => false],
                'fields' => [
                    ['key' => 'count', 'label' => 'Nombre d’articles', 'kind' => 'range', 'min' => 1, 'max' => 6, 'unit' => ''],
                    ['key' => 'excerpt', 'label' => 'Afficher le résumé', 'kind' => 'toggle'],
                ],
            ],
            'divider' => [
                'label' => 'Séparateur', 'hint' => 'Un filet horizontal.',
                'defaults' => ['color' => '#8a724b', 'width' => 'full'],
                'inline' => [],
                'fields' => [
                    ['key' => 'color', 'label' => 'Couleur', 'kind' => 'color'],
                    ['key' => 'width', 'label' => 'Longueur', 'kind' => 'select', 'options' => ['full' => 'Pleine', 'short' => 'Courte'], 'segmented' => true],
                ],
            ],
            'spacer' => [
                'label' => 'Espace', 'hint' => 'Un blanc entre deux blocs.',
                'defaults' => ['height' => 32],
                'inline' => [],
                'fields' => [['key' => 'height', 'label' => 'Hauteur', 'kind' => 'range', 'min' => 8, 'max' => 120, 'unit' => 'px']],
            ],
        ];
    }

    /** Lettre de départ : un titre, un texte et un bouton. */
    public static function starter(): array
    {
        return array_map(fn (string $type) => self::make($type), ['heading', 'text', 'button']);
    }

    /** Un bloc neuf, avec ses valeurs par défaut. */
    public static function make(string $type): array
    {
        $types = self::types();
        abort_unless(isset($types[$type]), 422);

        return ['id' => self::newId(), 'type' => $type, 'data' => $types[$type]['defaults']];
    }

    /**
     * Valide et nettoie une liste de blocs reçue du navigateur.
     *
     * @return list<array{id: string, type: string, data: array<string, mixed>}>
     */
    public static function normalize(mixed $blocks): array
    {
        if (! is_array($blocks)) {
            return [];
        }

        $types = self::types();
        $out = [];

        foreach (array_slice(array_values($blocks), 0, self::MAX_BLOCKS) as $block) {
            $type = is_array($block) ? ($block['type'] ?? null) : null;

            if (! is_string($type) || ! isset($types[$type])) {
                continue;
            }

            $raw = is_array($block['data'] ?? null) ? $block['data'] : [];
            $data = [];

            foreach ($types[$type]['defaults'] as $key => $default) {
                $data[$key] = self::field($type, $key, $raw[$key] ?? $default, $default);
            }

            $id = is_string($block['id'] ?? null) && preg_match('/^[a-z0-9]{4,24}$/', $block['id']) ? $block['id'] : self::newId();
            $out[] = ['id' => $id, 'type' => $type, 'data' => $data];
        }

        return $out;
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
            'range' => max($spec['min'], min($spec['max'], (int) $value)),
            'toggle' => $value ? '1' : '',
            'select' => array_key_exists($value, $spec['options']) ? (is_int($default) ? (int) $value : $value) : $default,
            default => $default,
        };
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
