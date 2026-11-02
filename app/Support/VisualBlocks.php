<?php

namespace App\Support;

use App\Models\MediaAsset;
use App\Models\Page;
use Illuminate\Validation\ValidationException;

/**
 * Description unique des blocs de page pour l'éditeur visuel : types, champs, valeurs par défaut.
 * Elle sert à trois choses : afficher le formulaire de réglages (JSON envoyé au navigateur), valider et nettoyer ce que le navigateur
 * renvoie (jamais de confiance dans le client), et créer un bloc neuf. Les types doivent rester identiques à App\Filament\Support\PageBlocks
 * (un test le vérifie) : le formulaire classique de l'administration et l'éditeur visuel lisent les mêmes données.
 */
final class VisualBlocks
{
    private const URL_PATTERN = '#^(/|https?://|mailto:|tel:)#i';

    /**
     * Pages dont la mise en page est dessinée sur mesure (vues dédiées) : leur contenu ne vient pas des blocs, l'éditeur visuel ne s'y applique pas.
     * Voir App\Http\Controllers\PageController.
     */
    public const BESPOKE_KEYS = [
        'home', 'about', 'support', 'student-life-library', 'student-life-cafeteria', 'student-life-dorms',
        'admissions-licence', 'admissions-master', 'admissions-fees', 'research', 'timetables',
    ];

    public static function isBespoke(Page $page): bool
    {
        return in_array($page->key, self::BESPOKE_KEYS, true);
    }

