<?php

namespace App\Models\Concerns;

use App\Support\Html;
use App\Support\Locales;

/**
 * Contenu traduisible. Les colonnes du modèle restent en français (langue source : recherche, adresses et listes ne changent pas) ;
 * les traductions vivent dans la colonne JSON « translations » : {"en": {"title": "…"}, "mg": {…}}.
 *
 * Sur une page /en ou /mg, lire $model->title renvoie la traduction si elle est remplie, sinon le français : un contenu pas encore
 * traduit s'affiche donc en français plutôt que vide. Les vues n'ont rien à savoir de la langue.
 *
 * Le modèle déclare :
 *  - $translatable      : attributs traduisibles ;
 *  - $translatableRich  : parmi eux, ceux qui contiennent du HTML (nettoyé à l'enregistrement) ;
 *  - $translatableMerge : attributs-listes dont la traduction ne remplace que certains champs (ex. key_figures : seulement « label »).
 */
trait HasTranslations
{
    public function initializeHasTranslations(): void
    {
        $this->mergeFillable(['translations']);
        $this->mergeCasts(['translations' => 'array']);
    }

    public static function bootHasTranslations(): void
    {
        static::saving(function ($model): void {
            $model->cleanTranslations();
        });
    }

    /** @return list<string> */
    public function translatableAttributes(): array
    {
        return $this->translatable ?? [];
    }

    public function getAttribute($key)
    {
        $value = parent::getAttribute($key);

        if (! in_array($key, $this->translatableAttributes(), true) || Locales::isDefault()) {
            return $value;
        }

        $translated = $this->translation($key, Locales::current());

        if (blank($translated)) {
            return $value;
        }

        // Liste dont seuls certains champs sont traduits : on garde les autres (valeurs, suffixes…) de l'original.
        if (is_array($translated) && is_array($value) && in_array($key, $this->translatableMerge ?? [], true)) {
            return array_replace_recursive($value, array_filter($translated, fn ($item) => ! is_array($item) || array_filter($item, fn ($v) => filled($v))));
        }

        return $translated;
    }

    /** Traduction brute d'un attribut dans une langue (null si absente). */
    public function translation(string $attribute, string $locale): mixed
    {
        return parent::getAttribute('translations')[$locale][$attribute] ?? null;
    }

    public function hasTranslation(string $locale): bool
    {
        return collect(parent::getAttribute('translations')[$locale] ?? [])->contains(fn ($value) => filled($value));
    }

    /** Le HTML saisi dans les onglets de traduction passe par le même nettoyage que le contenu français. */
    protected function cleanTranslations(): void
    {
        $translations = parent::getAttribute('translations');

        if (! is_array($translations)) {
            return;
        }

        foreach ($translations as $locale => $fields) {
            foreach ((array) $fields as $attribute => $value) {
                if (in_array($attribute, $this->translatableRich ?? [], true) && is_string($value)) {
                    $translations[$locale][$attribute] = (string) Html::clean($value);
                }
                // Pas de langue ni de champ vide enregistrés : « pas de traduction » = absence de clé.
                if ($value === null || $value === '' || $value === []) {
                    unset($translations[$locale][$attribute]);
                }
            }
            if (empty($translations[$locale])) {
                unset($translations[$locale]);
            }
        }

        $this->attributes['translations'] = $translations ? json_encode($translations, JSON_UNESCAPED_UNICODE) : null;
    }
}