    /** @return array<string, array<string, mixed>> */
    public static function types(): array
    {
        $text = fn (string $name, string $label, int $max = 200, array $extra = []) => ['name' => $name, 'type' => 'text', 'label' => $label, 'max' => $max] + $extra;
        $area = fn (string $name, string $label, int $max = 600, array $extra = []) => ['name' => $name, 'type' => 'textarea', 'label' => $label, 'max' => $max] + $extra;
        $media = fn (string $name, string $label, array $extra = []) => ['name' => $name, 'type' => 'media', 'label' => $label] + $extra;
        $url = fn (string $name, string $label, array $extra = []) => ['name' => $name, 'type' => 'url', 'label' => $label, 'max' => 500] + $extra;

        return [
            'rich_text' => [
                'label' => 'Texte', 'hint' => 'Titre et paragraphes mis en forme', 'icon' => '<path d="M5 6h14M5 10h14M5 14h9M5 18h6"/>',
                'fields' => [
                    $text('heading', 'Titre (facultatif)'),
                    ['name' => 'content', 'type' => 'rich', 'label' => 'Texte', 'required' => true, 'max' => 60000],
                    ['name' => 'width', 'type' => 'select', 'label' => 'Largeur', 'options' => ['normal' => 'Colonne de lecture', 'wide' => 'Large'], 'default' => 'normal'],
                ],
                'default' => ['heading' => '', 'content' => '<p>Cliquez ici pour écrire votre texte.</p>', 'width' => 'normal'],
            ],
            'image' => [
                'label' => 'Image', 'hint' => 'Une photo avec légende', 'icon' => '<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="10" r="1.5"/><path d="M4 17l5-4 4 3 3-2 4 3"/>',
                'fields' => [
                    $media('image_id', 'Image', ['required' => true]),
                    $text('caption', 'Légende (facultatif)', 300),
                    ['name' => 'size', 'type' => 'select', 'label' => 'Taille', 'options' => ['normal' => 'Normale', 'wide' => 'Large', 'full' => 'Pleine largeur'], 'default' => 'wide'],
                ],
                'default' => ['image_id' => null, 'caption' => '', 'size' => 'wide'],
            ],
            'gallery' => [
                'label' => 'Galerie', 'hint' => 'Plusieurs photos en grille', 'icon' => '<rect x="3" y="3" width="8" height="8" rx="1"/><rect x="13" y="3" width="8" height="8" rx="1"/><rect x="3" y="13" width="8" height="8" rx="1"/><rect x="13" y="13" width="8" height="8" rx="1"/>',
                'fields' => [
                    $text('title', 'Titre (facultatif)'),
                    ['name' => 'images', 'type' => 'repeater', 'label' => 'Photos', 'item' => 'Photo', 'min' => 1, 'max' => 60, 'itemLabel' => 'caption', 'fields' => [
                        $media('image_id', 'Photo', ['required' => true]),
                        $text('caption', 'Légende (facultatif)', 300),
                    ]],
                ],
                'default' => ['title' => '', 'images' => []],
            ],
            'quote' => [
                'label' => 'Citation', 'hint' => 'Une phrase mise en avant', 'icon' => '<path d="M7 17c-2 0-3-1.5-3-3.5S5.5 9 8 8M17 17c-2 0-3-1.5-3-3.5S15.5 9 18 8"/>',
                'fields' => [
                    $area('text', 'Citation', 600, ['required' => true]),
                    $text('author', 'Auteur', 150),
                    $text('role', 'Fonction', 150),
                ],
                'default' => ['text' => 'Votre citation ici.', 'author' => '', 'role' => ''],
            ],
            'cta' => [
                'label' => 'Appel à l\'action', 'hint' => 'Un grand bandeau avec un bouton', 'icon' => '<rect x="3" y="8" width="18" height="8" rx="4"/><path d="M8 12h8"/>',
                'fields' => [
                    $text('title', 'Titre', 200, ['required' => true]),
                    $area('text', 'Texte', 400),
                    $text('button_label', 'Texte du bouton', 60, ['required' => true]),
                    $url('button_url', 'Lien du bouton', ['required' => true, 'help' => 'Adresse complète (https://…) ou chemin du site (/contact).']),
                ],
                'default' => ['title' => 'Rejoignez-nous', 'text' => '', 'button_label' => 'En savoir plus', 'button_url' => '/contact'],
            ],
            'key_figures' => [
                'label' => 'Chiffres clés', 'hint' => 'Jusqu\'à six chiffres qui comptent', 'icon' => '<path d="M5 19V9M12 19V5M19 19v-7"/>',
                'fields' => [
                    $text('title', 'Titre (facultatif)'),
                    ['name' => 'items', 'type' => 'repeater', 'label' => 'Chiffres', 'item' => 'Chiffre', 'min' => 1, 'max' => 6, 'itemLabel' => 'label', 'fields' => [
                        ['name' => 'value', 'type' => 'number', 'label' => 'Valeur', 'required' => true],
                        $text('suffix', 'Suffixe (+, %, ha…)', 10),
                        $text('label', 'Libellé', 80, ['required' => true]),
                    ]],
                ],
                'default' => ['title' => '', 'items' => [['value' => 100, 'suffix' => '+', 'label' => 'Libellé']]],
            ],
            'card_list' => [
                'label' => 'Liste de cartes', 'hint' => 'Des cartes avec image et lien', 'icon' => '<rect x="3" y="4" width="8" height="7" rx="1"/><rect x="13" y="4" width="8" height="7" rx="1"/><rect x="3" y="13" width="8" height="7" rx="1"/><rect x="13" y="13" width="8" height="7" rx="1"/>',
                'fields' => [
                    $text('title', 'Titre (facultatif)'),
                    $area('intro', 'Introduction (facultatif)', 400),
                    ['name' => 'cards', 'type' => 'repeater', 'label' => 'Cartes', 'item' => 'Carte', 'min' => 1, 'max' => 30, 'itemLabel' => 'title', 'fields' => [
                        $text('title', 'Titre', 120, ['required' => true]),
                        $area('text', 'Texte', 400),
                        $media('image_id', 'Image (facultatif)'),
                        $url('url', 'Lien (facultatif)'),
                        $text('link_label', 'Texte du lien', 60),
                    ]],
                ],
                'default' => ['title' => '', 'intro' => '', 'cards' => [['title' => 'Nouvelle carte', 'text' => '', 'image_id' => null, 'url' => '', 'link_label' => '']]],
            ],
            'download_list' => [
                'label' => 'Téléchargements', 'hint' => 'Une liste de documents', 'icon' => '<path d="M12 4v10M8 10l4 4 4-4M5 19h14"/>',
                'fields' => [
                    $text('title', 'Titre (facultatif)'),
                    $area('intro', 'Introduction (facultatif)', 400),
                    ['name' => 'items', 'type' => 'repeater', 'label' => 'Documents', 'item' => 'Document', 'min' => 1, 'max' => 60, 'itemLabel' => 'title', 'fields' => [
                        $text('title', 'Titre du document', 160, ['required' => true]),
                        $area('text', 'Description (facultatif)', 400),
                        $media('file_id', 'Fichier de la médiathèque', ['any' => true]),
                        ['name' => 'url', 'type' => 'url', 'label' => 'Adresse du fichier (si hors médiathèque)', 'max' => 500, 'schemes' => 'web'],
                    ]],
                ],
                'default' => ['title' => '', 'intro' => '', 'items' => [['title' => 'Nouveau document', 'text' => '', 'file_id' => null, 'url' => '']]],
            ],
        ];
    }

    /** Valeurs d'un bloc neuf. */
    public static function fresh(string $type): ?array
    {
        $definition = self::types()[$type] ?? null;

        return $definition ? ['type' => $type, 'data' => $definition['default']] : null;
    }

    /**
     * Valide et nettoie une liste de blocs envoyée par le navigateur : types connus, champs connus seulement, longueurs, liens autorisés,
     * HTML assaini, images existantes. En mode souple (brouillon), les champs obligatoires peuvent rester vides ; en mode strict (publication), non.
     *
     * @param  array<int, mixed>  $blocks
     * @return list<array{type: string, data: array<string, mixed>}>
     *
     * @throws ValidationException
     */
    public static function normalize(array $blocks, bool $strict = false): array
    {
        $types = self::types();
        $errors = [];
        $clean = [];

        if (count($blocks) > 80) {
            throw ValidationException::withMessages(['blocks' => 'Une page ne peut pas contenir plus de 80 blocs.']);
        }

        foreach (array_values($blocks) as $i => $block) {
            $type = is_array($block) ? ($block['type'] ?? null) : null;

            if (! is_string($type) || ! isset($types[$type])) {
                $errors["blocks.$i.type"] = 'Type de bloc inconnu.';

                continue;
            }

            $data = self::cleanFields($types[$type]['fields'], is_array($block['data'] ?? null) ? $block['data'] : [], "blocks.$i.data", $strict, $errors);
            $clean[] = ['type' => $type, 'data' => $data];
        }

        // Les images référencées doivent exister (une seule requête).
        $ids = self::mediaIds($clean);
        if ($ids && ($missing = array_diff($ids, MediaAsset::query()->whereIn('id', $ids)->pluck('id')->all()))) {
            $errors['blocks'] = 'Une image choisie n\'existe plus dans la médiathèque ('.implode(', ', $missing).').';
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $clean;
    }

    /**
     * Identifiants des fichiers de la médiathèque utilisés par les blocs.
     *
     * @param  list<array{type: string, data: array<string, mixed>}>  $blocks
     * @return list<int>
     */
    public static function mediaIds(array $blocks): array
    {
        $ids = [];
        $walk = function (array $data) use (&$walk, &$ids): void {
            foreach ($data as $key => $value) {
                if (is_array($value)) {
                    $walk($value);
                } elseif (in_array($key, ['image_id', 'file_id'], true) && $value) {
                    $ids[] = (int) $value;
                }
            }
        };
        foreach ($blocks as $block) {
            $walk($block['data']);
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     * @param  array<string, mixed>  $input
     * @param  array<string, string>  $errors
     * @return array<string, mixed>
     */
    private static function cleanFields(array $fields, array $input, string $path, bool $strict, array &$errors): array
    {
        $out = [];

        foreach ($fields as $field) {
            $name = $field['name'];
            $value = $input[$name] ?? null;
            $here = "$path.$name";
            $required = $strict && ($field['required'] ?? false);

            $out[$name] = match ($field['type']) {
                'text', 'textarea' => self::cleanString($value, $field, $here, $required, $errors),
                'rich' => self::cleanRich($value, $field, $here, $required, $errors),
                'select' => array_key_exists((string) $value, $field['options']) ? (string) $value : ($field['default'] ?? array_key_first($field['options'])),
                'media' => self::cleanMedia($value, $here, $required, $errors),
                'url' => self::cleanUrl($value, $field, $here, $required, $errors),
                'number' => self::cleanNumber($value, $here, $required, $errors),
                'repeater' => self::cleanRepeater($value, $field, $here, $strict, $errors),
            };
        }

        return $out;
    }

    private static function cleanString(mixed $value, array $field, string $path, bool $required, array &$errors): string
    {
        $string = is_scalar($value) ? trim((string) $value) : '';

        if (mb_strlen($string) > $field['max']) {
            $errors[$path] = "{$field['label']} : {$field['max']} caractères au maximum.";
            $string = mb_substr($string, 0, $field['max']);
        }
        if ($required && $string === '') {
            $errors[$path] = "{$field['label']} est obligatoire.";
        }

        return $string;
    }

    private static function cleanRich(mixed $value, array $field, string $path, bool $required, array &$errors): string
    {
        $html = is_string($value) ? (string) Html::clean($value) : '';

        if (strlen($html) > $field['max']) {
            $errors[$path] = 'Le texte est trop long.';
            $html = '';
        }
        if ($required && trim(strip_tags($html, '<figure>')) === '') {
            $errors[$path] = "{$field['label']} est obligatoire.";
        }

        return $html;
    }

    private static function cleanMedia(mixed $value, string $path, bool $required, array &$errors): ?int
    {
        $id = is_numeric($value) && (int) $value > 0 ? (int) $value : null;

        if ($required && $id === null) {
            $errors[$path] = 'Une image est obligatoire.';
        }

        return $id;
    }

    private static function cleanUrl(mixed $value, array $field, string $path, bool $required, array &$errors): string
    {
        $url = is_scalar($value) ? trim((string) $value) : '';
        $pattern = ($field['schemes'] ?? null) === 'web' ? '#^(/|https?://)#i' : self::URL_PATTERN;

        if ($url !== '' && (mb_strlen($url) > $field['max'] || ! preg_match($pattern, $url))) {
            $errors[$path] = "{$field['label']} : adresse non autorisée (https://…, /chemin, mailto: ou tel:).";
            $url = '';
        }
        if ($required && $url === '') {
            $errors[$path] = "{$field['label']} est obligatoire.";
        }

        return $url;
    }

    private static function cleanNumber(mixed $value, string $path, bool $required, array &$errors): ?int
    {
        if (! is_numeric($value)) {
            if ($required) {
                $errors[$path] = 'Une valeur numérique est obligatoire.';
            }

            return null;
        }

        return max(0, min(1_000_000_000, (int) $value));
    }

    /** @return list<array<string, mixed>> */
    private static function cleanRepeater(mixed $value, array $field, string $path, bool $strict, array &$errors): array
    {
        $items = is_array($value) ? array_values($value) : [];

        if (count($items) > $field['max']) {
            $errors[$path] = "{$field['label']} : {$field['max']} éléments au maximum.";
            $items = array_slice($items, 0, $field['max']);
        }
        if ($strict && count($items) < $field['min']) {
            $errors[$path] = "{$field['label']} : au moins {$field['min']} élément(s).";
        }

        return array_map(
            fn ($item, $index) => self::cleanFields($field['fields'], is_array($item) ? $item : [], "$path.$index", $strict, $errors),
            $items,
            array_keys($items),
        );
    }
}
